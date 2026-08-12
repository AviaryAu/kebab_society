<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Jobs\Ingest\RunSourceJob;
use App\Models\IngestSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ManageSourcesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_source_registry_is_closed_to_the_public(): void
    {
        $this->get('/admin/sources')->assertRedirect('/admin/login');
    }

    #[Test]
    public function a_non_admin_cannot_see_the_registry(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get('/admin/sources')
            ->assertNotFound();
    }

    #[Test]
    public function an_admin_sees_the_registry(): void
    {
        IngestSource::factory()->create(['name' => 'Ticketmaster']);

        $this->actingAs($this->admin())
            ->get('/admin/sources')
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->component('Admin/Sources/Index')
                    ->has('sources.data', 1)
                    ->where('sources.data.0.name', 'Ticketmaster'),
            );
    }

    #[Test]
    public function an_admin_can_add_a_source(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/sources', $this->payload())
            ->assertRedirect();

        $this->assertDatabaseHas('ingest_sources', [
            'slug' => 'ticketmaster',
            'adapter' => 'ticketmaster',
            'trust' => 'licensed',
        ]);
    }

    #[Test]
    public function an_api_key_is_stored_encrypted_and_never_returned(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/sources', $this->payload([
                'credentials' => ['api_key' => 'a-real-looking-key'],
            ]))
            ->assertRedirect();

        $stored = (string) $this->getConnection()
            ->table('ingest_sources')
            ->where('slug', 'ticketmaster')
            ->value('credentials');

        $this->assertStringNotContainsString('a-real-looking-key', $stored);

        $this->actingAs($this->admin())
            ->get('/admin/sources/ticketmaster/edit')
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->where('source.has_credentials', true)
                    ->missing('source.credentials'),
            );
    }

    #[Test]
    public function submitting_a_blank_key_keeps_the_stored_one(): void
    {
        $source = IngestSource::factory()->create([
            'slug' => 'ticketmaster',
            'credentials' => ['api_key' => 'keep-me'],
        ]);

        $this->actingAs($this->admin())
            ->put("/admin/sources/{$source->slug}", $this->payload(['credentials' => []]))
            ->assertRedirect();

        $this->assertSame('keep-me', $source->fresh()->credentials['api_key']);
    }

    #[Test]
    public function an_editorial_source_cannot_be_told_to_import_images(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/sources', $this->payload([
                'trust' => 'signal',
                'tier' => 'editorial',
                'allow_image_import' => true,
            ]))
            ->assertSessionHasErrors('allow_image_import');

        $this->assertDatabaseCount('ingest_sources', 0);
    }

    #[Test]
    public function an_editorial_source_cannot_be_told_to_auto_publish(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/sources', $this->payload([
                'trust' => 'signal',
                'tier' => 'editorial',
                'auto_publish' => true,
            ]))
            ->assertSessionHasErrors('auto_publish');
    }

    #[Test]
    public function an_unknown_adapter_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/sources', $this->payload([
                'adapter' => 'App\\Models\\User',
            ]))
            ->assertSessionHasErrors('adapter');
    }

    #[Test]
    public function an_endpoint_pointing_at_internal_infrastructure_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/sources', $this->payload([
                'endpoint' => 'http://169.254.169.254/latest/meta-data/',
            ]))
            ->assertSessionHasErrors('endpoint');
    }

    #[Test]
    public function a_polling_interval_below_the_floor_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/sources', $this->payload(['frequency_minutes' => 1]))
            ->assertSessionHasErrors('frequency_minutes');
    }

    #[Test]
    public function an_admin_can_delete_a_source(): void
    {
        $source = IngestSource::factory()->create();

        $this->actingAs($this->admin())
            ->delete("/admin/sources/{$source->slug}")
            ->assertRedirect('/admin/sources');

        $this->assertDatabaseCount('ingest_sources', 0);
    }

    #[Test]
    public function running_a_source_is_queued_rather_than_blocking_the_request(): void
    {
        Queue::fake();

        $source = IngestSource::factory()->create();

        $this->actingAs($this->admin())
            ->post("/admin/sources/{$source->slug}/run", ['limit' => 50])
            ->assertRedirect()
            ->assertSessionHas('success');

        Queue::assertPushed(
            RunSourceJob::class,
            fn (RunSourceJob $job): bool => $job->sourceId === $source->id
                && $job->limit === 50
                && $job->dryRun === false,
        );
    }

    #[Test]
    public function a_dry_run_is_passed_through_to_the_job(): void
    {
        Queue::fake();

        $source = IngestSource::factory()->create();

        $this->actingAs($this->admin())
            ->post("/admin/sources/{$source->slug}/run", ['dry_run' => true])
            ->assertRedirect();

        Queue::assertPushed(RunSourceJob::class, fn (RunSourceJob $job): bool => $job->dryRun);
    }

    #[Test]
    public function a_non_admin_cannot_trigger_a_run(): void
    {
        Queue::fake();

        $source = IngestSource::factory()->create();

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->post("/admin/sources/{$source->slug}/run")
            ->assertNotFound();

        Queue::assertNothingPushed();
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ticketmaster',
            'slug' => 'ticketmaster',
            'adapter' => 'ticketmaster',
            'tier' => 'api',
            'trust' => 'licensed',
            'endpoint' => 'https://app.ticketmaster.com/discovery/v2/events.json',
            'frequency_minutes' => 360,
            'rate_limit_per_minute' => 30,
            'auto_publish' => false,
            'allow_image_import' => false,
            'is_enabled' => true,
        ], $overrides);
    }
}
