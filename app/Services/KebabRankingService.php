<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Restaurant;
use App\Support\LeaderboardDefinition;
use Illuminate\Support\Collection;

/**
 * Builds Kebab Society leaderboards.
 *
 * Ranking lives here — never in a controller and never in a Vue component —
 * so that every board is calculated the same way and can be tested.
 */
class KebabRankingService
{
    public const DEFAULT_LIMIT = 25;

    /**
     * @return Collection<int, LeaderboardDefinition>
     */
    public function definitions(): Collection
    {
        return collect([
            new LeaderboardDefinition(
                key: 'best-kebab',
                title: 'Best Kebab in Sydney',
                tagline: 'The overall standings. Disputed constantly. Settled here.',
            ),
            new LeaderboardDefinition(
                key: 'best-hsp',
                title: 'Best HSP',
                tagline: 'Chips, meat, sauce, consequences.',
                styleSlug: 'hsp',
            ),
            new LeaderboardDefinition(
                key: 'best-late-night',
                title: 'Best Late Night Kebab',
                tagline: 'Still trading when your judgement is not.',
                lateNightOnly: true,
            ),
            new LeaderboardDefinition(
                key: 'society-certified',
                title: 'Society Certified',
                tagline: 'Inspected in person. Approved in writing.',
                societyApprovedOnly: true,
            ),
        ]);
    }

    public function find(string $key): ?LeaderboardDefinition
    {
        return $this->definitions()->firstWhere('key', $key);
    }

    /**
     * Ranked restaurants for a board, highest score first.
     *
     * @return Collection<int, array{rank: int, restaurant: Restaurant}>
     */
    public function entries(LeaderboardDefinition $definition, int $limit = self::DEFAULT_LIMIT): Collection
    {
        $query = Restaurant::query()
            ->with(['suburb', 'kebabStyles', 'photos'])
            ->discoverable()
            ->whereNotNull('kebab_rating')
            ->minimumRating($definition->minimumRating)
            ->orderByDesc('kebab_rating')
            ->orderByDesc('google_review_count')
            ->orderBy('name');

        if ($definition->styleSlug !== null) {
            $query->withAnyStyle([$definition->styleSlug]);
        }

        if ($definition->societyApprovedOnly) {
            $query->societyApproved();
        }

        $restaurants = $query->get();

        if ($definition->lateNightOnly) {
            // Trading hours live in JSON, so this filter is applied in PHP
            // using the same OpeningHours logic the rest of the app uses.
            $restaurants = $restaurants->filter->tradesLateNight()->values();
        }

        return $restaurants
            ->take($limit)
            ->values()
            ->map(fn (Restaurant $restaurant, int $index): array => [
                'rank' => $index + 1,
                'restaurant' => $restaurant,
            ]);
    }
}
