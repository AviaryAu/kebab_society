<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\Ingest\GenerateEventCopyJob;
use App\Models\Event;
use App\Services\Ingest\CopyWriter;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Finds ingested events without usable copy and writes some.
 *
 * Kept separate from ingest:run so that fetching events and writing about them
 * fail independently: a dead model provider should never stop new listings
 * arriving, and an exhausted daily allowance should simply resume tomorrow.
 */
class IngestCopyCommand extends Command
{
    protected $signature = 'ingest:copy
        {--limit=100 : Maximum events to write copy for}
        {--sync : Write now instead of queueing}
        {--force : Rewrite even where copy already exists}';

    protected $description = 'Generate Keep Sydney Live copy for ingested events';

    public function handle(CopyWriter $writer): int
    {
        if (! $writer->isEnabled()) {
            $this->components->warn('Copy generation is disabled or GROQ_API_KEY is unset.');

            return self::SUCCESS;
        }

        $limit = (int) $this->option('limit');
        $force = (bool) $this->option('force');

        $events = Event::query()
            ->with(['venue:id,name', 'ingestSource'])
            ->whereNotNull('ingest_source_id')
            ->where('import_locked', false)
            ->where('start_datetime', '>=', now())
            ->when(! $force, fn (Builder $query) => $query->where(
                fn (Builder $query) => $query
                    ->whereNull('copy_generated_at')
                    ->orWhereNull('description'),
            ))
            // What people are about to look at gets written first, so a thin
            // allowance is spent where it shows.
            ->orderByDesc('featured')
            ->orderBy('start_datetime')
            ->limit($limit)
            ->get()
            ->filter(fn (Event $event): bool => $force || $event->needsGeneratedCopy());

        if ($events->isEmpty()) {
            $this->components->info('Every ingested event already has copy.');

            return self::SUCCESS;
        }

        $this->components->twoColumnDetail('Events queued', (string) $events->count());
        $this->components->twoColumnDetail('Budget remaining', (string) $writer->budgetRemaining());

        $batches = $events->pluck('id')->chunk(
            max(1, (int) config('ingest.ai.batch_size', 10)),
        );

        foreach ($batches as $ids) {
            $job = new GenerateEventCopyJob($ids->values()->all());

            if ($this->option('sync')) {
                dispatch_sync($job);

                continue;
            }

            dispatch($job);
        }

        $this->components->info(sprintf(
            '%d %s.',
            $batches->count(),
            $this->option('sync') ? 'batches written' : 'batches queued',
        ));

        return self::SUCCESS;
    }
}
