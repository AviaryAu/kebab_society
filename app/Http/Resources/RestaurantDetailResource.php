<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Restaurant;
use App\Support\OpeningHours;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * The full restaurant payload used by /kebabs/{slug}.
 *
 * @mixin Restaurant
 */
class RestaurantDetailResource extends RestaurantPreviewResource
{
    private const DAY_LABELS = [
        'mon' => 'Monday',
        'tue' => 'Tuesday',
        'wed' => 'Wednesday',
        'thu' => 'Thursday',
        'fri' => 'Friday',
        'sat' => 'Saturday',
        'sun' => 'Sunday',
    ];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return parent::toArray($request) + [
            'description' => $this->description,
            'website' => $this->website,
            'editorial_note' => $this->editorial_note,
            'verification_status' => $this->verification_status->value,
            'verification_label' => $this->verification_status->label(),
            'society_approved_at' => $this->society_approved_at?->toDateString(),
            'data_source' => $this->data_source->value,
            'data_source_label' => $this->data_source->label(),
            'google_data_updated_at' => $this->google_data_updated_at?->diffForHumans(),
            'rating_breakdown' => $this->rating_breakdown,
            'weekly_hours' => $this->weeklyHours(),
        ];
    }

    /**
     * @return array<int, array{day: string, label: string, sessions: array<int, string>, is_today: bool}>
     */
    private function weeklyHours(): array
    {
        $schedule = $this->hours()->toArray();
        $today = CarbonImmutable::now();
        $todayKey = OpeningHours::DAYS[$today->dayOfWeekIso - 1];

        return array_map(function (string $day) use ($schedule, $todayKey): array {
            $sessions = array_map(
                fn (array $session): string => $this->formatTime($session['open']).' – '.$this->formatTime($session['close']),
                $schedule[$day] ?? [],
            );

            return [
                'day' => $day,
                'label' => self::DAY_LABELS[$day],
                'sessions' => $sessions === [] ? ['Closed'] : $sessions,
                'is_today' => $day === $todayKey,
            ];
        }, OpeningHours::DAYS);
    }

    private function formatTime(string $time): string
    {
        if ($time === '24:00') {
            return 'midnight';
        }

        return CarbonImmutable::createFromFormat('H:i', $time)->format('g:ia');
    }
}
