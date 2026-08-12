<?php

declare(strict_types=1);

namespace App\Jobs\Ingest;

use App\Models\Event;
use App\Services\Ingest\CopyWriter;
use App\Services\Ingest\Exceptions\CopyWriterThrottled;
use App\Services\Ingest\GeneratedCopy;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Writes copy for a batch of events.
 *
 * Batched rather than one job per event, because the free tier is capped on
 * requests per day: ten events in one prompt costs a tenth of the allowance.
 */
class GenerateEventCopyJob implements ShouldQueue
{
    use Queueable;

    /**
     * Being throttled is expected on a free tier, so the job is patient. Each
     * release is honoured for as long as the provider asked, which is why this
     * can afford to be a large number.
     */
    public int $tries = 12;

    /**
     * @param  array<int, int>  $eventIds
     */
    public function __construct(public readonly array $eventIds)
    {
        $this->onQueue('ingest-ai');
    }

    public function handle(CopyWriter $writer): void
    {
        $events = Event::query()
            ->with(['venue:id,name', 'ingestSource'])
            ->whereIn('id', $this->eventIds)
            ->get()
            // Re-check on the way in: another run may have written this copy
            // between queueing and running.
            ->filter(fn (Event $event): bool => $event->needsGeneratedCopy());

        if ($events->isEmpty()) {
            return;
        }

        try {
            $copy = $writer->generateMany($events);
        } catch (CopyWriterThrottled $e) {
            $this->release($e->retryAfter);

            return;
        }

        foreach ($events as $event) {
            if (! isset($copy[$event->id])) {
                continue;
            }

            $this->apply($event, $copy[$event->id]);
        }
    }

    private function apply(Event $event, GeneratedCopy $copy): void
    {
        $event->description = $copy->description;

        // Never overwrite a meta description someone wrote by hand.
        if (blank($event->meta_description)) {
            $event->meta_description = $copy->metaDescription;
        }

        $event->copy_model = $copy->model;

        // Template copy is a placeholder, so it does not claim the facts hash.
        // Leaving that null is what lets ingest:copy find it again tomorrow
        // when there is budget for the real thing.
        if (! GeneratedCopy::isTemplate($copy->model)) {
            $event->copy_generated_at = now();
            $event->facts_hash = $event->factsHash();
        }

        $event->save();
    }
}
