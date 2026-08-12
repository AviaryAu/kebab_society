<?php

declare(strict_types=1);

namespace App\Services\Ingest;

use App\Enums\ImageLicence;
use App\Enums\ImportStatus;
use App\Models\Event;
use App\Models\EventImport;
use App\Models\IngestRun;
use App\Models\IngestSource;
use Illuminate\Support\Str;

/**
 * Turns a draft into a staged import, and — where the source is trusted enough
 * — into a published event.
 *
 * This is where the project's copyright posture is actually applied, so the
 * rules are stated once, here, rather than assumed by each adapter:
 *
 *   1. A publisher's prose is written to `event_imports.raw_payload` and
 *      nowhere else. `Event::description` is only ever filled by our own
 *      copywriter, or left null for one to fill later.
 *   2. Artwork is recorded only when the source's tier grants us the right.
 *   3. Every ingested event carries an attribution link back to whoever told
 *      us about it.
 */
class EventImporter
{
    public function __construct(
        private readonly VenueResolver $venues,
        private readonly EventMatcher $matcher,
    ) {}

    /**
     * @return string One of: created, updated, staged, skipped.
     */
    public function import(IngestSource $source, EventDraft $draft, IngestRun $run): string
    {
        $import = $this->stage($source, $draft, $run);

        // A reviewer has already turned this down. Remembering that is the
        // only thing stopping the next run from asking again forever.
        if ($import->status === ImportStatus::Rejected) {
            return 'skipped';
        }

        $existing = $this->matcher->match($source, $draft);

        if ($existing !== null && $existing->import_locked) {
            $import->forceFill([
                'event_id' => $existing->id,
                'status' => ImportStatus::Merged,
                'message' => 'Event is locked for manual editing.',
            ])->save();

            return 'skipped';
        }

        $confidence = $this->matcher->confidence($source, $draft, $existing);

        if (! $this->mayPublish($source, $confidence)) {
            $import->forceFill([
                'event_id' => $existing?->id,
                'match_confidence' => $confidence,
                'status' => ImportStatus::Pending,
            ])->save();

            // Waiting for a reviewer is an outcome in its own right. Reporting
            // it as "skipped" made a run that had queued a dozen events look
            // like a run that had done nothing at all.
            return 'staged';
        }

        $event = $existing === null
            ? $this->createEvent($source, $draft)
            : $this->updateEvent($existing, $source, $draft);

        $import->forceFill([
            'event_id' => $event->id,
            'match_confidence' => $confidence,
            'status' => ImportStatus::Auto,
        ])->save();

        return $existing === null ? 'created' : 'updated';
    }

    /**
     * Publish an import a reviewer accepted.
     *
     * The event is created from the stored normalised facts, so what goes live
     * is exactly what the reviewer looked at, not a fresh fetch that may have
     * changed underneath them.
     */
    public function publish(EventImport $import, ?int $reviewerId = null): ?Event
    {
        $source = $import->source;
        $normalised = $import->normalised;

        if ($source === null || ! is_array($normalised) || $normalised === []) {
            return null;
        }

        $draft = EventDraft::fromNormalised($normalised, $import->raw_payload ?? []);

        $existing = $import->event ?? $this->matcher->match($source, $draft);

        if ($existing !== null && $existing->import_locked) {
            $import->forceFill([
                'status' => ImportStatus::Merged,
                'message' => 'Event is locked for manual editing.',
                'reviewed_by' => $reviewerId,
                'reviewed_at' => now(),
            ])->save();

            return $existing;
        }

        $event = $existing === null
            ? $this->createEvent($source, $draft)
            : $this->updateEvent($existing, $source, $draft);

        $import->forceFill([
            'event_id' => $event->id,
            'status' => ImportStatus::Approved,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
        ])->save();

        return $event;
    }

    /**
     * Turn an import down.
     *
     * The row is kept rather than deleted: its fingerprint is how the next run
     * knows not to offer the same thing again.
     */
    public function reject(EventImport $import, ?int $reviewerId = null, ?string $reason = null): void
    {
        $import->forceFill([
            'status' => ImportStatus::Rejected,
            'message' => $reason,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
        ])->save();
    }

