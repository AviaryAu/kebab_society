<?php

declare(strict_types=1);

namespace Tests\Feature\Ingest;

use App\Models\Event;
use App\Models\IngestSource;
use App\Models\Venue;
use App\Services\Ingest\CopyWriter;
use App\Services\Ingest\Exceptions\CopyWriterThrottled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CopyWriterTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = 'api.groq.com/openai/v1/chat/completions';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config()->set('ingest.ai.enabled', true);
        config()->set('ingest.ai.key', 'test-key');
        config()->set('ingest.ai.base_url', 'https://api.groq.com/openai/v1');
    }

    #[Test]
    public function it_writes_copy_from_the_facts(): void
    {
        $event = $this->event();

        $this->fakeReply([[
            'id' => $event->id,
            'description' => 'A late-night set at the Enmore, all fuzzed guitars and deadpan asides.',
            'meta_description' => 'Courtney Barnett at the Enmore Theatre, Newtown.',
        ]]);

        $copy = app(CopyWriter::class)->generate($event);

        $this->assertStringContainsString('Enmore', $copy->description);
        $this->assertSame('Courtney Barnett at the Enmore Theatre, Newtown.', $copy->metaDescription);
        $this->assertNotSame('template', $copy->model);
    }

    #[Test]
    public function it_sends_only_facts_from_an_editorial_source(): void
    {
        $source = IngestSource::factory()->editorial()->create();
        $event = $this->event(['ingest_source_id' => $source->id]);

        $source->imports()->create([
            'external_id' => 'x1',
            'fingerprint' => str_repeat('a', 64),
            'event_id' => $event->id,
            'raw_payload' => ['info' => 'A gorgeous, aching set from a generational songwriter.'],
        ]);

        $this->fakeReply([]);

        app(CopyWriter::class)->generate($event);

        Http::assertSent(function ($request): bool {
            $prompt = $request->data()['messages'][1]['content'];

            return ! str_contains($prompt, 'gorgeous')
                && str_contains($prompt, 'Courtney Barnett');
        });
    }

    #[Test]
    public function it_may_use_an_organiser_note_from_a_licensed_source(): void
    {
        $source = IngestSource::factory()->create(['allow_image_import' => true]);
        $event = $this->event(['ingest_source_id' => $source->id]);

        $source->imports()->create([
            'external_id' => 'x1',
            'fingerprint' => str_repeat('b', 64),
            'event_id' => $event->id,
            'raw_payload' => ['info' => 'No latecomers admitted.'],
        ]);

        $this->fakeReply([]);

        app(CopyWriter::class)->generate($event);

        Http::assertSent(
            fn ($request): bool => str_contains(
                $request->data()['messages'][1]['content'],
                'No latecomers admitted.',
            ),
        );
    }

    #[Test]
    public function it_batches_events_into_one_request(): void
    {
        $events = collect(range(1, 8))->map(fn (int $i): Event => $this->event(['title' => "Gig {$i}"]));

        $this->fakeReply($events->map(fn (Event $e): array => [
            'id' => $e->id,
            'description' => 'A perfectly reasonable description of a live show in Sydney.',
            'meta_description' => 'A live show.',
        ])->all());

        $copy = app(CopyWriter::class)->generateMany($events);

        $this->assertCount(8, $copy);
        Http::assertSentCount(1);
    }

    #[Test]
    public function it_falls_back_to_template_copy_when_the_provider_fails(): void
    {
        $event = $this->event();

        Http::fake([self::ENDPOINT => Http::response('upstream on fire', 500)]);

        $copy = app(CopyWriter::class)->generate($event);

        $this->assertSame('template', $copy->model);
        $this->assertStringContainsString('Enmore Theatre', $copy->description);
    }

    #[Test]
    public function it_falls_back_when_the_model_returns_nonsense(): void
    {
        $event = $this->event();

        Http::fake([
            self::ENDPOINT => Http::response([
                'choices' => [['message' => ['content' => 'not json at all']]],
            ]),
        ]);

        $this->assertSame('template', app(CopyWriter::class)->generate($event)->model);
    }

    #[Test]
    public function it_discards_copy_for_an_event_it_never_asked_about(): void
    {
        $event = $this->event();

        $this->fakeReply([[
            'id' => 999999,
            'description' => 'Copy for an event that does not exist.',
            'meta_description' => 'Nope.',
        ]]);

        $this->assertSame('template', app(CopyWriter::class)->generate($event)->model);
    }

    #[Test]
    public function it_rejects_copy_containing_a_link(): void
    {
        $event = $this->event();

        $this->fakeReply([[
            'id' => $event->id,
            'description' => 'Tickets are available at https://example.com/buy right now.',
            'meta_description' => 'Tickets.',
        ]]);

        $this->assertSame('template', app(CopyWriter::class)->generate($event)->model);
    }

    #[Test]
    public function it_strips_markup_rather_than_discarding_usable_copy(): void
    {
        $event = $this->event();

        $this->fakeReply([[
            'id' => $event->id,
            'description' => 'A set at the <strong>Enmore</strong> with support to be announced.',
            'meta_description' => 'A set.',
        ]]);

        $copy = app(CopyWriter::class)->generate($event);

        // The sentence is fine; only the markup was unwanted. Throwing away a
        // request we already paid for would be worse than tidying it.
        $this->assertSame('A set at the Enmore with support to be announced.', $copy->description);
        $this->assertStringNotContainsString('<', $copy->description);
    }

    #[Test]
    public function it_rejects_copy_far_longer_than_asked_for(): void
    {
        $event = $this->event();

        $this->fakeReply([[
            'id' => $event->id,
            'description' => str_repeat('word ', 200),
            'meta_description' => 'Long.',
        ]]);

        $this->assertSame('template', app(CopyWriter::class)->generate($event)->model);
    }

    #[Test]
    public function a_meta_description_is_trimmed_to_the_limit(): void
    {
        $event = $this->event();

        $this->fakeReply([[
            'id' => $event->id,
            'description' => 'A good short description of a gig.',
            'meta_description' => str_repeat('a', 400),
        ]]);

        $copy = app(CopyWriter::class)->generate($event);

        $this->assertLessThanOrEqual(155, mb_strlen($copy->metaDescription));
    }

    #[Test]
    public function throttling_is_surfaced_so_the_queue_can_wait(): void
    {
        $event = $this->event();

        Http::fake([
            self::ENDPOINT => Http::response('rate limited', 429, ['retry-after' => '42']),
        ]);

        try {
            app(CopyWriter::class)->generateMany(collect([$event]));
            $this->fail('Expected CopyWriterThrottled.');
        } catch (CopyWriterThrottled $e) {
            $this->assertSame(42, $e->retryAfter);
        }
    }

    #[Test]
    public function it_stops_asking_once_the_daily_budget_is_gone(): void
    {
        config()->set('ingest.ai.daily_request_budget', 2);

        $writer = app(CopyWriter::class);
        $this->fakeReply([]);

        $writer->generate($this->event());
        $writer->generate($this->event(['title' => 'Second']));

        $this->assertSame(0, $writer->budgetRemaining());

        Http::assertSentCount(2);

        // The third falls back rather than asking again.
        $copy = $writer->generate($this->event(['title' => 'Third']));

        $this->assertSame('template', $copy->model);
        Http::assertSentCount(2);
    }

    #[Test]
    public function it_uses_the_fast_model_for_the_long_tail(): void
    {
        config()->set('ingest.ai.model_primary', 'big-model');
        config()->set('ingest.ai.model_bulk', 'fast-model');

        $this->fakeReply([]);

        app(CopyWriter::class)->generate($this->event([
            'featured' => false,
            'start_datetime' => now()->addMonths(4),
        ]));

        Http::assertSent(fn ($request): bool => $request->data()['model'] === 'fast-model');
    }

    #[Test]
    public function it_uses_the_better_model_for_something_happening_soon(): void
    {
        config()->set('ingest.ai.model_primary', 'big-model');
        config()->set('ingest.ai.model_bulk', 'fast-model');

        $this->fakeReply([]);

        app(CopyWriter::class)->generate($this->event(['start_datetime' => now()->addDays(2)]));

        Http::assertSent(fn ($request): bool => $request->data()['model'] === 'big-model');
    }

    #[Test]
    public function it_does_nothing_without_an_api_key(): void
    {
        config()->set('ingest.ai.key', null);

        Http::fake();

        $copy = app(CopyWriter::class)->generate($this->event());

        $this->assertSame('template', $copy->model);
        Http::assertNothingSent();
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     */
    private function fakeReply(array $events): void
    {
        Http::fake([
            self::ENDPOINT => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode(['events' => $events], JSON_THROW_ON_ERROR),
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
            'suburb' => 'Newtown',
            'category_slug' => 'music',
            'price' => '$89',
            'start_datetime' => now()->addDays(3)->setTime(20, 0),
            'ingest_source_id' => IngestSource::factory()->create()->id,
        ], $overrides));
    }
}
