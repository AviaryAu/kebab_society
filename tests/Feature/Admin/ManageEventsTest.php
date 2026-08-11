<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Event;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ManageEventsTest extends TestCase
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
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Late Set Fridays',
            'slug' => 'late-set-fridays',
            'description' => 'A stacked local lineup.',
            'body' => '<p>Doors at seven.</p>',
            'start_datetime' => '2026-09-04T19:30',
            'end_datetime' => '2026-09-04T23:45',
            'venue_id' => null,
            'suburb' => 'Newtown',
            'category_slug' => 'music',
            'image' => null,
            'price' => '$38',
            'ticket_url' => 'https://example.com/tickets',
            'latitude' => -33.9004,
            'longitude' => 151.1736,
            'featured' => true,
            'status' => 'published',
            'meta_title' => null,
            'meta_description' => null,
        ], $overrides);
    }

    #[Test]
    public function the_index_lists_events(): void
    {
        Event::factory()->create(['title' => 'Rooftop Jazz']);

        $this->actingAs($this->admin())
            ->get('/admin/events')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Events/Index')
                ->has('events.data', 1)
                ->where('events.data.0.title', 'Rooftop Jazz'));
    }

    #[Test]
    public function an_administrator_can_create_an_event(): void
    {
        $venue = Venue::factory()->create();

        $this->actingAs($this->admin())
            ->post('/admin/events', $this->payload(['venue_id' => $venue->id]))
            ->assertRedirect('/admin/events/late-set-fridays/edit');

        $event = Event::query()->firstWhere('slug', 'late-set-fridays');

        $this->assertNotNull($event);
        $this->assertSame($venue->id, $event->venue_id);
        $this->assertTrue($event->featured);
        $this->assertSame('<p>Doors at seven.</p>', $event->body);
    }

    #[Test]
    public function an_administrator_can_update_and_delete_an_event(): void
    {
        $event = Event::factory()->create(['slug' => 'night-shift']);

        $this->actingAs($this->admin())
            ->put("/admin/events/{$event->slug}", $this->payload([
                'title' => 'Night Shift',
                'slug' => 'night-shift',
            ]))
            ->assertRedirect('/admin/events/night-shift/edit');

        $this->assertSame('Night Shift', $event->refresh()->title);

        $this->actingAs($this->admin())
            ->delete('/admin/events/night-shift')
            ->assertRedirect('/admin/events');

        $this->assertDatabaseMissing('events', ['slug' => 'night-shift']);
    }

    #[Test]
    public function editor_html_is_sanitised_before_it_is_stored(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/events', $this->payload([
                'body' => '<p onclick="steal()">Hello</p><script>alert(1)</script><a href="javascript:alert(1)">x</a>',
            ]))
            ->assertRedirect();

        $body = Event::query()->firstWhere('slug', 'late-set-fridays')?->body;

        $this->assertStringNotContainsString('<script', (string) $body);
        $this->assertStringNotContainsString('onclick', (string) $body);
        $this->assertStringNotContainsString('javascript:', (string) $body);
        $this->assertStringContainsString('Hello', (string) $body);
    }

    #[Test]
    public function an_unknown_category_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/events', $this->payload(['category_slug' => 'interpretive-dance']))
            ->assertSessionHasErrors('category_slug');
    }

    #[Test]
    public function a_visitor_cannot_manage_events(): void
    {
        $this->get('/admin/events')->assertRedirect('/admin/login');
        $this->post('/admin/events', $this->payload())->assertRedirect('/admin/login');
    }
}
