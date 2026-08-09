<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\RatingTier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RatingTierTest extends TestCase
{
    /**
     * @return array<string, array{0: float, 1: string}>
     */
    public static function ratings(): array
    {
        return [
            'floor' => [0.0, 'questionable'],
            'poor' => [2.9, 'questionable'],
            'acceptable lower bound' => [3.0, 'average'],
            'acceptable upper bound' => [3.4, 'average'],
            'good' => [3.7, 'good'],
            'excellent' => [4.2, 'excellent'],
            'legendary lower bound' => [4.5, 'legendary'],
            'ceiling' => [5.0, 'legendary'],
        ];
    }

    #[Test]
    #[DataProvider('ratings')]
    public function it_maps_ratings_to_the_correct_tier(float $rating, string $expected): void
    {
        $this->assertSame($expected, RatingTier::forRating($rating)->key);
    }

    #[Test]
    public function an_unrated_restaurant_gets_its_own_tier(): void
    {
        $tier = RatingTier::forRating(null);

        $this->assertSame('unrated', $tier->key);
        $this->assertSame(0, $tier->stars);
        $this->assertSame('unrated', $tier->marker);
    }

    #[Test]
    public function out_of_range_ratings_are_clamped(): void
    {
        $this->assertSame('questionable', RatingTier::forRating(-2.0)->key);
        $this->assertSame('legendary', RatingTier::forRating(9.9)->key);
    }

    #[Test]
    public function tiers_cover_the_whole_meter_without_gaps_or_overlaps(): void
    {
        $seen = [];

        // Ratings are published to one decimal place.
        foreach (range(0, 50) as $step) {
            $rating = $step / 10;
            $key = RatingTier::forRating($rating)->key;

            $this->assertNotSame('unrated', $key, "Rating {$rating} fell outside every tier.");
            $seen[$key] = true;
        }

        $this->assertCount(count(RatingTier::all()), $seen);
    }

    #[Test]
    public function each_tier_names_a_marker_asset_that_exists(): void
    {
        foreach (RatingTier::allIncludingUnrated() as $tier) {
            $this->assertFileExists(public_path("images/markers/marker-{$tier->marker}.png"));
        }
    }

    #[Test]
    public function star_counts_ascend_with_the_bands(): void
    {
        $stars = array_map(fn (RatingTier $tier): int => $tier->stars, RatingTier::all());

        $sorted = $stars;
        sort($sorted);

        $this->assertSame($sorted, $stars);
        $this->assertSame([1, 2, 3, 4, 5], $stars);
    }
}
