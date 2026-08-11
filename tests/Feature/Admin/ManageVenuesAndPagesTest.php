<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Page;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ManageVenuesAndPagesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        /** @var User $user */
        $user = User::factory()->create(['is_admin' => true]);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function venuePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Enmore Theatre',
            'slug' => 'enmore-theatre',
            'suburb' => 'Newtown',
            'address' => '118 Enmore Road, Newtown NSW 2042',
            'description' => 'A Sydney live institution.',
            'body' => '<p>Heritage room, relentless calendar.</p>',
            'image' => null,
            'website' => 'https://example.com',
            'social_url' => null,
            'phone' => null,
            'transport' => 'Ten minutes from Newtown Station.',
            'latitude' => -33.9004,
            'longitude' => 151.1736,
            'status' => 'published',
            'featured' => false,
            'meta_title' => null,
            'meta_description' => null,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function pagePayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'What to do in Sydney this weekend',
            'slug' => 'what-to-do-in-sydney-this-weekend',
            'type' => 'guide',
            'excerpt' => 'Twelve sharp picks.',
            'body' => '<h2>Friday</h2><p>Start in Newtown.</p>',
            'image' => null,
            'status' => 'published',
            'published_at' => null,
            'featured' => false,
            'sort_order' => 0,
            'meta_title' => null,
            'meta_description' => null,
        ], $overrides);
    }

    #[Test]
    public function an_administrator_can_create_and_delete_a_venue(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/venues', $this->venuePayload())
            ->assertRedirect('/admin/venues/enmore-theatre/edit');

        $this->assertDatabaseHas('venues', ['slug' => 'enmore-theatre', 'suburb' => 'Newtown']);

        $this->actingAs($this->admin())
            ->delete('/admin/venues/enmore-theatre')
            ->assertRedirect('/admin/venues');

        $this->assertDatabaseMissing('venues', ['slug' => 'enmore-theatre']);
    }

    #[Test]
    public function the_venue_index_counts_events(): void
    {
        Venue::factory()->create(['name' => 'Factory Theatre']);

        $this->actingAs($this->admin())
            ->get('/admin/venues')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Venues/Index')
                ->where('venues.data.0.events_count', 0));
    }

    #[Test]
    public function an_administrator_can_write_a_guide(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/pages', $this->pagePayload())
            ->assertRedirect('/admin/pages/what-to-do-in-sydney-this-weekend/edit');

        $page = Page::query()->firstWhere('slug', 'what-to-do-in-sydney-this-weekend');

        $this->assertNotNull($page);
        $this->assertNotNull($page->published_at, 'Publishing without a date should stamp one.');
        $this->assertStringContainsString('<h2>Friday</h2>', (string) $page->body);
    }

    #[Test]
    public function a_standalone_page_cannot_take_a_reserved_slug(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/pages', $this->pagePayload(['type' => 'page', 'slug' => 'events']))
            ->assertSessionHasErrors('slug');
    }

    #[Test]
    public function a_visitor_cannot_manage_venues_or_pages(): void
    {
        $this->get('/admin/venues')->assertRedirect('/admin/login');
        $this->get('/admin/pages')->assertRedirect('/admin/login');
    }
}
