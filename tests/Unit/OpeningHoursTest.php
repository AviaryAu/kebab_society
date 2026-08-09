<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\OpeningHours;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OpeningHoursTest extends TestCase
{
    private function hours(string $open, string $close): OpeningHours
    {
        $schedule = [];

        foreach (OpeningHours::DAYS as $day) {
            $schedule[$day] = [['open' => $open, 'close' => $close]];
        }

        return OpeningHours::fromArray($schedule);
    }

    #[Test]
    public function it_reports_open_during_trading_hours(): void
    {
        $hours = $this->hours('10:00', '22:00');

        $this->assertTrue($hours->isOpenAt(CarbonImmutable::parse('2026-03-04 12:00')));
        $this->assertFalse($hours->isOpenAt(CarbonImmutable::parse('2026-03-04 09:59')));
        $this->assertFalse($hours->isOpenAt(CarbonImmutable::parse('2026-03-04 22:00')));
    }

    #[Test]
    public function it_handles_sessions_that_run_past_midnight(): void
    {
        $hours = $this->hours('11:00', '03:00');

        // 1am belongs to the session that opened the previous day.
        $this->assertTrue($hours->isOpenAt(CarbonImmutable::parse('2026-03-04 01:00')));
        $this->assertFalse($hours->isOpenAt(CarbonImmutable::parse('2026-03-04 04:00')));
        $this->assertTrue($hours->isOpenAt(CarbonImmutable::parse('2026-03-04 23:30')));
    }

    #[Test]
    public function it_reports_the_closing_time_of_the_current_session(): void
    {
        $hours = $this->hours('11:00', '03:00');
        $closing = $hours->closingTime(CarbonImmutable::parse('2026-03-04 23:30'));

        $this->assertNotNull($closing);
        $this->assertSame('2026-03-05 03:00', $closing->format('Y-m-d H:i'));
    }

    #[Test]
    public function it_reports_the_next_opening_time_when_closed(): void
    {
        $hours = $this->hours('10:00', '22:00');
        $next = $hours->nextOpeningTime(CarbonImmutable::parse('2026-03-04 23:00'));

        $this->assertNotNull($next);
        $this->assertSame('2026-03-05 10:00', $next->format('Y-m-d H:i'));
    }

    #[Test]
    public function it_identifies_late_night_trading(): void
    {
        $this->assertTrue($this->hours('11:00', '03:00')->tradesLateNight());
        $this->assertTrue($this->hours('00:00', '24:00')->tradesLateNight());
        $this->assertFalse($this->hours('10:00', '22:00')->tradesLateNight());
    }

    #[Test]
    public function an_empty_schedule_is_never_open(): void
    {
        $hours = OpeningHours::fromArray(null);

        $this->assertTrue($hours->isEmpty());
        $this->assertFalse($hours->isOpenAt(CarbonImmutable::parse('2026-03-04 12:00')));
        $this->assertNull($hours->nextOpeningTime(CarbonImmutable::parse('2026-03-04 12:00')));
    }

    #[Test]
    public function it_ignores_malformed_sessions(): void
    {
        $hours = OpeningHours::fromArray([
            'mon' => [['open' => '10:00'], 'nonsense', ['open' => '11:00', 'close' => '15:00']],
        ]);

        $this->assertSame([['open' => '11:00', 'close' => '15:00']], $hours->toArray()['mon']);
    }
}
