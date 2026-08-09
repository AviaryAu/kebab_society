<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RestaurantStatus;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RestaurantPageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_shows_a_restaurant_and_explains_its_score(): void
    {
        $restaurant = Restaurant::factory()->societyApproved()->create([
            'name' => 'Anatolia Charcoal Kebabs',
            'slug' => 'anatolia-charcoal-kebabs',
            'rating_breakdown' => ['rating' => 4.6, 'components' => [], 'editorial_adjustment' => 0.2],
        ]);

        $this->get("/kebabs/{$restaurant->slug}")->assertOk()->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Restaurants/Show')
                ->where('restaurant.name', 'Anatolia Charcoal Kebabs')
                ->where('restaurant.society_approved', true)
                ->has('restaurant.rating_breakdown')
                ->has('restaurant.weekly_hours', 7)
        );
    }

    #[Test]
    public function it_distinguishes_the_google_rating_from_the_society_rating(): void
    {
        $restaurant = Restaurant::factory()->create([
            'google_rating' => 4.4,
            'kebab_rating' => 3.7,
        ]);

        $this->get("/kebabs/{$restaurant->slug}")->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('restaurant.google_rating', 4.4)
                ->where('restaurant.kebab_rating', 3.7)
                ->where('restaurant.tier.label', 'GOOD')
        );
    }

    #[Test]
    public function it_offers_nearby_alternatives_without_repeating_itself(): void
    {
        $restaurant = Restaurant::factory()->create(['latitude' => -33.9, 'longitude' => 151.15]);
        Restaurant::factory()->count(3)->create(['latitude' => -33.91, 'longitude' => 151.16]);

        $this->get("/kebabs/{$restaurant->slug}")->assertInertia(function (AssertableInertia $page) use ($restaurant) {
            $page->has('nearby', 3);

            foreach ($page->toArray()['props']['nearby'] as $option) {
                $this->assertNotSame($restaurant->id, $option['id']);
            }
        });
    }

    #[Test]
    public function a_permanently_closed_restaurant_is_not_published(): void
    {
        $restaurant = Restaurant::factory()->create(['status' => RestaurantStatus::PermanentlyClosed]);

        $this->get("/kebabs/{$restaurant->slug}")->assertNotFound();
    }

    #[Test]
    public function an_unknown_restaurant_returns_not_found(): void
    {
        $this->get('/kebabs/a-kebab-that-never-was')->assertNotFound();
    }
}
