<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Restaurant;
use App\Support\RestaurantFilters;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Finds kebabs.
 *
 * Everything the map, the search box, Kebab Radar and Kebab Emergency need in
 * order to answer "where should I get my next kebab?".
 */
class KebabDiscoveryService
{
    /**
     * @return Collection<int, Restaurant>
     */
    public function search(RestaurantFilters $filters): Collection
    {
        $query = Restaurant::query()
            ->with(['suburb', 'kebabStyles'])
            ->discoverable()
            ->matching($filters->search)
            ->withAnyStyle($filters->styles)
            ->minimumScore($filters->minimumScore);

        if ($filters->societyCertified) {
            $query->societyApproved();
        }

        if ($filters->suburb !== null) {
            $query->whereHas('suburb', fn ($suburb) => $suburb->where('slug', $filters->suburb));
        }

        $restaurants = $query->orderByDesc('kebab_score')->get();

        // Trading hours are stored as JSON, so time-based filters are resolved
        // in PHP through the shared OpeningHours logic.
        if ($filters->openNow) {
            $now = CarbonImmutable::now();
            $restaurants = $restaurants->filter(fn (Restaurant $r): bool => $r->isOpenAt($now));
        }

        if ($filters->lateNight) {
            $restaurants = $restaurants->filter->tradesLateNight();
        }

        return $restaurants->values();
    }

    /**
     * Nearest kebabs to a point, used by Kebab Radar and Kebab Emergency.
     *
     * @return Collection<int, Restaurant>
     */
    public function nearest(
        float $latitude,
        float $longitude,
        RestaurantFilters $filters,
        int $limit = 5,
    ): Collection {
        return $this->search($filters)
            ->each(fn (Restaurant $restaurant) => $restaurant->setAttribute(
                'distance_km',
                round($restaurant->distanceTo($latitude, $longitude), 2),
            ))
            ->sortBy('distance_km')
            ->take($limit)
            ->values();
    }
}
