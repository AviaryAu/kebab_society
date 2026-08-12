<?php

declare(strict_types=1);

namespace Tests\Feature\Ingest;

use App\Models\IngestSource;
use App\Services\Ingest\Adapters\JsonLdAdapter;
use App\Services\Ingest\EventDraft;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JsonLdAdapterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ingest.http.min_interval_ms', 0);
    }

    #[Test]
    public function it_reads_an_event_from_embedded_structured_data(): void
    {
        $draft = $this->normalise($this->event());

        $this->assertInstanceOf(EventDraft::class, $draft);
        $this->assertSame('Sunday Session', $draft->title);
        $this->assertSame('Lansdowne Hotel', $draft->venueName);
        $this->assertSame('Chippendale', $draft->suburb);
        $this->assertSame('2030-11-03 20:00', $draft->startsAt->format('Y-m-d H:i'));
        $this->assertSame(-33.8863, $draft->latitude);
    }

    #[Test]
    public function a_date_without_a_time_becomes_an_evening_not_midnight(): void
    {
        $draft = $this->normalise($this->event(['startDate' => '2030-11-03']));

        $this->assertSame('19:00', $draft->startsAt->format('H:i'));
    }

    #[Test]
    public function it_skips_a_cancelled_event(): void
    {
        $this->assertNull($this->normalise($this->event([
            'eventStatus' => 'https://schema.org/EventCancelled',
        ])));
    }

    #[Test]
    public function it_skips_an_event_with_no_start_date(): void
    {
        $event = $this->event();
        unset($event['startDate']);

        $this->assertNull($this->normalise($event));
    }

    #[Test]
    public function it_reads_a_price_range_from_offers(): void
    {
        $this->assertSame('$25 – $45', $this->normalise($this->event())->price);
    }

    #[Test]
    public function a_zero_price_reads_as_free(): void
    {
        $event = $this->event();
        $event['offers'] = ['@type' => 'Offer', 'price' => 0];

        $this->assertSame('Free', $this->normalise($event)->price);
    }

    #[Test]
    public function the_same_page_listing_two_nights_yields_two_events(): void
    {
        $first = $this->normalise($this->event(['startDate' => '2030-11-03T20:00:00+11:00']));
        $second = $this->normalise($this->event(['startDate' => '2030-11-04T20:00:00+11:00']));

        $this->assertNotSame($first->externalId, $second->externalId);
        $this->assertNotSame($first->fingerprint(), $second->fingerprint());
    }

    #[Test]
    public function it_finds_events_inside_a_graph_wrapper(): void
    {
        $html = $this->page(['@context' => 'https://schema.org', '@graph' => [
            ['@type' => 'WebSite', 'name' => 'A venue'],
            $this->event(),
        ]]);

        $items = $this->fetchFrom($html);

        $this->assertCount(1, $items);
        $this->assertSame('Sunday Session', $items[0]['name']);
    }

    #[Test]
    public function it_finds_events_in_a_bare_list(): void
    {
        $html = $this->page([$this->event(['name' => 'One']), $this->event(['name' => 'Two'])]);

        $this->assertCount(2, $this->fetchFrom($html));
    }

    #[Test]
    public function it_recognises_specific_event_subtypes(): void
    {
        $html = $this->page($this->event(['@type' => 'MusicEvent']));

        $this->assertCount(1, $this->fetchFrom($html));
    }

    #[Test]
    public function it_ignores_structured_data_that_is_not_an_event(): void
    {
        $html = $this->page(['@type' => 'Restaurant', 'name' => 'Not a gig']);

        $this->assertSame([], $this->fetchFrom($html));
    }

    #[Test]
    public function malformed_json_does_not_derail_the_crawl(): void
    {
        $html = '<html><script type="application/ld+json">{ broken,,, }</script></html>';

        $this->assertSame([], $this->fetchFrom($html));
    }

    #[Test]
    public function it_walks_a_sitemap_to_find_pages(): void
    {
        Http::fake([
            'venue.invalid/robots.txt' => Http::response(''),
            'venue.invalid/sitemap.xml' => Http::response($this->sitemap([
                'https://venue.invalid/events/one',
                'https://venue.invalid/events/two',
            ])),
            'venue.invalid/events/*' => Http::response($this->page($this->event())),
        ]);

        $source = $this->source(['sitemap_url' => 'https://venue.invalid/sitemap.xml']);

        $items = iterator_to_array(app(JsonLdAdapter::class)->fetch($source), false);

        $this->assertCount(2, $items);
    }

    #[Test]
    public function it_respects_the_sources_path_allowlist(): void
    {
        Http::fake([
            'venue.invalid/robots.txt' => Http::response(''),
            'venue.invalid/sitemap.xml' => Http::response($this->sitemap([
                'https://venue.invalid/whats-on/gig',
                'https://venue.invalid/corporate/hire',
            ])),
            'venue.invalid/*' => Http::response($this->page($this->event())),
        ]);

        // Sydney Opera House disallows everything then permits named sections;
        // a crawl that ignored that would be unwelcome even though each page
        // returns happily.
        $source = $this->source([
            'sitemap_url' => 'https://venue.invalid/sitemap.xml',
            'path_allowlist' => ['/whats-on'],
        ]);

        iterator_to_array(app(JsonLdAdapter::class)->fetch($source), false);

        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/corporate/'));
    }

    #[Test]
    public function it_follows_a_sitemap_index(): void
    {
        Http::fake([
            'venue.invalid/robots.txt' => Http::response(''),
            'venue.invalid/sitemap.xml' => Http::response($this->sitemapIndex([
                'https://venue.invalid/sitemap-events.xml',
            ])),
            'venue.invalid/sitemap-events.xml' => Http::response($this->sitemap([
                'https://venue.invalid/events/one',
            ])),
            'venue.invalid/events/*' => Http::response($this->page($this->event())),
        ]);

        $source = $this->source(['sitemap_url' => 'https://venue.invalid/sitemap.xml']);

        $this->assertCount(1, iterator_to_array(app(JsonLdAdapter::class)->fetch($source), false));
    }

    #[Test]
    public function a_page_robots_disallows_is_not_fetched(): void
    {
        Http::fake([
            'venue.invalid/robots.txt' => Http::response("User-agent: *\nDisallow: /events/"),
            'venue.invalid/sitemap.xml' => Http::response($this->sitemap([
                'https://venue.invalid/events/one',
            ])),
            'venue.invalid/events/*' => Http::response($this->page($this->event())),
        ]);

        $source = $this->source(['sitemap_url' => 'https://venue.invalid/sitemap.xml']);

        $this->assertSame([], iterator_to_array(app(JsonLdAdapter::class)->fetch($source), false));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchFrom(string $html): array
    {
        Http::fake([
            'venue.invalid/robots.txt' => Http::response(''),
            'venue.invalid/*' => Http::response($html),
        ]);

        return iterator_to_array(
            app(JsonLdAdapter::class)->fetch($this->source(['endpoint' => 'https://venue.invalid/events'])),
            false,
        );
    }

    private function normalise(array $event): ?EventDraft
    {
        return app(JsonLdAdapter::class)->normalise($this->source(), $event);
    }

    private function source(array $overrides = []): IngestSource
    {
        return IngestSource::factory()->make(array_merge([
            'name' => 'Lansdowne Hotel',
            'adapter' => 'json-ld',
            'default_category_slug' => 'music',
        ], $overrides));
    }

    private function page(array $jsonLd): string
    {
        return '<html><head><script type="application/ld+json">'
            .json_encode($jsonLd, JSON_THROW_ON_ERROR)
            .'</script></head><body>A page</body></html>';
    }

    /**
     * @param  array<int, string>  $urls
     */
    private function sitemap(array $urls): string
    {
        $entries = implode('', array_map(static fn (string $u): string => "<url><loc>{$u}</loc></url>", $urls));

        return '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
            .$entries.'</urlset>';
    }

    /**
     * @param  array<int, string>  $urls
     */
    private function sitemapIndex(array $urls): string
    {
        $entries = implode('', array_map(static fn (string $u): string => "<sitemap><loc>{$u}</loc></sitemap>", $urls));

        return '<?xml version="1.0" encoding="UTF-8"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
            .$entries.'</sitemapindex>';
    }

    /**
     * @return array<string, mixed>
     */
    private function event(array $overrides = []): array
    {
        return array_merge([
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => 'Sunday Session',
            'url' => 'https://venue.invalid/events/sunday-session',
            'startDate' => '2030-11-03T20:00:00+11:00',
            'endDate' => '2030-11-03T23:00:00+11:00',
            'description' => 'An afternoon that turns into an evening.',
            'image' => 'https://venue.invalid/img/hero.jpg',
            'location' => [
                '@type' => 'Place',
                'name' => 'Lansdowne Hotel',
                'address' => [
                    '@type' => 'PostalAddress',
                    'streetAddress' => '2-6 City Road',
                    'addressLocality' => 'Chippendale',
                ],
                'geo' => ['@type' => 'GeoCoordinates', 'latitude' => -33.8863, 'longitude' => 151.1972],
            ],
            'offers' => [
                '@type' => 'AggregateOffer',
                'lowPrice' => 25,
                'highPrice' => 45,
                'url' => 'https://tickets.invalid/sunday-session',
            ],
        ], $overrides);
    }
}
