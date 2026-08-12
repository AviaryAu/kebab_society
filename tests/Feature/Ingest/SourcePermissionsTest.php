<?php

declare(strict_types=1);

namespace Tests\Feature\Ingest;

use App\Enums\SourceTrust;
use App\Models\IngestSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The permission rules are the whole legal posture of the pipeline, so they get
 * tested as behaviour rather than trusted as convention.
 */
class SourcePermissionsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function an_editorial_source_may_never_import_images_even_if_ticked(): void
    {
        $source = IngestSource::factory()->editorial()->create([
            'allow_image_import' => true,
        ]);

        $this->assertFalse($source->mayImportImages());
    }

    #[Test]
    public function an_editorial_source_may_never_auto_publish_even_if_ticked(): void
    {
        $source = IngestSource::factory()->editorial()->create([
            'auto_publish' => true,
        ]);

        $this->assertFalse($source->mayAutoPublish());
    }

    #[Test]
    public function a_licensed_api_may_do_both(): void
    {
        $source = IngestSource::factory()->create([
            'trust' => SourceTrust::Licensed,
            'auto_publish' => true,
            'allow_image_import' => true,
        ]);

        $this->assertTrue($source->mayImportImages());
        $this->assertTrue($source->mayAutoPublish());
    }

    #[Test]
    public function a_trusted_source_still_honours_its_own_switches_being_off(): void
    {
        $source = IngestSource::factory()->create([
            'trust' => SourceTrust::Licensed,
            'auto_publish' => false,
            'allow_image_import' => false,
        ]);

        $this->assertFalse($source->mayImportImages());
        $this->assertFalse($source->mayAutoPublish());
    }

    #[Test]
    public function credentials_are_encrypted_at_rest(): void
    {
        $source = IngestSource::factory()->create([
            'credentials' => ['api_key' => 'super-secret-key'],
        ]);

        $stored = (string) $this->getConnection()
            ->table('ingest_sources')
            ->where('id', $source->id)
            ->value('credentials');

        $this->assertStringNotContainsString('super-secret-key', $stored);
        $this->assertSame('super-secret-key', $source->fresh()->credentials['api_key']);
    }

    #[Test]
    public function credentials_are_hidden_from_serialisation(): void
    {
        $source = IngestSource::factory()->create([
            'credentials' => ['api_key' => 'super-secret-key'],
        ]);

        $this->assertArrayNotHasKey('credentials', $source->toArray());
    }

    #[Test]
    public function a_source_that_has_never_run_is_due(): void
    {
        $source = IngestSource::factory()->create(['last_run_at' => null]);

        $this->assertTrue($source->isDue());
    }

    #[Test]
    public function a_source_polled_recently_is_not_due(): void
    {
        $source = IngestSource::factory()->create([
            'frequency_minutes' => 360,
            'last_run_at' => now()->subMinutes(30),
        ]);

        $this->assertFalse($source->isDue());
    }

    #[Test]
    public function a_source_past_its_interval_is_due(): void
    {
        $source = IngestSource::factory()->create([
            'frequency_minutes' => 60,
            'last_run_at' => now()->subMinutes(90),
        ]);

        $this->assertTrue($source->isDue());
    }

    #[Test]
    public function an_over_eager_frequency_is_clamped_to_the_configured_floor(): void
    {
        config()->set('ingest.min_frequency_minutes', 30);

        // An administrator asking to poll every minute does not get to.
        $source = IngestSource::factory()->create([
            'frequency_minutes' => 1,
            'last_run_at' => now()->subMinutes(5),
        ]);

        $this->assertFalse($source->isDue());
    }

    #[Test]
    public function a_disabled_source_is_never_due(): void
    {
        $source = IngestSource::factory()->disabled()->create(['last_run_at' => null]);

        $this->assertFalse($source->isDue());
    }
}
