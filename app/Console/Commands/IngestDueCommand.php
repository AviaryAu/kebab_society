<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\Ingest\RunSourceJob;
use App\Models\IngestSource;
use Illuminate\Console\Command;

/**
 * The scheduler's single entry point.
 *
 * Cron runs this hourly; it decides which sources are actually due and hands
 * each to the queue. Polling intervals therefore live in the database next to
 * the source, and adding a source never means touching the schedule.
 *
 * Dispatching rather than crawling inline keeps the scheduler process free —
 * important on a managed platform where the scheduler is not a long-lived
 * worker and one slow publisher would otherwise hold up every other source.
 */
class IngestDueCommand extends Command
{
    protected $signature = 'ingest:due
        {--limit= : Cap items per source}
        {--sync : Crawl now instead of queueing}';

    protected $description = 'Queue every ingest source whose polling interval has elapsed';

    public function handle(): int
    {
        $due = IngestSource::query()
            ->enabled()
            ->orderBy('last_run_at')
            ->get()
            ->filter(fn (IngestSource $source): bool => $source->isDue());

        if ($due->isEmpty()) {
            $this->components->info('No sources due.');

            return self::SUCCESS;
        }

        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        foreach ($due as $source) {
            $this->components->info("Queueing {$source->name}");

            $job = new RunSourceJob($source->id, $limit);

            $this->option('sync') ? dispatch_sync($job) : dispatch($job);
        }

        return self::SUCCESS;
    }
}
