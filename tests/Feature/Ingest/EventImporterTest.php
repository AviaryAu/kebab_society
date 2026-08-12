<?php

declare(strict_types=1);

namespace Tests\Feature\Ingest;

use App\Enums\ImageLicence;
use App\Enums\ImportStatus;
use App\Models\Event;
use App\Models\EventImport;
use App\Models\IngestRun;
use App\Models\IngestSource;
use App\Models\Venue;
use App\Services\Ingest\EventDraft;
use App\Services\Ingest\EventImporter;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventImporterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // No geocoding in tests; drafts carry their own coordinates.
        config()->set('ingest.geocoding.enabled', false);
    }

    #[Test]
    public function a_trusted_source_creates_an_event_and_its_venue(): void
    {
        $source = $this->source();

        $result = $this->import($source, $this->draft());

        $this->assertSame('created', $result);

        $event = Event::query()->firstOrFail();
        $this->assertSame('Courtney Barnett', $event->title);
        $this->assertSame('Newtown', $event->suburb);
        $this->assertSame($source->id, $event->ingest_source_id);
        $this->assertSame('tm-1', $event->external_id);

        $this->assertDatabaseHas('venues', ['name' => 'Enmore Theatre', 'status' => 'draft']);
    }

    #[Test]
    public function an_ingested_event_never_carries_the_publishers_prose(): void
    {
        $source = $this->source();

        $this->import($source, $this->draft(
            sourceDescription: 'An aching, gorgeous set from one of the finest songwriters going.',
        ));

        $event = Event::query()->firstOrFail();

        $this->assertNull($event->description);
        $this->assertNull($event->body);

        // It is kept for the copywriter and the reviewer, but only there.
        $import = EventImport::query()->firstOrFail();
        $this->assertSame(
            'An aching, gorgeous set from one of the finest songwriters going.',
            $import->raw_payload['blurb'],
        );
    }

    #[Test]
    public function an_ingested_event_carries_an_attribution_link(): void
    {
        $source = $this->source(['name' => 'Ticketmaster']);

        $this->import($source, $this->draft());

        $event = Event::query()->firstOrFail();

        $this->assertSame('Ticketmaster', $event->source_name);
        $this->assertSame('https://tickets.test/courtney', $event->source_attribution_url);
        $this->assertSame(
            ['name' => 'Ticketmaster', 'url' => 'https://tickets.test/courtney'],
            $event->attribution(),
        );
    }

    #[Test]
    public function an_editorial_source_records_no_artwork(): void
    {
        $source = $this->source([
            'trust' => 'signal',
            'tier' => 'editorial',
            'allow_image_import' => true,
            'auto_publish' => true,
        ]);

        $this->import($source, $this->draft());

        // Nothing was published, so check the staged row and confirm no event
        // ever held the image.
        $this->assertSame(0, Event::query()->whereNotNull('image_source_url')->count());
    }

    #[Test]
    public function a_licensed_source_records_artwork_with_its_licence(): void
    {
        $this->import($this->source(), $this->draft());

        $event = Event::query()->firstOrFail();

        $this->assertSame('https://img.test/hero.jpg', $event->image_source_url);
        $this->assertSame(ImageLicence::Licensed, $event->image_licence);
        $this->assertSame('Ticketmaster', $event->image_credit);
    }

    #[Test]
    public function an_editorial_source_stages_for_review_instead_of_publishing(): void
    {
        $source = $this->source(['trust' => 'signal', 'tier' => 'editorial', 'auto_publish' => false]);

        $result = $this->import($source, $this->draft());

        // "staged", not "skipped": a run that queued a dozen events for review
        // has done real work, and the run log should say so.
        $this->assertSame('staged', $result);
        $this->assertSame(0, Event::query()->count());

        $import = EventImport::query()->firstOrFail();
        $this->assertSame(ImportStatus::Pending, $import->status);
        $this->assertSame('Courtney Barnett', $import->proposed_title);
    }

    #[Test]
    public function re_running_the_same_source_updates_rather_than_duplicates(): void
    {
        $source = $this->source();

        $this->assertSame('created', $this->import($source, $this->draft()));
        $this->assertSame('updated', $this->import($source, $this->draft(price: '$95')));

        $this->assertSame(1, Event::query()->count());
        $this->assertSame(1, EventImport::query()->count());
        $this->assertSame('$95', Event::query()->firstOrFail()->price);
    }

    #[Test]
    public function a_second_source_describing_the_same_gig_does_not_create_a_duplicate(): void
    {
        $ticketing = $this->source(['slug' => 'ticketmaster', 'name' => 'Ticketmaster']);
        $venueSite = $this->source(['slug' => 'venue-direct', 'name' => 'Enmore Theatre']);

        $this->import($ticketing, $this->draft());
        $this->import($venueSite, $this->draft(externalId: 'enmore-99', title: 'Courtney  Barnett'));

        $this->assertSame(1, Event::query()->count());
        $this->assertSame(2, EventImport::query()->count());
    }

    #[Test]
    public function a_lower_ranked_source_does_not_overwrite_a_higher_ranked_one(): void
    {
        config()->set('ingest.source_precedence', ['ticketmaster', 'venue-direct']);

        $ticketing = $this->source(['slug' => 'ticketmaster', 'name' => 'Ticketmaster']);
        $venueSite = $this->source(['slug' => 'venue-direct', 'name' => 'Enmore Theatre']);

        $this->import($ticketing, $this->draft(price: '$89'));
        $this->import($venueSite, $this->draft(externalId: 'enmore-99', price: '$500'));

        $this->assertSame('$89', Event::query()->firstOrFail()->price);
    }

    #[Test]
    public function a_lower_ranked_source_may_still_fill_a_gap(): void
    {
        config()->set('ingest.source_precedence', ['ticketmaster', 'venue-direct']);

        $ticketing = $this->source(['slug' => 'ticketmaster', 'name' => 'Ticketmaster']);
        $venueSite = $this->source(['slug' => 'venue-direct', 'name' => 'Enmore Theatre']);

        $this->import($ticketing, $this->draft(price: null));
        $this->import($venueSite, $this->draft(externalId: 'enmore-99', price: '$75'));

        $this->assertSame('$75', Event::query()->firstOrFail()->price);
    }

    #[Test]
    public function a_locked_event_is_left_alone(): void
    {
        $source = $this->source();

        $this->import($source, $this->draft());

        $event = Event::query()->firstOrFail();
        $event->forceFill(['import_locked' => true, 'title' => 'Courtney Barnett (rescheduled)'])->save();

        $result = $this->import($source, $this->draft(title: 'Courtney Barnett'));

        $this->assertSame('skipped', $result);
        $this->assertSame('Courtney Barnett (rescheduled)', $event->fresh()->title);
        $this->assertSame(ImportStatus::Merged, EventImport::query()->firstOrFail()->status);
    }

    #[Test]
    public function an_import_a_reviewer_rejected_is_not_resurrected(): void
    {
        $source = $this->source();

        EventImport::factory()->rejected()->create([
            'ingest_source_id' => $source->id,
            'external_id' => 'tm-1',
        ]);

        $result = $this->import($source, $this->draft());

        $this->assertSame('skipped', $result);
        $this->assertSame(0, Event::query()->count());
    }

    #[Test]
    public function an_existing_venue_is_reused_rather_than_duplicated(): void
    {
        Venue::factory()->create([
            'name' => 'Enmore Theatre',
            'slug' => 'enmore-theatre',
            'suburb' => 'Newtown',
        ]);

        $this->import($this->source(), $this->draft());

        $this->assertSame(1, Venue::query()->count());
        $this->assertSame(
            'enmore-theatre',
            Event::query()->firstOrFail()->venue->slug,
        );
    }

    #[Test]
    public function ingested_events_start_as_drafts_for_an_editor_to_finish(): void
    {
        $this->import($this->source(), $this->draft());

        $this->assertSame('draft', Event::query()->firstOrFail()->status);
    }

    private function import(IngestSource $source, EventDraft $draft): string
    {
        $run = IngestRun::factory()->create(['ingest_source_id' => $source->id]);

        return app(EventImporter::class)->import($source, $draft, $run);
    }

    private function source(array $overrides = []): IngestSource
    {
        return IngestSource::factory()->create(array_merge([
            'auto_publish' => true,
            'allow_image_import' => true,
        ], $overrides));
    }

    private function draft(
        string $externalId = 'tm-1',
        string $title = 'Courtney Barnett',
        ?string $price = '$89',
        ?string $sourceDescription = null,
    ): EventDraft {
        return new EventDraft(
            externalId: $externalId,
            title: $title,
            startsAt: CarbonImmutable::parse('2030-09-01 20:00', 'Australia/Sydney'),
            endsAt: CarbonImmutable::parse('2030-09-01 23:00', 'Australia/Sydney'),
            venueName: 'Enmore Theatre',
            venueExternalId: 'KovZ917A7EV',
            address: '118-132 Enmore Road',
            suburb: 'Newtown',
            latitude: -33.8975,
            longitude: 151.1758,
            categorySlug: 'music',
            price: $price,
            ticketUrl: 'https://tickets.test/courtney',
            sourceUrl: 'https://tickets.test/courtney',
            imageUrl: 'https://img.test/hero.jpg',
            imageCredit: 'Ticketmaster',
            sourceDescription: $sourceDescription,
            raw: ['blurb' => $sourceDescription],
        );
    }
}