    /**
     * Record what we were told, whatever happens next. The staging row is the
     * audit trail and the review queue at once.
     */
    private function stage(IngestSource $source, EventDraft $draft, IngestRun $run): EventImport
    {
        $import = EventImport::query()->firstOrNew([
            'ingest_source_id' => $source->id,
            'external_id' => $draft->externalId,
        ]);

        $import->fill([
            'ingest_run_id' => $run->id,
            'source_url' => $draft->sourceUrl,
            'fingerprint' => $draft->fingerprint(),
            'raw_payload' => $draft->raw,
            'normalised' => $draft->toArray(),
            'proposed_title' => $draft->title,
            'proposed_start' => $draft->startsAt,
            'proposed_venue_name' => $draft->venueName,
            'proposed_suburb' => $draft->suburb,
        ]);

        if (! $import->exists) {
            $import->status = ImportStatus::Pending;
        }

        $import->save();

        return $import;
    }

    private function createEvent(IngestSource $source, EventDraft $draft): Event
    {
        $venue = $this->venues->resolve($source, $draft);

        $event = new Event;
        $event->fill($this->attributes($source, $draft, $venue?->id));
        $event->slug = $this->uniqueSlug($draft);

        // No description. The copywriter fills this from the facts; until then
        // the page shows the facts alone rather than someone else's sentences.
        $event->description = null;
        $event->status = 'draft';

        $event->save();

        return $event;
    }

    private function updateEvent(Event $event, IngestSource $source, EventDraft $draft): Event
    {
        $venue = $this->venues->resolve($source, $draft);

        // A better-ranked source may correct a weaker one; a weaker one may
        // only fill gaps.
        if ($this->matcher->outranks($source, $event)) {
            $event->fill($this->attributes($source, $draft, $venue?->id));
        } else {
            $event->fill(array_filter(
                $this->attributes($source, $draft, $venue?->id),
                fn (string $key): bool => blank($event->{$key}),
                ARRAY_FILTER_USE_KEY,
            ));
        }

        $event->save();

        return $event;
    }

    /**
     * The facts, and only the facts.
     *
     * @return array<string, mixed>
     */
    private function attributes(IngestSource $source, EventDraft $draft, ?int $venueId): array
    {
        $importImages = $source->mayImportImages();

        return [
            'title' => $draft->title,
            'start_datetime' => $draft->startsAt,
            'end_datetime' => $draft->endsAt,
            'venue_id' => $venueId,
            'suburb' => $draft->suburb ?? 'Sydney',
            'category_slug' => $draft->categorySlug ?? $source->default_category_slug ?? 'music',
            'price' => $draft->price,
            'ticket_url' => $draft->ticketUrl,
            'latitude' => $draft->latitude,
            'longitude' => $draft->longitude,

            'ingest_source_id' => $source->id,
            'external_id' => $draft->externalId,
            'source_url' => $draft->sourceUrl,
            'source_name' => $source->name,
            'source_attribution_url' => $draft->sourceUrl,
            'fingerprint' => $draft->fingerprint(),
            'last_synced_at' => now(),

            // An image URL is only worth recording if we are allowed to use it.
            // Editorial listers fall through to `none`, and the page uses a
            // Keep Sydney Live card instead.
            'image_source_url' => $importImages ? $draft->imageUrl : null,
            'image_credit' => $importImages ? $draft->imageCredit : null,
            'image_licence' => $importImages && $draft->imageUrl !== null
                ? ImageLicence::Licensed
                : ImageLicence::None,
        ];
    }

    /**
     * Auto-publishing needs both a trusted source and a confident match.
     * A licensed API that we cannot reconcile with an existing record still
     * goes to a human.
     */
    private function mayPublish(IngestSource $source, float $confidence): bool
    {
        if (! $source->mayAutoPublish()) {
            return false;
        }

        return $confidence >= (float) config('ingest.matching.auto_publish_confidence', 0.9);
    }

    private function uniqueSlug(EventDraft $draft): string
    {
        // Dated, because the same show returns next season and both deserve a
        // page.
        $base = Str::slug($draft->title.'-'.$draft->startsAt->format('M-Y'));
        $slug = $base;
        $suffix = 2;

        while (Event::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
