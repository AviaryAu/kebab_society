<?php

declare(strict_types=1);

namespace App\Services\Ingest;

use App\Services\Ingest\Http\PoliteClient;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

/**
 * Runtime geocoding against Nominatim.
 *
 * Carried over from scripts/geocode_seed.py, which did this at build time so
 * the app carried no key and no runtime dependency. Ingestion changes that: we
 * cannot know in advance which venues will turn up. The manners come with it —
 * one request per second and a contactable user agent are Nominatim's terms,
 * and PoliteClient enforces both.
 *
 * Results are cached hard. A venue's coordinates do not move, and the cheapest
 * request is the one we never make.
 */
class Geocoder
{
    /** Roughly the Sydney basin, so a query for "Newtown" cannot land in Ohio. */
    private const VIEWBOX = '150.5,-34.3,151.6,-33.4';

    public function __construct(private readonly PoliteClient $client) {}

    /**
     * @return array{latitude: float, longitude: float}|null
     */
    public function locate(string $query): ?array
    {
        if (! config('ingest.geocoding.enabled', true)) {
            return null;
        }

        $query = trim($query);

        if ($query === '') {
            return null;
        }

        $days = (int) config('ingest.geocoding.cache_days', 90);
        $key = 'ingest:geocode:'.md5(mb_strtolower($query));

        // Misses are cached too, as an empty array. Somewhere unfindable stays
        // unfindable, and re-asking every run wastes the rate limit.
        $cached = Cache::remember(
            $key,
            now()->addDays($days),
            fn (): array => $this->lookup($query) ?? [],
        );

        return $cached === [] ? null : $cached;
    }

    /**
     * @return array{latitude: float, longitude: float}|null
     */
    private function lookup(string $query): ?array
    {
        $response = $this->client->get((string) config('ingest.geocoding.endpoint'), [
            'q' => $query,
            'format' => 'json',
            'limit' => 1,
            'countrycodes' => 'au',
            'viewbox' => config('ingest.geocoding.viewbox', self::VIEWBOX),
            'bounded' => 1,
        ]);

        if ($response === null || ! $response->successful()) {
            return null;
        }

        $result = Arr::get($response->json(), '0');

        if (! is_array($result)) {
            return null;
        }

        $latitude = Arr::get($result, 'lat');
        $longitude = Arr::get($result, 'lon');

        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return null;
        }

        return [
            'latitude' => (float) $latitude,
            'longitude' => (float) $longitude,
        ];
    }
}
