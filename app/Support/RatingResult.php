<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The outcome of a Kebab Society Rating calculation.
 *
 * Every rating must be explainable, so the signals that produced it travel with
 * the number itself.
 *
 * @phpstan-type RatingComponent array{key: string, label: string, weight: float, rating: float, detail: string}
 */
final readonly class RatingResult
{
    /**
     * @param  array<int, RatingComponent>  $components
     */
    public function __construct(
        public ?float $rating,
        public array $components,
        public float $editorialAdjustment,
        public float $confidence,
    ) {}

    public function tier(): RatingTier
    {
        return RatingTier::forRating($this->rating);
    }

    public function confidenceLabel(): string
    {
        return match (true) {
            $this->rating === null => 'No rating held',
            $this->confidence >= 1.0 => 'Thoroughly reviewed',
            $this->confidence >= 0.5 => 'Reasonably well reviewed',
            $this->confidence > 0.0 => 'Still gathering evidence',
            default => 'No reviews held',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'rating' => $this->rating,
            'components' => $this->components,
            'editorial_adjustment' => $this->editorialAdjustment,
            'confidence' => round($this->confidence, 2),
            'confidence_label' => $this->confidenceLabel(),
            'tier' => $this->tier()->key,
        ];
    }
}
