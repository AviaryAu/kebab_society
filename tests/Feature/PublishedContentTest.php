<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Page;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PublishedContentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_events_index_only_shows_published_events(): void
    {
        Event::factory()->create(['title' => 'Published Gig']);
        Event::factory()->draft()->create(['title' => 'Secret Gig']);

        $this->get('/events')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Live/EventsIndex')
                ->has('events', 1)
                ->where('events.0.title', 'Published Gig'));
    }

    #[Test]
    public function a_draft_event_is_not_reachable(): void
    {
        $event = Event::factory()->draft()->create(['slug' => 'secret-gig']);

        $this->get("/events/{$event->slug}")->assertNotFound();
    }

    #[Test]
    public function a_venue_page_lists_its_events(): void
    {
        $venue = Venue::factory()->create(['slug' => 'enmore-theatre']);
        Event::factory()->create(['venue_id' => $venue->id, 'title' => 'Late Set Fridays']);

        $this->get('/venues/enmore-theatre')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Live/VenueShow')
                ->has('events', 1)
                ->where('events.0.title', 'Late Set Fridays'));
    }

    #[Test]
    public function a_published_guide_renders_its_body(): void
    {
        Page::factory()->create(['slug' => 'best-live-music', 'body' => '<p>Start in Newtown.</p>']);

        $this->get('/guides/best-live-music')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Live/PageShow')
                ->where('page.body', '<p>Start in Newtown.</p>'));
    }

    #[Test]
    public function a_draft_page_is_not_reachable(): void
    {
        Page::factory()->draft()->standalone()->create(['slug' => 'about-us']);

        $this->get('/about-us')->assertNotFound();
    }

    #[Test]
    public function a_published_standalone_page_sits_at_the_top_level(): void
    {
        Page::factory()->standalone()->create(['slug' => 'about-us', 'title' => 'About us']);

        $this->get('/about-us')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Live/PageShow')
                ->where('page.title', 'About us'));
    }
}
