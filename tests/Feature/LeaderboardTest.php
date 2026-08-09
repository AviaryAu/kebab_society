<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\KebabStyle;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LeaderboardTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_default_board_ranks_by_kebab_rating(): void
    {
        Restaurant::factory()->create(['name' => 'Middle', 'kebab_rating' => 3.6]);
        Restaurant::factory()->create(['name' => 'Best', 'kebab_rating' => 4.8]);
        Restaurant::factory()->create(['name' => 'Worst', 'kebab_rating' => 2.4]);

        $this->get('/leaderboard')->assertOk()->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('Leaderboard/Show')
                ->where('board.key', 'best-kebab')
                ->where('entries.0.rank', 1)
                ->where('entries.0.restaurant.name', 'Best')
                ->where('entries.2.restaurant.name', 'Worst')
        );
    }

    #[Test]
    public function the_hsp_board_only_includes_shops_serving_hsp(): void
    {
        $hsp = KebabStyle::factory()->create(['slug' => 'hsp', 'name' => 'HSP']);

        $withHsp = Restaurant::factory()->create(['name' => 'Snack Pack House', 'kebab_rating' => 4.0]);
        $withHsp->kebabStyles()->attach($hsp);

        Restaurant::factory()->create(['name' => 'Wraps Only', 'kebab_rating' => 4.6]);

        $this->get('/leaderboard/best-hsp')->assertInertia(
            fn (AssertableInertia $page) => $page
                ->has('entries', 1)
                ->where('entries.0.restaurant.name', 'Snack Pack House')
        );
    }

    #[Test]
    public function the_late_night_board_only_includes_late_traders(): void
    {
        Restaurant::factory()->lateNight()->create(['name' => 'Three AM Kebabs', 'kebab_rating' => 3.0]);
        Restaurant::factory()->create(['name' => 'Shuts At Ten', 'kebab_rating' => 5.0]);

        $this->get('/leaderboard/best-late-night')->assertInertia(
            fn (AssertableInertia $page) => $page
                ->has('entries', 1)
                ->where('entries.0.restaurant.name', 'Three AM Kebabs')
        );
    }

    #[Test]
    public function unrated_restaurants_are_excluded(): void
    {
        Restaurant::factory()->create(['kebab_rating' => null]);

        $this->get('/leaderboard')->assertInertia(fn (AssertableInertia $page) => $page->has('entries', 0));
    }

    #[Test]
    public function an_unknown_board_returns_not_found(): void
    {
        $this->get('/leaderboard/best-lasagne')->assertNotFound();
    }
}
