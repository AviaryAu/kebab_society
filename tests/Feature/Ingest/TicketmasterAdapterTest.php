<?php

declare(strict_types=1);

namespace Tests\Feature\Ingest;

use App\Models\IngestSource;
use App\Services\Ingest\Adapters\TicketmasterAdapter;
use App\Services\Ingest\EventDraft;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class TicketmasterAdapterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ingest.http.min_interval_ms', 0);
    }

    #[Test]
    public function it_normalises_an_event_into_a_draft(): void
    {
        $draft = $this->normalise($this->event());

        $this->assertInstanceOf(EventDraft::class, $draft);
        $this->assertSame('G5vY-abc123', $draft->externalId);
        $this->assertSame('Courtney Barnett', $draft->title);
        $this->assertSame('Enmore Theatre', $draft->venueName);
        $this->assertSame('Newtown', $draft->suburb);
        $this->assertSame('118-132 Enmore Road', $draft->address);
        $this->assertSame(-33.8975, $draft->latitude);
        $this->assertSame(151.1758, $draft->longitude);
        $this->assertSame('music', $draft->categorySlug);
    }

    #[Test]
    public function it_reads_the_local_wall_clock_time(): void
    {
        $draft = $this->normalise($this->event());

        // Listed as 8pm in Newtown, so it should read as 8pm.
        $this->assertSame('2030-09-01 20:00', $draft->startsAt->format('Y-m-d H:i'));
        $this->assertSame('Australia/Sydney', $draft->startsAt->timezoneName);
    }

    #[Test]
    public function it_skips_test_fixtures_shipped_through_the_live_api(): void
    {
        $this->assertNull($this->normalise($this->event(['test' => true])));
    }

    #[Test]
    public function it_skips_cancelled_events(): void
    {
        $item = $this->event();
        $item['dates']['status']['code'] = 'cancelled';

        $this->assertNull($this->normalise($item));
    }

    #[Test]
    public function it_skips_an_event_with_no_confirmed_date(): void
    {
        $item = $this->event();
        $item['dates']['start']['dateTBA'] = true;

        $this->assertNull($this->normalise($item));
    }

    #[Test]
    public function an_event_with_no_announced_time_gets_a_sensible_default(): void
    {
        $item = $this->event();
        $item['dates']['start']['timeTBA'] = true;
        unset($item['dates']['start']['localTime']);

        $draft = $this->normalise($item);

        $this->assertSame('19:00', $draft->startsAt->format('H:i'));
    }

    #[Test]
    public function it_formats_a_price_range_for_reading_not_transacting(): void
    {
        $this->assertSame('$89 – $149.50', $this->normalise($this->event())->price);
    }

    #[Test]
    public function a_single_price_is_not_rendered_as_a_range(): void
    {
        $item = $this->event();
        $item['priceRanges'][0]['max'] = 89.0;

        $this->assertSame('$89', $this->normalise($item)->price);
    }

    #[Test]
    public function it_prefers_the_widest_non_fallback_artwork(): void
    {
        $this->assertSame(
            'https://img.test/wide-2048.jpg',
            $this->normalise($this->event())->imageUrl,
        );
    }

    #[Test]
    public function comedy_is_separated_from_theatre(): void
    {
        $item = $this->event();
        $item['classifications'][0]['segment']['name'] = 'Arts & Theatre';
        $item['classifications'][0]['genre']['name'] = 'Comedy';

        $this->assertSame('comedy', $this->normalise($item)->categorySlug);
    }

    #[Test]
    public function sport_maps_to_the_sport_category(): void
    {
        $item = $this->event();
        $item['classifications'][0]['segment']['name'] = 'Sports';

        $this->assertSame('sport', $this->normalise($item)->categorySlug);
    }

    #[Test]
    public function a_source_may_override_the_category_mapping(): void
    {
        $source = $this->source(['category_map' => ['Rock' => 'nightlife']]);

        $draft = app(TicketmasterAdapter::class)->normalise($source, $this->event());

        $this->assertSame('nightlife', $draft->categorySlug);
    }

    #[Test]
    public function an_unmapped_segment_falls_back_to_the_source_default(): void
    {
        $item = $this->event();
        $item['classifications'][0]['segment']['name'] = 'Miscellaneous';

        $source = $this->source(['default_category_slug' => 'festivals']);
        $draft = app(TicketmasterAdapter::class)->normalise($source, $item);

        $this->assertSame('festivals', $draft->categorySlug);
    }

    #[Test]
    public function the_publishers_blurb_is_carried_for_context_but_kept_out_of_the_facts(): void
    {
        $draft = $this->normalise($this->event());

        $this->assertSame('Please note: no latecomers admitted.', $draft->sourceDescription);
        $this->assertArrayNotHasKey('description', $draft->facts());
    }

    #[Test]
    public function it_walks_date_windows_and_pages(): void
    {
        Http::fake([
            // The sequence is scoped to the events endpoint on purpose.
            // Http::fake evaluates every stub for every request, so a wildcard
            // sequence would be silently popped by the robots.txt fetch.
            'app.ticketmaster.com/robots.txt' => Http::response(''),
            'app.ticketmaster.com/discovery/v2/events.json*' => Http::sequence()
                ->push($this->payload([$this->event(['id' => 'a'])], totalPages: 2))
                ->push($this->payload([$this->event(['id' => 'b'])], totalPages: 2))
                ->whenEmpty($this->payload([])),
        ]);

        $source = $this->source(['options' => ['window_days' => 30, 'horizon_days' => 60]]);

        $items = iterator_to_array(app(TicketmasterAdapter::class)->fetch($source), false);

        $this->assertCount(2, $items);
        $this->assertSame('a', $items[0]['id']);
        $this->assertSame('b', $items[1]['id']);
    }

    #[Test]
    public function it_stops_paging_before_the_deep_paging_cap(): void
    {
        Http::fake([
            'app.ticketmaster.com/robots.txt' => Http::response(''),
            // Claims far more pages than the API will actually serve.
            'app.ticketmaster.com/discovery/v2/events.json*' => Http::response(
                $this->payload([$this->event()], totalPages: 500),
            ),
        ]);

        $source = $this->source([
            'options' => ['window_days' => 30, 'horizon_days' => 30, 'size' => 200],
        ]);

        $items = iterator_to_array(app(TicketmasterAdapter::class)->fetch($source), false);

        // 200 per page against a 1000-item ceiling is five pages, not 500.
        $this->assertCount(5, $items);
    }

    #[Test]
    public function it_sends_the_api_key_and_a_sydney_filter(): void
    {
        Http::fake([
            'app.ticketmaster.com/robots.txt' => Http::response(''),
            'app.ticketmaster.com/discovery/v2/events.json*' => Http::response($this->payload([])),
        ]);

        $source = $this->source([
            'options' => ['window_days' => 30, 'horizon_days' => 30, 'market_id' => '302'],
        ]);

        iterator_to_array(app(TicketmasterAdapter::class)->fetch($source), false);

        Http::assertSent(function ($request): bool {
            $query = [];
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return ($query['apikey'] ?? null) === 'test-key'
                && ($query['countryCode'] ?? null) === 'AU'
                && ($query['marketId'] ?? null) === '302';
        });
    }

    #[Test]
    public function a_source_without_an_api_key_fails_loudly(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no Ticketmaster api_key');

        $source = $this->source(['credentials' => []]);

        iterator_to_array(app(TicketmasterAdapter::class)->fetch($source), false);
    }

    private function normalise(array $item): ?EventDraft
    {
        return app(TicketmasterAdapter::class)->normalise($this->source(), $item);
    }

    private function source(array $overrides = []): IngestSource
    {
        return IngestSource::factory()->make(array_merge([
            'adapter' => 'ticketmaster',
            'credentials' => ['api_key' => 'test-key'],
            'default_category_slug' => 'music',
        ], $overrides));
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     * @return array<string, mixed>
     */
    private function payload(array $events, int $totalPages = 1): array
    {
        return [
            '_embedded' => ['events' => $events],
            'page' => ['size' => 200, 'totalPages' => $totalPages, 'number' => 0],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function event(array $overrides = []): array
    {
        return array_merge([
            'id' => 'G5vY-abc123',
            'name' => 'Courtney Barnett',
            'url' => 'https://www.ticketmaster.com.au/courtney-barnett-tickets/artist/123',
            'test' => false,
            'info' => 'Please note: no latecomers admitted.',
            'images' => [
                ['url' => 'https://img.test/small.jpg', 'width' => 305, 'ratio' => '16_9', 'fallback' => false],
                ['url' => 'https://img.test/wide-2048.jpg', 'width' => 2048, 'ratio' => '16_9', 'fallback' => false],
                ['url' => 'https://img.test/huge-fallback.jpg', 'width' => 4000, 'ratio' => '16_9', 'fallback' => true],
            ],
            'dates' => [
                'start' => [
                    'localDate' => '2030-09-01',
                    'localTime' => '20:00:00',
                    'dateTime' => '2030-09-01T10:00:00Z',
                    'dateTBA' => false,
                    'dateTBD' => false,
                    'timeTBA' => false,
                    'noSpecificTime' => false,
                ],
                'timezone' => 'Australia/Sydney',
                'status' => ['code' => 'onsale'],
            ],
            'classifications' => [[
                'primary' => true,
                'segment' => ['name' => 'Music'],
                'genre' => ['name' => 'Rock'],
            ]],
            'priceRanges' => [[
                'type' => 'standard',
                'currency' => 'AUD',
                'min' => 89.0,
                'max' => 149.5,
            ]],
            '_embedded' => [
                'venues' => [[
                    'name' => 'Enmore Theatre',
                    'id' => 'KovZ917A7EV',
                    'postalCode' => '2042',
                    'city' => ['name' => 'Newtown'],
                    'state' => ['stateCode' => 'NSW'],
                    'country' => ['countryCode' => 'AU'],
                    'address' => ['line1' => '118-132 Enmore Road'],
                    'location' => ['latitude' => '-33.8975', 'longitude' => '151.1758'],
                ]],
            ],
        ], $overrides);
    }
}
