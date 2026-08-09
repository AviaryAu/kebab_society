<?php

declare(strict_types=1);

namespace App\Support;

/**
 * A band of the Kebab Meter, expressed in stars.
 *
 * Tiers are defined in config/kebab.php so the Society's copywriting and its
 * marker artwork can change without touching application code.
 */
final readonly class RatingTier
{
    private function __construct(
        public string $key,
        public float $min,
        public float $max,
        public int $stars,
        public string $label,
        public string $verdict,
        public string $marker,
        public string $colour,
    ) {}

    /**
     * @param  array<string, mixed>  $tier
     */
    private static function fromConfig(array $tier): self
    {
        return new self(
            key: $tier['key'],
            min: (float) $tier['min'],
            max: (float) $tier['max'],
            stars: (int) $tier['stars'],
            label: $tier['label'],
            verdict: $tier['verdict'],
            marker: $tier['marker'],
            colour: $tier['colour'],
        );
    }

    /**
     * The rated bands, lowest first.
     *
     * @return array<int, self>
     */
    public static function all(): array
    {
        return array_map(self::fromConfig(...), config('kebab.tiers'));
    }

    public static function unrated(): self
    {
        return self::fromConfig(config('kebab.unrated_tier'));
    }

    /**
     * @return array<int, self>
     */
    public static function allIncludingUnrated(): array
    {
        return [self::unrated(), ...self::all()];
    }

    public static function forRating(?float $rating): self
    {
        if ($rating === null) {
            return self::unrated();
        }

        $rating = max(0.0, min(5.0, $rating));

        foreach (self::all() as $tier) {
            if ($rating >= $tier->min && $rating <= $tier->max) {
                return $tier;
            }
        }

        return self::unrated();
    }

    public function markerUrl(): string
    {
        return asset("images/markers/marker-{$this->marker}.png");
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
            'stars' => $this->stars,
            'label' => $this->label,
            'verdict' => $this->verdict,
            'marker' => $this->marker,
            'colour' => $this->colour,
        ];
    }
}
