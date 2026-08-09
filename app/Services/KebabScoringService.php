<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Restaurant;
use App\Support\ScoreResult;

/**
 * Calculates the Kebab Society Score.
 *
 * This is the single source of truth for the number. Nothing else in the
 * application — and certainly nothing in Vue — may derive a score.
 *
 * The MVP model blends the signals we genuinely hold:
 *
 *   1. Society rating   - our own members' verdict (weighted highest)
 *   2. Google rating    - a secondary, external signal
 *   3. Confidence       - how much opinion sits behind those ratings
 *
 * Ratings are shrunk toward a neutral prior so a single enthusiastic review
 * cannot mint a legendary kebab. An editorial adjustment lets the Society
 * apply a bounded human correction, which is always disclosed.
 */
class KebabScoringService
{
    private const MAX_STAR_RATING = 5.0;

    public function score(Restaurant $restaurant): ScoreResult
    {
        $weights = config('kebab.scoring.weights');
        $prior = (float) config('kebab.scoring.prior_rating');
        $priorWeight = (int) config('kebab.scoring.prior_weight');
        $reviewTarget = max(1, (int) config('kebab.scoring.confidence_review_target'));

        $societyReviews = (int) $restaurant->society_review_count;
        $googleReviews = (int) ($restaurant->google_review_count ?? 0);
        $hasSocietyVerdict = $societyReviews > 0 && $restaurant->society_rating !== null;

        // Until the Society has its own reviews for a shop, its share of the
        // score falls to the external signal rather than being invented.
        $societyWeight = $hasSocietyVerdict ? (float) $weights['society_rating'] : 0.0;
        $googleWeight = (float) $weights['google_rating']
            + ($hasSocietyVerdict ? 0.0 : (float) $weights['society_rating']);
        $confidenceWeight = (float) $weights['confidence'];

        $components = [];

        if ($hasSocietyVerdict) {
            $societyValue = $this->shrinkRating(
                (float) $restaurant->society_rating,
                $societyReviews,
                $prior,
                $priorWeight,
            );

            $components[] = $this->component(
                key: 'society_rating',
                label: 'Society rating',
                weight: $societyWeight,
                value: $societyValue,
                detail: sprintf('%s Society review%s', $societyReviews, $societyReviews === 1 ? '' : 's'),
            );
        }

        $googleValue = $restaurant->google_rating === null
            ? $this->ratingToPercentage($prior)
            : $this->shrinkRating((float) $restaurant->google_rating, $googleReviews, $prior, $priorWeight);

        $components[] = $this->component(
            key: 'google_rating',
            label: 'Google rating',
            weight: $googleWeight,
            value: $googleValue,
            detail: $restaurant->google_rating === null
                ? 'No Google rating held — treated as average'
                : sprintf('%.1f from %s Google reviews', $restaurant->google_rating, number_format($googleReviews)),
        );

        $confidenceValue = min(1.0, ($societyReviews * 2 + $googleReviews) / $reviewTarget) * 100;

        $components[] = $this->component(
            key: 'confidence',
            label: 'Weight of opinion',
            weight: $confidenceWeight,
            value: $confidenceValue,
            detail: $confidenceValue >= 100
                ? 'Thoroughly reviewed'
                : 'Still gathering evidence',
        );

        $adjustment = $this->clampAdjustment((int) $restaurant->editorial_adjustment);
        $raw = array_sum(array_column($components, 'points')) + $adjustment;

        return new ScoreResult(
            score: (int) round(max(0, min(100, $raw))),
            components: $components,
            editorialAdjustment: $adjustment,
        );
    }

    /**
     * Recalculate and persist the score for a restaurant.
     */
    public function apply(Restaurant $restaurant): Restaurant
    {
        $result = $this->score($restaurant);

        $restaurant->forceFill([
            'kebab_score' => $result->score,
            'score_breakdown' => $result->toArray(),
        ])->save();

        return $restaurant;
    }

    /**
     * Bayesian shrinkage toward the neutral prior, expressed as 0-100.
     */
    private function shrinkRating(float $rating, int $reviews, float $prior, int $priorWeight): float
    {
        $weighted = (($rating * $reviews) + ($prior * $priorWeight)) / max(1, $reviews + $priorWeight);

        return $this->ratingToPercentage($weighted);
    }

    private function ratingToPercentage(float $rating): float
    {
        $floor = (float) config('kebab.scoring.rating_floor');
        $span = max(0.1, self::MAX_STAR_RATING - $floor);

        return max(0.0, min(100.0, (($rating - $floor) / $span) * 100));
    }

    private function clampAdjustment(int $adjustment): int
    {
        $limit = (int) config('kebab.scoring.editorial_adjustment_limit');

        return max(-$limit, min($limit, $adjustment));
    }

    /**
     * @return array{key: string, label: string, weight: float, value: float, points: float, detail: string}
     */
    private function component(string $key, string $label, float $weight, float $value, string $detail): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'weight' => round($weight, 4),
            'value' => round($value, 1),
            'points' => round($value * $weight, 2),
            'detail' => $detail,
        ];
    }
}
