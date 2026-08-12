<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\ImportStatus;
use App\Jobs\Ingest\GenerateEventCopyJob;
use App\Jobs\Ingest\ImportEventImageJob;
use App\Models\Event;
use App\Models\EventImport;
use App\Models\IngestSource;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReviewImportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ingest.geocoding.enabled', false);
        Queue::fake();
    }

    #[Test]
    public function the_queue_is_closed_to_the_public(): void
    {
        $this->get('/admin/imports')->assertRedirect('/admin/login');
    }

    #[Test]
    public function a_non_admin_cannot_approve_an_import(): void
    {
        $import = $this->import();

        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->post("/admin/imports/{$import->id}/approve")
            ->assertNotFound();

        $this->assertSame(0, Event::query()->count());
    }

    #[Test]
    public function the_queue_shows_pending_imports_by_default(): void
    {
        $this->import(['proposed_title' => 'Waiting Gig']);
        EventImport::factory()->approved()->create(['proposed_title' => 'Already Done']);

        $this->actingAs($this->admin())
            ->get('/admin/imports')
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->component('Admin/Imports/Index')
                    ->has('imports.data', 1)
                    ->where('imports.data.0.title', 'Waiting Gig'),
            );
    }

    #[Test]
    public function the_queue_never_ships_the_publishers_prose_to_the_browser(): void
    {
        $this->import([
            'raw_payload' => ['description' => 'An aching, gorgeous set from a generational talent.'],
        ]);

        $response = $this->actingAs($this->admin())->get('/admin/imports');

        $response->assertOk();
        $response->assertDontSee('aching, gorgeous', escape: false);
    }

    #[Test]
    public function approving_an_import_publishes_an_event(): void
    {
        $import = $this->import();

        $this->actingAs($this->admin())
            ->post("/admin/imports/{$import->id}/approve")
            ->assertRedirect();

        $event = Event::query()->firstOrFail();

        $this->assertSame('Waiting Gig', $event->title);
        $this->assertSame('draft', $event->status);
        $this->assertSame(ImportStatus::Approved, $import->fresh()->status);
        $this->assertSame($event->id, $import->fresh()->event_id);
    }

    #[Test]
    public function an_approved_event_is_never_left_bare(): void
    {
        $import = $this->import();

        $this->actingAs($this->admin())->post("/admin/imports/{$import->id}/approve");

        Queue::assertPushed(GenerateEventCopyJob::class);
    }

    #[Test]
    public function approving_an_editorial_import_does_not_fetch_artwork(): void
    {
        $source = IngestSource::factory()->editorial()->create();
        $import = $this->import([], $source);

        $this->actingAs($this->admin())->post("/admin/imports/{$import->id}/approve");

        Queue::assertNotPushed(ImportEventImageJob::class);
    }

    #[Test]
    public function approving_a_licensed_import_fetches_artwork(): void
    {
        $source = IngestSource::factory()->create(['allow_image_import' => true]);
        $import = $this->import([], $source);

        $this->actingAs($this->admin())->post("/admin/imports/{$import->id}/approve");

        Queue::assertPushed(ImportEventImageJob::class);
    }

    #[Test]
    public function the_reviewer_is_recorded(): void
    {
        $admin = $this->admin();
        $import = $this->import();

        $this->actingAs($admin)->post("/admin/imports/{$import->id}/approve");

        $this->assertSame($admin->id, $import->fresh()->reviewed_by);
        $this->assertNotNull($import->fresh()->reviewed_at);
    }

    #[Test]
    public function rejecting_keeps_the_row_so_it_is_not_offered_again(): void
    {
        $import = $this->import();

        $this->actingAs($this->admin())
            ->post("/admin/imports/{$import->id}/reject", ['reason' => 'Not a real event.'])
            ->assertRedirect();

        $import->refresh();

        $this->assertSame(ImportStatus::Rejected, $import->status);
        $this->assertSame('Not a real event.', $import->message);
        $this->assertSame(0, Event::query()->count());
    }

    #[Test]
    public function several_imports_can_be_published_at_once(): void
    {
        $ids = collect(range(1, 3))
            ->map(fn (int $i): int => $this->import(title: "Gig {$i}")->id)
            ->all();

        $this->actingAs($this->admin())
            ->post('/admin/imports/bulk', ['action' => 'approve', 'ids' => $ids])
            ->assertRedirect();

        $this->assertSame(3, Event::query()->count());
    }

    #[Test]
    public function approving_the_same_gig_from_two_sources_yields_one_event(): void
    {
        // Same night, same room, same act, reported by two publishers.
        $first = $this->import(source: IngestSource::factory()->create(['slug' => 'moshtix']));
        $second = $this->import(source: IngestSource::factory()->create(['slug' => 'venue-direct']));

        $this->actingAs($this->admin())
            ->post('/admin/imports/bulk', ['action' => 'approve', 'ids' => [$first->id, $second->id]]);

        $this->assertSame(1, Event::query()->count());
    }

    #[Test]
    public function a_bulk_action_ignores_imports_already_reviewed(): void
    {
        $pending = $this->import();
        $done = EventImport::factory()->rejected()->create();

        $this->actingAs($this->admin())
            ->post('/admin/imports/bulk', ['action' => 'approve', 'ids' => [$pending->id, $done->id]]);

        $this->assertSame(1, Event::query()->count());
        $this->assertSame(ImportStatus::Rejected, $done->fresh()->status);
    }

    #[Test]
    public function a_locked_event_is_not_overwritten_by_an_approval(): void
    {
        $event = Event::factory()->create([
            'title' => 'Edited by hand',
            'import_locked' => true,
        ]);

        $import = $this->import();
        $import->forceFill(['event_id' => $event->id])->save();

        $this->actingAs($this->admin())->post("/admin/imports/{$import->id}/approve");

        $this->assertSame('Edited by hand', $event->fresh()->title);
        $this->assertSame(ImportStatus::Merged, $import->fresh()->status);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function import(
        array $overrides = [],
        ?IngestSource $source = null,
        string $title = 'Waiting Gig',
    ): EventImport {
        $source ??= IngestSource::factory()->create();
        $start = CarbonImmutable::now()->addDays(10)->setTime(20, 0);

        return EventImport::factory()->create(array_merge([
            'ingest_source_id' => $source->id,
            'status' => ImportStatus::Pending,
            'proposed_title' => $title,
            'proposed_start' => $start,
            'normalised' => [
                'external_id' => 'x-'.Str::slug($title),
                'title' => $title,
                'starts_at' => $start->toIso8601String(),
                'venue_name' => 'Lansdowne Hotel',
                'suburb' => 'Chippendale',
                'category_slug' => 'music',
                'source_url' => 'https://example.org/gig',
                'image_url' => 'https://example.org/hero.jpg',
            ],
        ], $overrides));
    }
}
