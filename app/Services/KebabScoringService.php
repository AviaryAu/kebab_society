<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Restaurant;
use App\Support\RatingResult;

/**
 * Calculates the Kebab Society Rating.
 *
 * This is the single source of truth for the number. Nothing else in the
 * application — and certainly nothing in Vue — may derive a rating.
 *
 * The rating is published out of five, the scale people already read on Google.
 * It is not a copy of the Google rating:
 *
 *   1. Each source rating is shrunk toward a neutral prior in proportion to how
 *      little review volume stands behind it.
 *   2. Society member reviews take the majority of the weight once they exist.
 *   3. A small, bounded editorial adjustment may be applied by hand, and is
 *      always disclosed.
 *
 * A restaurant with no ratings at all stays honestly unrated.
 */
class KebabScoringService
{
    private const MAX_RATING = 5.0;

    public function rate(Restaurant $restaurant): RatingResult
    {
        $weights = config('kebab.rating.weights');
        $prior = (float) config('kebab.rating.prior_rating');
        $priorWeight = (int) config('kebab.rating.prior_weight');
        $reviewTarget = max(1, (int) config('kebab.rating.confidence_review_target'));

        $societyReviews = (int) $restaurant->society_review_count;
        $googleReviews = (int) ($restaurant->google_review_count ?? 0);

        $hasSociety = $societyReviews > 0 && $restaurant->society_rating !== null;
        $hasGoogle = $restaurant->google_rating !== null;

        $confidence = min(1.0, ($societyReviews * 2 + $googleReviews) / $reviewTarget);

        if (! $hasSociety && ! $hasGoogle) {
            return new RatingResult(
                rating: null,
                components: [],
                editorialAdjustment: 0.0,
                confidence: 0.0,
            );
        }

        $signals = [];

        if ($hasSociety) {
            $signals[] = [
                'key' => 'society_rating',
                'label' => 'Society member rating',
                'weight' => (float) $weights['society_rating'],
                'rating' => $this->shrink((float) $restaurant->society_rating, $societyReviews, $prior, $priorWeight),
                'detail' => sprintf('%s Society review%s', $societyReviews, $societyReviews === 1 ? '' : 's'),
            ];
        }

        if ($hasGoogle) {
            $signals[] = [
                'key' => 'google_rating',
                'label' => 'Google rating',
                'weight' => (float) $weights['google_rating'],
                'rating' => $this->shrink((float) $restaurant->google_rating, $googleReviews, $prior, $priorWeight),
                'detail' => sprintf(
                    '%.1f from %s Google review%s',
                    $restaurant->google_rating,
                    number_format($googleReviews),
                    $googleReviews === 1 ? '' : 's',
                ),
            ];
        }

        // Normalise across whichever signals we actually hold.
        $totalWeight = array_sum(array_column($signals, 'weight'));

        $components = array_map(
            fn (array $signal): array => [
                'key' => $signal['key'],
                'label' => $signal['label'],
                'weight' => round($signal['weight'] / $totalWeight, 4),
                'rating' => round($signal['rating'], 2),
                'detail' => $signal['detail'],
            ],
            $signals,
        );

        $weighted = 0.0;

        foreach ($components as $component) {
            $weighted += $component['rating'] * $component['weight'];
        }

        $adjustment = $this->clampAdjustment((float) $restaurant->editorial_adjustment);

        return new RatingResult(
            rating: round(max(0.0, min(self::MAX_RATING, $weighted + $adjustment)), 1),
            components: $components,
            editorialAdjustment: $adjustment,
            confidence: $confidence,
        );
    }

    /**
     * Recalculate and persist the rating for a restaurant.
     */
    public function apply(Restaurant $restaurant): Restaurant
    {
        $result = $this->rate($restaurant);

        $restaurant->forceFill([
            'kebab_rating' => $result->rating,
            'rating_breakdown' => $result->toArray(),
        ])->save();

        return $restaurant;
    }

    /**
     * Bayesian shrinkage toward the neutral prior.
     */
    private function shrink(float $rating, int $reviews, float $prior, int $priorWeight): float
    {
        return (($rating * $reviews) + ($prior * $priorWeight)) / max(1, $reviews + $priorWeight);
    }

    private function clampAdjustment(float $adjustment): float
    {
        $limit = (float) config('kebab.rating.editorial_adjustment_limit');

        return round(max(-$limit, min($limit, $adjustment)), 2);
    }
}
