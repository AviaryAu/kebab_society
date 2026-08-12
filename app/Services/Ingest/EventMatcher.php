<?php

declare(strict_types=1);

namespace App\Services\Ingest;

use App\Models\Event;
use App\Models\IngestSource;
use Illuminate\Support\Str;

/**
 * Decides whether a draft is something we already have.
 *
 * The same gig reaches us from a ticketing API, the venue's own site and two
 * editorial round-ups. Getting this wrong in one direction floods the site with
 * duplicates; getting it wrong in the other silently drops real events. So
 * matching runs from strongest evidence to weakest and reports how sure it is,
 * leaving anything doubtful for a human.
 */
class EventMatcher
{
    /**
     * The event this draft refers to, if we already know about it.
     */
    public function match(IngestSource $source, EventDraft $draft): ?Event
    {
        // Same source, same id: definitively the same record, coming round again.
        $event = Event::query()
            ->where('ingest_source_id', $source->id)
            ->where('external_id', $draft->externalId)
            ->first();

        if ($event !== null) {
            return $event;
        }

        // Same happening, different source.
        $event = Event::query()
            ->where('fingerprint', $draft->fingerprint())
            ->first();

        if ($event !== null) {
            return $event;
        }

        return $this->findByLikeness($draft);
    }

    /**
     * How confident we are in the result of match(), from 0 to 1.
     *
     * A null match is 1.0 rather than 0.0: being sure something is new is a
     * confident answer, and the alternative would send every genuinely new
     * event to the review queue.
     */
    public function confidence(IngestSource $source, EventDraft $draft, ?Event $event): float
    {
        if ($event === null) {
            return 1.0;
        }

        if ($event->ingest_source_id === $source->id
            && $event->external_id === $draft->externalId) {
            return 1.0;
        }

        if ($event->fingerprint === $draft->fingerprint()) {
            return 0.95;
        }

        return $this->similarity($event->title, $draft->title);
    }

    /**
     * Whether this source is entitled to overwrite what is already recorded.
     *
     * Precedence is by source slug in config. An event with no source at all
     * was made by a person, and outranks everything.
     */
    public function outranks(IngestSource $source, Event $event): bool
    {
        if ($event->ingest_source_id === null) {
            return false;
        }

        if ($event->ingest_source_id === $source->id) {
            return true;
        }

        $order = (array) config('ingest.source_precedence', []);

        $incoming = array_search($source->slug, $order, true);
        $existing = array_search($event->ingestSource?->slug, $order, true);

        // Anything unlisted ranks last, and never displaces a listed source.
        if ($incoming === false) {
            return false;
        }

        if ($existing === false) {
            return true;
        }

        return $incoming < $existing;
    }

    /**
     * Last resort: a near-identical title at the same venue on the same night.
     *
     * Scoped to a narrow date window and a single venue, because comparing
     * every title against every other one would be both slow and reckless.
     */
    private function findByLikeness(EventDraft $draft): ?Event
    {
        $hours = (int) config('ingest.matching.date_window_hours', 24);
        $threshold = (float) config('ingest.matching.title_similarity', 0.82);

        $candidates = Event::query()
            ->whereBetween('start_datetime', [
                $draft->startsAt->subHours($hours),
                $draft->startsAt->addHours($hours),
            ])
            ->when(
                $draft->venueName !== null,
                fn ($query) => $query->whereHas(
                    'venue',
                    fn ($q) => $q->where('name', 'like', '%'.$this->keyword($draft->venueName).'%'),
                ),
            )
            ->limit(50)
            ->get();

        return $candidates
            ->map(fn (Event $event): array => [
                'event' => $event,
                'score' => $this->similarity($event->title, $draft->title),
            ])
            ->filter(fn (array $row): bool => $row['score'] >= $threshold)
            ->sortByDesc('score')
            ->first()['event'] ?? null;
    }

    /**
     * 0 to 1, case- and punctuation-insensitive.
     */
    private function similarity(string $a, string $b): float
    {
        $a = Str::slug($a);
        $b = Str::slug($b);

        if ($a === '' || $b === '') {
            return 0.0;
        }

        if ($a === $b) {
            return 1.0;
        }

        similar_text($a, $b, $percent);

        return round($percent / 100, 4);
    }

    /**
     * The most distinctive word in a venue name, for a LIKE that tolerates
     * "The Enmore" against "Enmore Theatre".
     */
    private function keyword(string $venueName): string
    {
        $words = preg_split('/\s+/', Str::lower($venueName)) ?: [];

        $words = array_filter(
            $words,
            static fn (string $word): bool => ! in_array($word, ['the', 'at', 'sydney', 'theatre', 'hotel'], true)
                && mb_strlen($word) > 2,
        );

        return $words === [] ? Str::lower($venueName) : (string) reset($words);
    }
}
