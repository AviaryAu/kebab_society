<?php

declare(strict_types=1);

namespace Tests\Feature\Ingest;

use App\Jobs\Ingest\GenerateEventCopyJob;
use App\Models\Event;
use App\Models\IngestSource;
use App\Models\Venue;
use App\Services\Ingest\CopyWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GenerateEventCopyJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config()->set('ingest.ai.key', 'test-key');
        config()->set('ingest.ai.base_url', 'https://api.groq.com/openai/v1');
    }

    #[Test]
    public function it_writes_copy_onto_the_event(): void
    {
        $event = $this->event();

        $this->fakeReply($event->id, 'Fuzzed guitars and deadpan asides, in the room that suits them.');

        dispatch_sync(new GenerateEventCopyJob([$event->id]));

        $event->refresh();

        $this->assertSame('Fuzzed guitars and deadpan asides, in the room that suits them.', $event->description);
        $this->assertNotNull($event->copy_generated_at);
        $this->assertSame($event->factsHash(), $event->facts_hash);
    }

    #[Test]
    public function copy_is_not_rewritten_when_the_facts_have_not_moved(): void
    {
        $event = $this->event();

        $this->fakeReply($event->id, 'A perfectly good description of a live show.');
        dispatch_sync(new GenerateEventCopyJob([$event->id]));

        // Second pass: nothing about the event changed, so nothing should be asked.
        dispatch_sync(new GenerateEventCopyJob([$event->id]));

        Http::assertSentCount(1);
    }

    #[Test]
    public function copy_is_rewritten_when_a_fact_changes(): void
    {
        $event = $this->event();

        $this->fakeReply($event->id, 'A perfectly good description of a live show.');
        dispatch_sync(new GenerateEventCopyJob([$event->id]));

        $event->forceFill(['start_datetime' => now()->addDays(20)->setTime(21, 0)])->save();

        dispatch_sync(new GenerateEventCopyJob([$event->id]));

        Http::assertSentCount(2);
    }

    #[Test]
    public function template_copy_does_not_claim_the_facts_hash(): void
    {
        $event = $this->event();

        Http::fake(['api.groq.com/*' => Http::response('down', 500)]);

        dispatch_sync(new GenerateEventCopyJob([$event->id]));

        $event->refresh();

        $this->assertSame('template', $event->copy_model);
        $this->assertNull($event->copy_generated_at);

        // Left findable, so tomorrow's budget can upgrade it.
        $this->assertTrue($event->needsGeneratedCopy());
    }

    #[Test]
    public function a_hand_written_meta_description_is_left_alone(): void
    {
        $event = $this->event(['meta_description' => 'Written by an editor.']);

        $this->fakeReply($event->id, 'Generated copy for the body.');

        dispatch_sync(new GenerateEventCopyJob([$event->id]));

        $this->assertSame('Written by an editor.', $event->fresh()->meta_description);
    }

    #[Test]
    public function being_throttled_releases_the_job_instead_of_failing_it(): void
    {
        Queue::fake();

        $event = $this->event();

        Http::fake([
            'api.groq.com/*' => Http::response('slow down', 429, ['retry-after' => '30']),
        ]);

        (new GenerateEventCopyJob([$event->id]))->handle(app(CopyWriter::class));

        // Nothing was written, and the event is still waiting its turn.
        $this->assertNull($event->fresh()->description);
        $this->assertTrue($event->fresh()->needsGeneratedCopy());
    }

    #[Test]
    public function the_command_queues_work_in_batches(): void
    {
        Queue::fake();

        collect(range(1, 25))->each(fn (int $i) => $this->event(['title' => "Gig {$i}"]));

        config()->set('ingest.ai.batch_size', 10);

        $this->artisan('ingest:copy', ['--limit' => 25])->assertSuccessful();

        Queue::assertPushed(GenerateEventCopyJob::class, 3);
    }

    #[Test]
    public function the_command_skips_locked_events(): void
    {
        Queue::fake();

        $this->event(['import_locked' => true]);

        $this->artisan('ingest:copy')->assertSuccessful();

        Queue::assertNothingPushed();
    }

    private function fakeReply(int $id, string $description): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'events' => [[
                                'id' => $id,
                                'description' => $description,
                                'meta_description' => 'A live show in Sydney.',
                            ]],
                        ], JSON_THROW_ON_ERROR),
                    ],
                ]],
            ]),
        ]);
    }

    private function event(array $overrides = []): Event
    {
        $venue = Venue::query()->firstOrCreate(
            ['slug' => 'enmore-theatre'],
            ['name' => 'Enmore Theatre', 'suburb' => 'Newtown', 'status' => 'published'],
        );

        return Event::factory()->create(array_merge([
            'title' => 'Courtney Barnett',
            'venue_id' => $venue->id,
            'description' => null,
            'meta_description' => null,
            'start_datetime' => now()->addDays(3)->setTime(20, 0),
            'ingest_source_id' => IngestSource::factory()->create()->id,
        ], $overrides));
    }
}
