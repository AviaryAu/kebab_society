<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\IngestRun;
use App\Models\IngestSource;
use App\Services\Ingest\EventDraft;
use App\Services\Ingest\EventImporter;
use App\Services\Ingest\SourceRunner;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class IngestRunCommand extends Command
{
    protected $signature = 'ingest:run
        {--source= : Slug of a single source to run}
        {--dry-run : Fetch and normalise without writing anything}
        {--limit= : Stop after this many items per source}';

    protected $description = 'Pull events from one or all enabled ingest sources';

    public function handle(SourceRunner $runner, EventImporter $importer): int
    {
        $sources = $this->resolveSources();

        if ($sources->isEmpty()) {
            $this->components->warn('No matching enabled sources.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        if ($dryRun) {
            $this->components->info('Dry run: nothing will be written.');
        }

        foreach ($sources as $source) {
            $this->components->task(
                "Running {$source->name}",
                function () use ($runner, $importer, $source, $dryRun, $limit): bool {
                    $run = $runner->run(
                        $source,
                        function (EventDraft $draft, IngestRun $run) use ($importer, $source, $dryRun): string {
                            if ($dryRun) {
                                $this->preview($draft);

                                return 'skipped';
                            }

                            return $importer->import($source, $draft, $run);
                        },
                        $dryRun,
                        $limit,
                    );

                    $this->report($run);

                    return $run->status !== 'failed';
                },
            );
        }

        return self::SUCCESS;
    }

    private function preview(EventDraft $draft): void
    {
        $this->line(sprintf(
            '  <fg=gray>%s</> %s <fg=gray>@</> %s <fg=gray>%s</>',
            $draft->startsAt->format('D j M g:ia'),
            $draft->title,
            $draft->venueName ?? 'unknown venue',
            $draft->price ?? '',
        ));
    }

    private function report(IngestRun $run): void
    {
        $this->newLine();
        $this->components->twoColumnDetail('  Seen', (string) $run->items_seen);
        $this->components->twoColumnDetail('  Created', (string) $run->items_created);
        $this->components->twoColumnDetail('  Updated', (string) $run->items_updated);
        $this->components->twoColumnDetail('  Awaiting review', (string) $run->items_staged);
        $this->components->twoColumnDetail('  Skipped', (string) $run->items_skipped);
        $this->components->twoColumnDetail('  Failed', (string) $run->items_failed);
        $this->components->twoColumnDetail('  Requests', (string) $run->requests_made);

        if ($run->error !== null) {
            $this->components->error($run->error);
        }
    }

    /**
     * @return Collection<int, IngestSource>
     */
    private function resolveSources(): Collection
    {
        $query = IngestSource::query()->enabled();

        if (is_string($slug = $this->option('source'))) {
            $query->where('slug', $slug);
        }

        return $query->orderBy('name')->get();
    }
}
