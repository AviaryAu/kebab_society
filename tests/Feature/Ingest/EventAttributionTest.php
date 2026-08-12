<?php

declare(strict_types=1);

namespace Tests\Feature\Ingest;

use App\Models\Event;
use App\Models\IngestSource;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Attribution is the consideration we offer for the facts we take, so it has
 * to actually reach the reader rather than merely being stored.
 */
class EventAttributionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function an_ingested_event_credits_and_links_its_source(): void
    {
        $event = $this->event([
            'source_name' => 'Concrete Playground',
            'source_attribution_url' => 'https://concreteplayground.com/sydney/gig',
        ]);

        $this->get("/events/{$event->slug}")
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->where('event.attribution.name', 'Concrete Playground')
                    ->where('event.attribution.url', 'https://concreteplayground.com/sydney/gig'),
            );
    }

    #[Test]
    public function an_event_written_in_house_credits_nobody(): void
    {
        $event = $this->event([
            'ingest_source_id' => null,
            'source_name' => null,
            'source_attribution_url' => null,
        ]);

        $this->get("/events/{$event->slug}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('event.attribution', null));
    }

    #[Test]
    public function attribution_falls_back_to_the_source_url(): void
    {
        $event = $this->event([
            'source_name' => 'Moshtix',
            'source_attribution_url' => null,
            'source_url' => 'https://moshtix.com.au/v2/event/123',
        ]);

        $this->assertSame(
            ['name' => 'Moshtix', 'url' => 'https://moshtix.com.au/v2/event/123'],
            $event->attribution(),
        );
    }

    #[Test]
    public function an_image_credit_travels_with_the_event(): void
    {
        $event = $this->event(['image' => '/media/gig.webp', 'image_credit' => 'Ticketmaster']);

        $this->get("/events/{$event->slug}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('event.image_credit', 'Ticketmaster'));
    }

    private function event(array $overrides = []): Event
    {
        $venue = Venue::factory()->create(['name' => 'Enmore Theatre', 'status' => 'published']);

        return Event::factory()->create(array_merge([
            'venue_id' => $venue->id,
            'status' => 'published',
            'start_datetime' => now()->addDays(3)->setTime(20, 0),
            'ingest_source_id' => IngestSource::factory()->create()->id,
        ], $overrides));
    }
}
