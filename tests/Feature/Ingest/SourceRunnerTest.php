<?php

declare(strict_types=1);

namespace Tests\Feature\Ingest;

use App\Models\IngestSource;
use App\Services\Ingest\Contracts\SourceAdapter;
use App\Services\Ingest\EventDraft;
use App\Services\Ingest\SourceRunner;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class SourceRunnerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ingest.adapters.fake', FakeAdapter::class);
        FakeAdapter::$items = [];
        FakeAdapter::$throwOnFetch = false;
        FakeAdapter::$failNormaliseFor = null;
    }

    #[Test]
    public function it_records_a_run_with_counters(): void
    {
        FakeAdapter::$items = [
            ['id' => 'a', 'title' => 'One'],
            ['id' => 'b', 'title' => 'Two'],
        ];

        $run = $this->runner()->run(
            $this->source(),
            fn (): string => 'created',
        );

        $this->assertSame('success', $run->status);
        $this->assertSame(2, $run->items_seen);
        $this->assertSame(2, $run->items_created);
        $this->assertNotNull($run->finished_at);
        $this->assertNotNull($run->duration_ms);
    }

    #[Test]
    public function a_past_event_is_skipped_before_it_reaches_the_handler(): void
    {
        FakeAdapter::$items = [
            ['id' => 'old', 'title' => 'Last year', 'starts_at' => '2020-01-01 20:00'],
            ['id' => 'new', 'title' => 'Next year'],
        ];

        $handled = 0;

        $run = $this->runner()->run($this->source(), function () use (&$handled): string {
            $handled++;

            return 'created';
        });

        $this->assertSame(1, $handled);
        $this->assertSame(1, $run->items_skipped);
        $this->assertSame(1, $run->items_created);
    }

    #[Test]
    public function one_bad_item_does_not_abandon_the_run(): void
    {
        FakeAdapter::$items = [
            ['id' => 'a', 'title' => 'Fine'],
            ['id' => 'poison', 'title' => 'Broken'],
            ['id' => 'c', 'title' => 'Also fine'],
        ];
        FakeAdapter::$failNormaliseFor = 'poison';

        $run = $this->runner()->run($this->source(), fn (): string => 'created');

        $this->assertSame(3, $run->items_seen);
        $this->assertSame(2, $run->items_created);
        $this->assertSame(1, $run->items_failed);
        $this->assertSame('partial', $run->status);
    }

    #[Test]
    public function the_limit_stops_the_walk_early(): void
    {
        FakeAdapter::$items = array_map(
            fn (int $i): array => ['id' => "e{$i}", 'title' => "Event {$i}"],
            range(1, 50),
        );

        $run = $this->runner()->run($this->source(), fn (): string => 'created', limit: 5);

        $this->assertSame(5, $run->items_seen);
    }

    #[Test]
    public function a_failed_fetch_marks_the_run_and_increments_source_failures(): void
    {
        FakeAdapter::$throwOnFetch = true;
        $source = $this->source();

        $run = $this->runner()->run($source, fn (): string => 'created');

        $this->assertSame('failed', $run->status);
        $this->assertStringContainsString('upstream exploded', (string) $run->error);
        $this->assertSame(1, $source->fresh()->consecutive_failures);
    }

    #[Test]
    public function a_source_that_keeps_failing_is_disabled_rather_than_left_knocking(): void
    {
        FakeAdapter::$throwOnFetch = true;
        $source = $this->source();

        foreach (range(1, 5) as $ignored) {
            $this->runner()->run($source->fresh(), fn (): string => 'created');
        }

        $source = $source->fresh();

        $this->assertFalse($source->is_enabled);
        $this->assertSame(5, $source->consecutive_failures);
    }

    #[Test]
    public function a_successful_run_clears_previous_failures(): void
    {
        $source = $this->source();
        $source->forceFill(['consecutive_failures' => 3])->save();

        FakeAdapter::$items = [['id' => 'a', 'title' => 'One']];

        $this->runner()->run($source, fn (): string => 'created');

        $source = $source->fresh();

        $this->assertSame(0, $source->consecutive_failures);
        $this->assertNotNull($source->last_success_at);
    }

    #[Test]
    public function a_dry_run_is_flagged_on_the_record(): void
    {
        FakeAdapter::$items = [['id' => 'a', 'title' => 'One']];

        $run = $this->runner()->run($this->source(), fn (): string => 'skipped', dryRun: true);

        $this->assertTrue($run->dry_run);
    }

    private function runner(): SourceRunner
    {
        return app(SourceRunner::class);
    }

    private function source(): IngestSource
    {
        return IngestSource::factory()->create(['adapter' => 'fake']);
    }
}

/**
 * A source that answers from an array, so the runner's bookkeeping can be
 * tested without a network or a real publisher.
 */
class FakeAdapter implements SourceAdapter
{
    /** @var array<int, array<string, mixed>> */
    public static array $items = [];

    public static bool $throwOnFetch = false;

    public static ?string $failNormaliseFor = null;

    public function fetch(IngestSource $source): iterable
    {
        if (self::$throwOnFetch) {
            throw new RuntimeException('upstream exploded');
        }

        yield from self::$items;
    }

    public function normalise(IngestSource $source, array $item): ?EventDraft
    {
        if (self::$failNormaliseFor !== null && $item['id'] === self::$failNormaliseFor) {
            throw new RuntimeException('unparseable item');
        }

        return new EventDraft(
            externalId: (string) $item['id'],
            title: (string) $item['title'],
            startsAt: CarbonImmutable::parse($item['starts_at'] ?? '+30 days'),
            venueName: 'Enmore Theatre',
            suburb: 'Newtown',
        );
    }

    public function requestCount(): int
    {
        return 1;
    }
}
