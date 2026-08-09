<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The outcome of a Kebab Society Score calculation.
 *
 * Every score must be explainable, so the components that produced it travel
 * with the number itself.
 *
 * @phpstan-type ScoreComponent array{key: string, label: string, weight: float, value: float, points: float, detail: string}
 */
final readonly class ScoreResult
{
    /**
     * @param  array<int, ScoreComponent>  $components
     */
    public function __construct(
        public int $score,
        public array $components,
        public int $editorialAdjustment,
    ) {}

    public function tier(): ScoreTier
    {
        return ScoreTier::forScore($this->score);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'components' => $this->components,
            'editorial_adjustment' => $this->editorialAdjustment,
            'tier' => $this->tier()->key,
        ];
    }
}
