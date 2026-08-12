<?php

declare(strict_types=1);

namespace App\Services\Ingest\Adapters;

use App\Models\IngestSource;
use App\Services\Ingest\Contracts\SourceAdapter;
use App\Services\Ingest\EventDraft;
use App\Services\Ingest\Http\PoliteClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use RuntimeException;

/**
 * Ticketmaster's Discovery API.
 *
 * The licensed backbone of the registry: arena shows, sport and the big rooms,
 * with venue coordinates and artwork we are entitled to display.
 *
 * Two quirks shape the code. Deep paging is capped at `size * page < 1000`, so
 * a whole season cannot be walked in one query; we step through date windows
 * instead. And the free tier allows 5000 calls a day at five a second, which is
 * generous but not infinite, so windows are wide and the page size is maxed.
 */
class TicketmasterAdapter implements SourceAdapter
{
    private const BASE_URL = 'https://app.ticketmaster.com/discovery/v2/events.json';

    private const MAX_PAGE_SIZE = 200;

    /** Ticketmaster refuses `size * page >= 1000`. */
    private const DEEP_PAGING_CAP = 1000;

    private const SYDNEY = 'Australia/Sydney';

    public function __construct(private readonly PoliteClient $client) {}

    public function fetch(IngestSource $source): iterable
    {
        $apiKey = Arr::get($source->credentials ?? [], 'api_key');

        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException("Source [{$source->slug}] has no Ticketmaster api_key.");
        }

        $options = $source->options ?? [];
        $windowDays = (int) Arr::get($options, 'window_days', 30);
        $horizonDays = (int) Arr::get($options, 'horizon_days', 180);
        $size = min((int) Arr::get($options, 'size', self::MAX_PAGE_SIZE), self::MAX_PAGE_SIZE);

        $cursor = CarbonImmutable::now(self::SYDNEY);
        $horizon = $cursor->addDays($horizonDays);

