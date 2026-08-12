<?php

declare(strict_types=1);

namespace Tests\Feature\Ingest;

use App\Enums\ImageLicence;
use App\Jobs\Ingest\ImportEventImageJob;
use App\Models\Event;
use App\Models\IngestSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImageImporterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A .invalid host, not .test: RFC 2606 guarantees .invalid never resolves,
     * whereas .test is routed to 127.0.0.1 on many development machines — which
     * the importer's SSRF guard correctly refuses to fetch from.
     */
    private const HOST = 'img.invalid';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        config()->set('kslive.media.disk', 'public');
        config()->set('ingest.http.min_interval_ms', 0);
    }

    #[Test]
    public function it_stores_a_licensed_image_as_webp_on_our_own_disk(): void
    {
        $event = $this->event();

        $this->fakeImage();

        dispatch_sync(new ImportEventImageJob($event->id));

        $event->refresh();

        $files = Storage::disk('public')->allFiles('events');

        $this->assertCount(1, $files);
        $this->assertStringEndsWith('.webp', $files[0]);
        $this->assertStringContainsString('events/', (string) $event->image);
        $this->assertSame(ImageLicence::Licensed, $event->image_licence);
    }

    #[Test]
    public function an_editorial_source_never_has_its_artwork_copied(): void
    {
        $source = IngestSource::factory()->editorial()->create();
        $event = $this->event(['ingest_source_id' => $source->id]);

        Http::fake();

        dispatch_sync(new ImportEventImageJob($event->id));

        $this->assertSame([], Storage::disk('public')->allFiles('events'));
        $this->assertSame(ImageLicence::None, $event->fresh()->image_licence);
        $this->assertNull($event->fresh()->image);
    }

    #[Test]
    public function it_refuses_an_image_url_pointing_at_internal_infrastructure(): void
    {
        $event = $this->event(['image_source_url' => 'http://169.254.169.254/latest/meta-data/']);

        Http::fake();

        dispatch_sync(new ImportEventImageJob($event->id));

        $this->assertSame([], Storage::disk('public')->allFiles('events'));
        Http::assertNothingSent();
    }

    #[Test]
    public function it_ignores_a_response_that_is_not_an_image(): void
    {
        $event = $this->event();

        Http::fake([
            'img.invalid/robots.txt' => Http::response(''),
            'img.invalid/*' => Http::response('<html>not a picture</html>', 200, [
                'Content-Type' => 'text/html',
            ]),
        ]);

        dispatch_sync(new ImportEventImageJob($event->id));

        $this->assertSame([], Storage::disk('public')->allFiles('events'));
        $this->assertNull($event->fresh()->image);
    }

    #[Test]
    public function it_survives_a_payload_that_lies_about_being_an_image(): void
    {
        $event = $this->event();

        // Correct content type, nonsense bytes. Decoding must fail safely
        // rather than writing a poisoned file to the bucket.
        Http::fake([
            'img.invalid/robots.txt' => Http::response(''),
            'img.invalid/*' => Http::response('GIF89a<?php echo "gotcha"; ?>', 200, [
                'Content-Type' => 'image/png',
            ]),
        ]);

        dispatch_sync(new ImportEventImageJob($event->id));

        $this->assertSame([], Storage::disk('public')->allFiles('events'));
    }

    #[Test]
    public function it_rejects_an_image_over_the_size_cap(): void
    {
        config()->set('ingest.images.max_bytes', 128);

        $event = $this->event();
        $this->fakeImage();

        dispatch_sync(new ImportEventImageJob($event->id));

        $this->assertSame([], Storage::disk('public')->allFiles('events'));
    }

    #[Test]
    public function a_locked_event_keeps_the_artwork_an_editor_chose(): void
    {
        $event = $this->event(['import_locked' => true, 'image' => '/media/chosen.webp']);

        $this->fakeImage();

        dispatch_sync(new ImportEventImageJob($event->id));

        $this->assertSame('/media/chosen.webp', $event->fresh()->image);
        Http::assertNothingSent();
    }

    #[Test]
    public function replacing_artwork_removes_the_copy_it_supersedes(): void
    {
        $event = $this->event();

        $this->fakeImage();
        dispatch_sync(new ImportEventImageJob($event->id));

        $first = Storage::disk('public')->allFiles('events')[0];

        $event->forceFill(['image_source_url' => 'https://img.invalid/hero-v2.jpg'])->save();
        dispatch_sync(new ImportEventImageJob($event->id));

        $files = Storage::disk('public')->allFiles('events');

        $this->assertCount(1, $files);
        $this->assertNotSame($first, $files[0]);
    }

    #[Test]
    public function the_command_only_queues_events_whose_source_permits_it(): void
    {
        Queue::fake();

        $this->event();
        $this->event([
            'ingest_source_id' => IngestSource::factory()->editorial()->create()->id,
        ]);

        $this->artisan('ingest:images')->assertSuccessful();

        Queue::assertPushed(ImportEventImageJob::class, 1);
    }

    private function fakeImage(): void
    {
        $jpeg = (string) (new ImageManager(new Driver))
            ->createImage(400, 225)
            ->encode(new JpegEncoder(quality: 80));

        Http::fake([
            'img.invalid/robots.txt' => Http::response(''),
            'img.invalid/*' => Http::response($jpeg, 200, ['Content-Type' => 'image/jpeg']),
        ]);
    }

    private function event(array $overrides = []): Event
    {
        return Event::factory()->create(array_merge([
            'image' => null,
            'image_source_url' => 'https://img.invalid/hero.jpg',
            'start_datetime' => now()->addDays(5),
            'ingest_source_id' => IngestSource::factory()->create([
                'allow_image_import' => true,
            ])->id,
        ], $overrides));
    }
}
