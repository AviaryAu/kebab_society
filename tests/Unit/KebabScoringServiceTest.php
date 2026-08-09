<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Restaurant;
use App\Services\KebabScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class KebabScoringServiceTest extends TestCase
{
    use RefreshDatabase;

    private KebabScoringService $scoring;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scoring = app(KebabScoringService::class);
    }

    #[Test]
    public function it_publishes_a_rating_out_of_five(): void
    {
        $restaurant = Restaurant::factory()->make([
            'google_rating' => 5.0,
            'google_review_count' => 10000,
            'society_rating' => 5.0,
            'society_review_count' => 500,
            'editorial_adjustment' => 0.3,
        ]);

        $result = $this->scoring->rate($restaurant);

        $this->assertGreaterThanOrEqual(0.0, $result->rating);
        $this->assertLessThanOrEqual(5.0, $result->rating);
    }

    #[Test]
    public function a_restaurant_with_no_ratings_stays_unrated(): void
    {
        $restaurant = Restaurant::factory()->make([
            'google_rating' => null,
            'google_review_count' => null,
            'society_rating' => null,
            'society_review_count' => 0,
        ]);

        $result = $this->scoring->rate($restaurant);

        $this->assertNull($result->rating);
        $this->assertSame([], $result->components);
        $this->assertSame('unrated', $result->tier()->key);
    }

    #[Test]
    public function a_better_rated_kebab_rates_higher(): void
    {
        $good = Restaurant::factory()->make(['google_rating' => 4.8, 'google_review_count' => 1000]);
        $poor = Restaurant::factory()->make(['google_rating' => 3.2, 'google_review_count' => 1000]);

        $this->assertGreaterThan($this->scoring->rate($poor)->rating, $this->scoring->rate($good)->rating);
    }

    #[Test]
    public function a_well_reviewed_rating_stays_close_to_its_source(): void
    {
        $restaurant = Restaurant::factory()->make([
            'google_rating' => 4.7,
            'google_review_count' => 2190,
            'society_review_count' => 0,
        ]);

        $this->assertEqualsWithDelta(4.7, $this->scoring->rate($restaurant)->rating, 0.1);
    }

    #[Test]
    public function a_single_glowing_review_cannot_mint_a_legendary_kebab(): void
    {
        $restaurant = Restaurant::factory()->make([
            'google_rating' => 5.0,
            'google_review_count' => 1,
            'society_rating' => 5.0,
            'society_review_count' => 1,
            'editorial_adjustment' => 0,
        ]);

        $this->assertLessThan(4.5, $this->scoring->rate($restaurant)->rating);
    }

    #[Test]
    public function the_rating_is_explainable(): void
    {
        $restaurant = Restaurant::factory()->make([
            'google_rating' => 4.5,
            'google_review_count' => 400,
            'society_rating' => 4.6,
            'society_review_count' => 40,
        ]);

        $result = $this->scoring->rate($restaurant);
        $keys = array_column($result->components, 'key');

        $this->assertSame(['society_rating', 'google_rating'], $keys);
        $this->assertEqualsWithDelta(1.0, array_sum(array_column($result->components, 'weight')), 0.0001);

        foreach ($result->components as $component) {
            $this->assertNotSame('', $component['detail']);
        }
    }

    #[Test]
    public function google_carries_the_whole_weight_until_the_society_has_reviewed(): void
    {
        $restaurant = Restaurant::factory()->make([
            'google_rating' => 4.5,
            'google_review_count' => 400,
            'society_rating' => null,
            'society_review_count' => 0,
        ]);

        $components = $this->scoring->rate($restaurant)->components;

        $this->assertCount(1, $components);
        $this->assertSame('google_rating', $components[0]['key']);
        $this->assertEqualsWithDelta(1.0, $components[0]['weight'], 0.0001);
    }

    #[Test]
    public function editorial_adjustments_are_clamped_to_the_configured_limit(): void
    {
        config()->set('kebab.rating.editorial_adjustment_limit', 0.3);

        $restaurant = Restaurant::factory()->make([
            'google_rating' => 4.0,
            'google_review_count' => 300,
            'editorial_adjustment' => 4.0,
        ]);

        $this->assertSame(0.3, $this->scoring->rate($restaurant)->editorialAdjustment);
    }

    #[Test]
    public function applying_the_rating_persists_the_number_and_its_breakdown(): void
    {
        $restaurant = Restaurant::factory()->create([
            'google_rating' => 4.4,
            'google_review_count' => 800,
            'kebab_rating' => null,
            'rating_breakdown' => null,
        ]);

        $this->scoring->apply($restaurant);
        $restaurant->refresh();

        $this->assertNotNull($restaurant->kebab_rating);
        $this->assertIsArray($restaurant->rating_breakdown);
        $this->assertArrayHasKey('components', $restaurant->rating_breakdown);
        $this->assertEqualsWithDelta(
            (float) $restaurant->kebab_rating,
            $restaurant->rating_breakdown['rating'],
            0.001,
        );
    }
}