        while ($cursor->lessThan($horizon)) {
            $windowEnd = $cursor->addDays($windowDays)->min($horizon);

            yield from $this->fetchWindow($source, $apiKey, $cursor, $windowEnd, $size);

            $cursor = $windowEnd;
        }
    }

    public function normalise(IngestSource $source, array $item): ?EventDraft
    {
        // Ticketmaster ships test fixtures through the live API; they are
        // flagged rather than withheld.
        if (Arr::get($item, 'test') === true) {
            return null;
        }

        $status = Arr::get($item, 'dates.status.code');

        if (in_array($status, ['cancelled', 'offsale'], true)) {
            return null;
        }

        $startsAt = $this->resolveStart($item);

        if ($startsAt === null) {
            return null;
        }

        $venue = Arr::get($item, '_embedded.venues.0', []);
        $externalId = Arr::get($item, 'id');

        if (! is_string($externalId) || $externalId === '') {
            return null;
        }

        return new EventDraft(
            externalId: $externalId,
            title: trim((string) Arr::get($item, 'name')),
            startsAt: $startsAt,
            endsAt: $this->resolveEnd($item),
            venueName: Arr::get($venue, 'name'),
            venueExternalId: Arr::get($venue, 'id'),
            address: Arr::get($venue, 'address.line1'),
            suburb: Arr::get($venue, 'city.name'),
            latitude: $this->floatOrNull(Arr::get($venue, 'location.latitude')),
            longitude: $this->floatOrNull(Arr::get($venue, 'location.longitude')),
            categorySlug: $this->resolveCategory($source, $item),
            price: $this->resolvePrice($item),
            ticketUrl: Arr::get($item, 'url'),
            sourceUrl: Arr::get($item, 'url'),
            imageUrl: $this->resolveImage($item),
            imageCredit: 'Ticketmaster',
            sourceDescription: Arr::get($item, 'info') ?? Arr::get($item, 'description'),
            raw: $item,
        );
    }

    public function requestCount(): int
    {
        return $this->client->requestCount();
    }

    /**
     * Walk one date window, page by page.
     *
     * @return iterable<int, array<string, mixed>>
     */
    private function fetchWindow(
        IngestSource $source,
        string $apiKey,
        CarbonImmutable $from,
        CarbonImmutable $to,
        int $size,
    ): iterable {
        $page = 0;

        do {
            $payload = $this->client->getJson(self::BASE_URL, array_filter([
                'apikey' => $apiKey,
                'countryCode' => Arr::get($source->options ?? [], 'country_code', 'AU'),
                'marketId' => Arr::get($source->options ?? [], 'market_id'),
                'city' => Arr::get($source->options ?? [], 'city'),
                'startDateTime' => $from->utc()->format('Y-m-d\TH:i:s\Z'),
                'endDateTime' => $to->utc()->format('Y-m-d\TH:i:s\Z'),
                'size' => $size,
                'page' => $page,
                'sort' => 'date,asc',
            ], static fn (mixed $value): bool => $value !== null && $value !== ''));

            if ($payload === null) {
                return;
            }

            $events = Arr::get($payload, '_embedded.events', []);

            if ($events === []) {
                return;
            }

            yield from $events;

            $totalPages = (int) Arr::get($payload, 'page.totalPages', 1);
            $page++;

            // Stop before Ticketmaster does, so the window simply ends rather
            // than erroring. Anything beyond the cap is picked up by the next
            // window, which is why windows are kept narrow.
        } while ($page < $totalPages && ($page * $size) < self::DEEP_PAGING_CAP);
    }

    /**
     * Prefer the local wall-clock time. `dateTime` is UTC and correct, but a
     * gig listed as 8pm should read as 8pm even if a timezone database
     * disagrees about the offset on that date.
     */
    private function resolveStart(array $item): ?CarbonImmutable
    {
        if (Arr::get($item, 'dates.start.dateTBA') === true
            || Arr::get($item, 'dates.start.dateTBD') === true) {
            return null;
        }

        $date = Arr::get($item, 'dates.start.localDate');

        if (! is_string($date) || $date === '') {
            return null;
        }

        $noTime = Arr::get($item, 'dates.start.timeTBA') === true
            || Arr::get($item, 'dates.start.noSpecificTime') === true;

        $time = $noTime ? '19:00:00' : (Arr::get($item, 'dates.start.localTime') ?: '19:00:00');

        return CarbonImmutable::parse("{$date} {$time}", self::SYDNEY);
    }

    private function resolveEnd(array $item): ?CarbonImmutable
    {
        $date = Arr::get($item, 'dates.end.localDate');

        if (! is_string($date) || $date === '') {
            return null;
        }

        $time = Arr::get($item, 'dates.end.localTime') ?: '23:00:00';

        return CarbonImmutable::parse("{$date} {$time}", self::SYDNEY);
    }

    /**
     * Map Ticketmaster's segment and genre onto our eight categories. A source
     * may override any of it via its `category_map`.
     */
    private function resolveCategory(IngestSource $source, array $item): ?string
    {
        $classification = collect(Arr::get($item, 'classifications', []))
            ->first(fn (array $c): bool => Arr::get($c, 'primary') === true)
            ?? Arr::get($item, 'classifications.0', []);

        $segment = (string) Arr::get($classification, 'segment.name', '');
        $genre = (string) Arr::get($classification, 'genre.name', '');

        $overrides = $source->category_map ?? [];

        foreach ([$genre, $segment] as $key) {
            if ($key !== '' && isset($overrides[$key])) {
                return $overrides[$key];
            }
        }

        $slug = match ($segment) {
            'Music' => 'music',
            'Sports' => 'sport',
            'Film' => 'arts',
            'Arts & Theatre' => match (true) {
                str_contains($genre, 'Comedy') => 'comedy',
                str_contains($genre, 'Dance'),
                str_contains($genre, 'Classical'),
                str_contains($genre, 'Fine Art') => 'arts',
                default => 'theatre',
            },
            default => null,
        };

        return $slug ?? $source->default_category_slug;
    }

    /**
     * A human-readable band, not a number to transact on. We are pointing at
     * the seller, not selling.
     */
    private function resolvePrice(array $item): ?string
    {
        $range = Arr::get($item, 'priceRanges.0');

        if (! is_array($range)) {
            return null;
        }

        $min = $this->floatOrNull(Arr::get($range, 'min'));
        $max = $this->floatOrNull(Arr::get($range, 'max'));

        if ($min === null) {
            return null;
        }

        if ($max === null || abs($max - $min) < 0.01) {
            return '$'.$this->money($min);
        }

        return '$'.$this->money($min).' – $'.$this->money($max);
    }

    /**
     * Widest 16:9 artwork that is not a placeholder.
     */
    private function resolveImage(array $item): ?string
    {
        $images = collect(Arr::get($item, 'images', []))
            ->filter(fn (array $image): bool => Arr::get($image, 'fallback') !== true)
            ->sortByDesc(fn (array $image): int => (int) Arr::get($image, 'width', 0));

        $wide = $images->first(fn (array $image): bool => Arr::get($image, 'ratio') === '16_9');

        return Arr::get($wide ?? $images->first() ?? [], 'url');
    }

    private function money(float $value): string
    {
        return fmod($value, 1.0) === 0.0
            ? number_format($value, 0)
            : number_format($value, 2);
    }

    private function floatOrNull(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
