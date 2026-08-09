<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * A band of the Kebab Meter. Tiers are defined in config/kebab.php so the
 * Society's copywriting can change without touching application code.
 */
final readonly class ScoreTier
{
    private function __construct(
        public string $key,
        public int $min,
        public int $max,
        public string $label,
        public string $verdict,
        public string $marker,
        public string $colour,
    ) {}

    /**
     * @return array<int, self>
     */
    public static function all(): array
    {
        static $tiers = null;

        return $tiers ??= array_map(
            static fn (array $tier): self => new self(
                key: $tier['key'],
                min: $tier['min'],
                max: $tier['max'],
                label: $tier['label'],
                verdict: $tier['verdict'],
                marker: $tier['marker'],
                colour: $tier['colour'],
            ),
            config('kebab.tiers'),
        );
    }

    public static function forScore(?int $score): self
    {
        $tiers = self::all();

        if ($tiers === []) {
            throw new InvalidArgumentException('No Kebab Meter tiers are configured.');
        }

        $score = max(0, min(100, $score ?? 0));

        foreach ($tiers as $tier) {
            if ($score >= $tier->min && $score <= $tier->max) {
                return $tier;
            }
        }

        return $tiers[array_key_last($tiers)];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'min' => $this->min,
            'max' => $this->max,
            'label' => $this->label,
            'verdict' => $this->verdict,
            'marker' => $this->marker,
            'colour' => $this->colour,
        ];
    }
}
