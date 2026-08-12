<?php

declare(strict_types=1);

namespace App\Jobs\Ingest;

use App\Models\Event;
use App\Services\Ingest\ImageImporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Copies one event's hero artwork onto our storage.
 *
 * One event per job rather than a batch: each is an independent download from
 * a different host, and one dead image should not cost the other nine.
 */
class ImportEventImageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300];

    public function __construct(public readonly int $eventId)
    {
        $this->onQueue('ingest-media');
    }

    public function handle(ImageImporter $images): void
    {
        $event = Event::query()->with('ingestSource')->find($this->eventId);

        if ($event === null || $event->import_locked) {
            return;
        }

        $images->import($event);
    }
}
