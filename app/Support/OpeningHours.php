<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Weekly trading hours for a restaurant.
 *
 * Stored as JSON keyed by short day name, each holding a list of
 * {open, close} pairs in "HH:MM" 24 hour time. A close time that is less than
 * or equal to the open time means the session runs past midnight, which is the
 * normal state of affairs for a Sydney kebab shop.
 */
final readonly class OpeningHours
{
    public const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    private const MINUTES_PER_DAY = 1440;

    /**
     * @param  array<string, array<int, array{open: string, close: string}>>  $schedule
     */
    private function __construct(public array $schedule) {}

    /**
     * @param  array<string, mixed>|null  $schedule
     */
    public static function fromArray(?array $schedule): self
    {
        $normalised = [];

        foreach (self::DAYS as $day) {
            $sessions = $schedule[$day] ?? [];

            if (! is_array($sessions)) {
                continue;
            }

            foreach ($sessions as $session) {
                if (! is_array($session) || ! isset($session['open'], $session['close'])) {
                    continue;
                }

                $normalised[$day][] = [
                    'open' => self::normaliseTime((string) $session['open']),
                    'close' => self::normaliseTime((string) $session['close']),
                ];
            }
        }

        return new self($normalised);
    }

    public function isEmpty(): bool
    {
        return $this->schedule === [];
    }

    public function isOpenAt(CarbonInterface $moment): bool
    {
        return $this->currentSession($moment) !== null;
    }

    /**
     * The moment the current trading session ends, or null when closed.
     */
    public function closingTime(CarbonInterface $moment): ?CarbonImmutable
    {
        $session = $this->currentSession($moment);

        return $session === null ? null : $session['end'];
    }

    /**
     * The next moment the restaurant opens, searching up to a week ahead.
     */
    public function nextOpeningTime(CarbonInterface $moment): ?CarbonImmutable
    {
        $from = CarbonImmutable::instance($moment);
        $earliest = null;

        for ($offset = 0; $offset <= 7; $offset++) {
            $day = $from->addDays($offset)->startOfDay();

            foreach ($this->schedule[self::dayKey($day)] ?? [] as $session) {
                $start = $day->addMinutes(self::toMinutes($session['open']));

                if ($start->greaterThan($from) && ($earliest === null || $start->lessThan($earliest))) {
                    $earliest = $start;
                }
            }

            if ($earliest !== null) {
                return $earliest;
            }
        }

        return null;
    }

    /**
     * Does this restaurant trade through the Society's late-night hour?
     */
    public function tradesLateNight(): bool
    {
        $hour = (int) config('kebab.late_night_hour', 0);
        $reference = CarbonImmutable::create(2024, 1, 1, $hour, 0, 0); // a Monday

        foreach (range(0, 6) as $offset) {
            if ($this->isOpenAt($reference->addDays($offset))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, array<int, array{open: string, close: string}>>
     */
    public function toArray(): array
    {
        return $this->schedule;
    }

    /**
     * @return array{start: CarbonImmutable, end: CarbonImmutable}|null
     */
    private function currentSession(CarbonInterface $moment): ?array
    {
        $at = CarbonImmutable::instance($moment);

        // A session that began yesterday may still be running, so look back a day.
        foreach ([0, -1] as $offset) {
            $day = $at->addDays($offset)->startOfDay();

            foreach ($this->schedule[self::dayKey($day)] ?? [] as $session) {
                $openMinutes = self::toMinutes($session['open']);
                $closeMinutes = self::toMinutes($session['close']);

                if ($closeMinutes <= $openMinutes) {
                    $closeMinutes += self::MINUTES_PER_DAY;
                }

                $start = $day->addMinutes($openMinutes);
                $end = $day->addMinutes($closeMinutes);

                if ($at->greaterThanOrEqualTo($start) && $at->lessThan($end)) {
                    return ['start' => $start, 'end' => $end];
                }
            }
        }

        return null;
    }

    private static function dayKey(CarbonInterface $moment): string
    {
        return self::DAYS[$moment->dayOfWeekIso - 1];
    }

    private static function normaliseTime(string $time): string
    {
        if (preg_match('/^(\d{1,2}):(\d{2})$/', trim($time), $matches) !== 1) {
            throw new InvalidArgumentException("Invalid opening hours time [{$time}].");
        }

        $hours = (int) $matches[1];
        $minutes = (int) $matches[2];

        if ($hours > 24 || $minutes > 59 || ($hours === 24 && $minutes !== 0)) {
            throw new InvalidArgumentException("Invalid opening hours time [{$time}].");
        }

        return sprintf('%02d:%02d', $hours, $minutes);
    }

    private static function toMinutes(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return $hours * 60 + $minutes;
    }
}
