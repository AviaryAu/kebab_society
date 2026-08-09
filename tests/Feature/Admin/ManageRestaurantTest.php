<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\KebabStyle;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ManageRestaurantTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        /** @var User $user */
        $user = User::factory()->create(['is_admin' => true]);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Restaurant $restaurant, array $overrides = []): array
    {
        return array_merge([
            'name' => $restaurant->name,
            'slug' => $restaurant->slug,
            'description' => 'A serious kebab.',
            'address_line' => $restaurant->address_line,
            'suburb_id' => $restaurant->suburb_id,
            'postcode' => $restaurant->postcode,
            'latitude' => $restaurant->latitude,
            'longitude' => $restaurant->longitude,
            'phone' => '(02) 9000 0000',
            'website' => null,
            'google_place_id' => null,
            'google_rating' => 4.4,
            'google_review_count' => 800,
            'price_level' => 2,
            'status' => 'published',
            'verification_status' => 'verified',
            'society_approved' => false,
            'editorial_adjustment' => 0,
            'editorial_note' => null,
            'styles' => [],
            'opening_hours' => [],
        ], $overrides);
    }

    #[Test]
    public function an_administrator_can_open_the_edit_view(): void
    {
        $restaurant = Restaurant::factory()->create(['name' => 'Haldon Street Charcoal']);

        $this->actingAs($this->admin())
            ->get("/admin/restaurants/{$restaurant->slug}/edit")
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->component('Admin/Restaurants/Edit')
                    ->where('restaurant.name', 'Haldon Street Charcoal')
                    ->has('options.suburbs')
                    ->has('options.styles')
            );
    }

    #[Test]
    public function saving_recalculates_the_published_rating_from_the_evidence(): void
    {
        $restaurant = Restaurant::factory()->create(['kebab_rating' => 2.0, 'google_rating' => 2.0]);

        $this->actingAs($this->admin())
            ->put("/admin/restaurants/{$restaurant->slug}", $this->payload($restaurant, [
                'google_rating' => 4.8,
                'google_review_count' => 2000,
            ]))
            ->assertRedirect();

        $restaurant->refresh();

        $this->assertEqualsWithDelta(4.8, (float) $restaurant->kebab_rating, 0.1);
        $this->assertNotNull($restaurant->rating_breakdown);
    }

    #[Test]
    public function the_rating_cannot_be_set_directly(): void
    {
        $restaurant = Restaurant::factory()->create(['google_rating' => 3.0, 'google_review_count' => 500]);

        $this->actingAs($this->admin())
            ->put("/admin/restaurants/{$restaurant->slug}", $this->payload($restaurant, [
                'google_rating' => 3.0,
                'google_review_count' => 500,
                'kebab_rating' => 5.0,
            ]));

        $restaurant->refresh();

        $this->assertLessThan(4.0, (float) $restaurant->kebab_rating);
    }

    #[Test]
    public function an_editorial_adjustment_beyond_the_limit_is_rejected(): void
    {
        $restaurant = Restaurant::factory()->create();

        $this->actingAs($this->admin())
            ->put("/admin/restaurants/{$restaurant->slug}", $this->payload($restaurant, [
                'editorial_adjustment' => 3,
            ]))
            ->assertSessionHasErrors('editorial_adjustment');
    }

    #[Test]
    public function certification_and_styles_can_be_managed(): void
    {
        $restaurant = Restaurant::factory()->create();
        $hsp = KebabStyle::factory()->create(['slug' => 'hsp']);

        $this->actingAs($this->admin())
            ->put("/admin/restaurants/{$restaurant->slug}", $this->payload($restaurant, [
                'society_approved' => true,
                'styles' => [$hsp->id],
            ]));

        $restaurant->refresh();

        $this->assertTrue($restaurant->isSocietyApproved());
        $this->assertSame(['hsp'], $restaurant->kebabStyles->pluck('slug')->all());
    }

    #[Test]
    public function trading_hours_can_be_recorded(): void
    {
        $restaurant = Restaurant::factory()->closed()->create();

        $this->actingAs($this->admin())
            ->put("/admin/restaurants/{$restaurant->slug}", $this->payload($restaurant, [
                'opening_hours' => [
                    'fri' => [['open' => '11:00', 'close' => '03:00']],
                    'sat' => [['open' => '11:00', 'close' => '03:00']],
                    'sun' => [],
                ],
            ]))
            ->assertSessionHasNoErrors();

        $restaurant->refresh();

        $this->assertTrue($restaurant->tradesLateNight());
        $this->assertArrayNotHasKey('sun', $restaurant->hours()->toArray());
    }

    #[Test]
    public function a_photograph_is_stored_and_resized_into_every_format(): void
    {
        Storage::fake('public');
        config()->set('kebab.photos.disk', 'public');

        $restaurant = Restaurant::factory()->create();

        $this->actingAs($this->admin())
            ->post("/admin/restaurants/{$restaurant->slug}/photos", [
                'photos' => [UploadedFile::fake()->image('kebab.jpg', 2400, 1600)],
            ])
            ->assertRedirect();

        $photo = $restaurant->photos()->first();

        $this->assertNotNull($photo);
        $this->assertTrue($photo->is_primary);
        $this->assertSame(['hero', 'card', 'thumb'], array_keys($photo->renditions));

        foreach ($photo->renditions as $path) {
            Storage::disk('public')->assertExists($path);
            $this->assertStringEndsWith('.webp', $path);
        }
    }

    #[Test]
    public function deleting_a_photograph_removes_its_files_and_promotes_another(): void
    {
        Storage::fake('public');
        config()->set('kebab.photos.disk', 'public');

        $restaurant = Restaurant::factory()->create();

        $this->actingAs($this->admin())->post("/admin/restaurants/{$restaurant->slug}/photos", [
            'photos' => [
                UploadedFile::fake()->image('one.jpg', 1200, 800),
                UploadedFile::fake()->image('two.jpg', 1200, 800),
            ],
        ]);

        $lead = $restaurant->photos()->where('is_primary', true)->firstOrFail();
        $paths = array_values($lead->renditions);

        $this->actingAs($this->admin())->delete("/admin/photos/{$lead->id}")->assertRedirect();

        foreach ($paths as $path) {
            Storage::disk('public')->assertMissing($path);
        }

        $this->assertSame(1, $restaurant->photos()->count());
        $this->assertTrue($restaurant->photos()->first()->is_primary);
    }

    #[Test]
    public function a_non_image_upload_is_rejected(): void
    {
        Storage::fake('public');
        $restaurant = Restaurant::factory()->create();

        $this->actingAs($this->admin())
            ->post("/admin/restaurants/{$restaurant->slug}/photos", [
                'photos' => [UploadedFile::fake()->create('menu.pdf', 100, 'application/pdf')],
            ])
            ->assertSessionHasErrors('photos.0');

        $this->assertSame(0, $restaurant->photos()->count());
    }

    #[Test]
    public function a_guest_cannot_upload_photographs(): void
    {
        $restaurant = Restaurant::factory()->create();

        $this->post("/admin/restaurants/{$restaurant->slug}/photos", [
            'photos' => [UploadedFile::fake()->image('kebab.jpg')],
        ])->assertRedirect('/admin/login');
    }
}
