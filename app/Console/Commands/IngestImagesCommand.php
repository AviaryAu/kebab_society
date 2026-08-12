<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\Ingest\ImportEventImageJob;
use App\Models\Event;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Copies hero artwork for events whose source permits it.
 *
 * Separate from ingest:run for the same reason as ingest:copy — downloading
 * pictures is slow, occasionally fails, and should never hold up the listings
 * themselves.
 */
class IngestImagesCommand extends Command
{
    protected $signature = 'ingest:images
        {--limit=100 : Maximum images to fetch}
        {--sync : Fetch now instead of queueing}';

    protected $description = 'Import hero images for ingested events';

    public function handle(): int
    {
        $events = Event::query()
            ->with('ingestSource')
            ->whereNotNull('ingest_source_id')
            ->whereNotNull('image_source_url')
            ->where('import_locked', false)
            ->where('start_datetime', '>=', now())
            // Nothing stored yet, or still pointing at somebody else's server.
            ->where(fn (Builder $query) => $query
                ->whereNull('image')
                ->orWhereColumn('image', 'image_source_url'))
            ->orderByDesc('featured')
            ->orderBy('start_datetime')
            ->limit((int) $this->option('limit'))
            ->get()
            ->filter(fn (Event $event): bool => $event->ingestSource?->mayImportImages() ?? false);

        if ($events->isEmpty()) {
            $this->components->info('No images to import.');

            return self::SUCCESS;
        }

        foreach ($events as $event) {
            $job = new ImportEventImageJob($event->id);

            $this->option('sync') ? dispatch_sync($job) : dispatch($job);
        }

        $this->components->info(sprintf(
            '%d %s.',
            $events->count(),
            $this->option('sync') ? 'images imported' : 'images queued',
        ));

        return self::SUCCESS;
    }
}
