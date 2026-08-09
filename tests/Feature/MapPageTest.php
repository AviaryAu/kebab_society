<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RestaurantStatus;
use App\Models\KebabStyle;
use App\Models\Restaurant;
use App\Models\Suburb;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MapPageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_map_lists_discoverable_restaurants(): void
    {
        Restaurant::factory()->create(['name' => 'Open Shop']);
        Restaurant::factory()->create([
            'name' => 'Gone Shop',
            'status' => RestaurantStatus::PermanentlyClosed,
        ]);

        $this->get('/')->assertOk()->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Map/Index')
                ->has('restaurants', 1)
                ->where('restaurants.0.name', 'Open Shop')
        );
    }

    #[Test]
    public function it_filters_by_suburb(): void
    {
        $lakemba = Suburb::factory()->create(['name' => 'Lakemba', 'slug' => 'lakemba']);
        Restaurant::factory()->create(['suburb_id' => $lakemba->id, 'name' => 'Haldon Street']);
        Restaurant::factory()->create(['name' => 'Somewhere Else']);

        $this->get('/?suburb=lakemba')->assertOk()->assertInertia(
            fn (AssertableInertia $page) => $page->has('restaurants', 1)->where('restaurants.0.name', 'Haldon Street')
        );
    }

    #[Test]
    public function it_searches_by_name_suburb_and_postcode(): void
    {
        $suburb = Suburb::factory()->create(['name' => 'Marrickville']);
        Restaurant::factory()->create(['suburb_id' => $suburb->id, 'name' => 'Anatolia Charcoal', 'postcode' => '2204']);
        Restaurant::factory()->create(['name' => 'Unrelated Kebabs', 'postcode' => '2000']);

        $this->get('/?search=Marrickville')->assertInertia(fn (AssertableInertia $page) => $page->has('restaurants', 1));
        $this->get('/?search=Anatolia')->assertInertia(fn (AssertableInertia $page) => $page->has('restaurants', 1));
        $this->get('/?search=2204')->assertInertia(fn (AssertableInertia $page) => $page->has('restaurants', 1));
    }

    #[Test]
    public function it_filters_by_late_night_trading(): void
    {
        Restaurant::factory()->lateNight()->create(['name' => 'The Night Shift']);
        Restaurant::factory()->create(['name' => 'Closes Early']);

        $this->get('/?late_night=1')->assertInertia(
            fn (AssertableInertia $page) => $page->has('restaurants', 1)->where('restaurants.0.name', 'The Night Shift')
        );
    }

    #[Test]
    public function it_filters_by_society_certification_and_kebab_style(): void
    {
        $hsp = KebabStyle::factory()->create(['slug' => 'hsp', 'name' => 'HSP']);

        $certified = Restaurant::factory()->societyApproved()->create(['name' => 'Certified HSP']);
        $certified->kebabStyles()->attach($hsp);

        Restaurant::factory()->create(['name' => 'Plain Shop']);

        $this->get('/?society_certified=1')->assertInertia(fn (AssertableInertia $page) => $page->has('restaurants', 1));
        $this->get('/?styles[0]=hsp')->assertInertia(
            fn (AssertableInertia $page) => $page->has('restaurants', 1)->where('restaurants.0.name', 'Certified HSP')
        );
    }

    #[Test]
    public function boolean_filters_written_as_text_do_not_bounce_the_visitor(): void
    {
        Restaurant::factory()->societyApproved()->create();

        $this->get('/?society_certified=true')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('restaurants', 1));
    }

    #[Test]
    public function an_out_of_range_rating_filter_is_rejected(): void
    {
        $this->get('/?min_rating=50')->assertRedirect();
    }
}
