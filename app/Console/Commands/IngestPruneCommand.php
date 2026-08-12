<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ImportStatus;
use App\Models\EventImport;
use App\Models\IngestRun;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Housekeeping. Audit trails are useful for weeks, not forever, and an
 * ingestion pipeline that never forgets anything eventually becomes the
 * largest thing in the database.
 */
class IngestPruneCommand extends Command
{
    protected $signature = 'ingest:prune {--dry-run : Report what would be deleted}';

    protected $description = 'Delete expired ingest runs and resolved imports';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $runsBefore = Carbon::now()->subDays((int) config('ingest.retention.runs_days', 90));
        $importsBefore = Carbon::now()->subDays((int) config('ingest.retention.resolved_imports_days', 30));

        $runs = IngestRun::query()->where('started_at', '<', $runsBefore);

        // Rejections are kept: they are how we remember not to re-import
        // something a reviewer has already turned down.
        $imports = EventImport::query()
            ->whereIn('status', [
                ImportStatus::Approved->value,
                ImportStatus::Auto->value,
                ImportStatus::Merged->value,
            ])
            ->where('updated_at', '<', $importsBefore);

        $runCount = $runs->count();
        $importCount = $imports->count();

        if (! $dryRun) {
            $runs->delete();
            $imports->delete();
        }

        $this->components->twoColumnDetail('Ingest runs', (string) $runCount);
        $this->components->twoColumnDetail('Resolved imports', (string) $importCount);

        if ($dryRun) {
            $this->components->info('Dry run: nothing deleted.');
        }

        return self::SUCCESS;
    }
}
