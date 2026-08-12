<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ImportStatus;
use App\Models\EventImport;
use App\Models\IngestSource;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EventImport>
 */
class EventImportFactory extends Factory
{
    protected $model = EventImport::class;

    public function definition(): array
    {
        $title = Str::title($this->faker->unique()->words(3, true));
        $start = now()->addDays($this->faker->numberBetween(1, 30))->setTime(20, 0);
        $venue = Str::title($this->faker->words(2, true));

        return [
            'ingest_source_id' => IngestSource::factory(),
            'external_id' => $this->faker->unique()->uuid(),
            'source_url' => 'https://example.test/events/'.Str::slug($title),
            'fingerprint' => hash('sha256', Str::slug($title).'|'.Str::slug($venue).'|'.$start->format('Y-m-d')),
            'raw_payload' => ['title' => $title],
            'normalised' => ['title' => $title],
            'proposed_title' => $title,
            'proposed_start' => $start,
            'proposed_venue_name' => $venue,
            'proposed_suburb' => 'Newtown',
            'status' => ImportStatus::Pending,
            'match_confidence' => 0.95,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => ImportStatus::Approved,
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => ImportStatus::Rejected,
            'reviewed_at' => now(),
        ]);
    }

    public function lowConfidence(): static
    {
        return $this->state(fn (): array => ['match_confidence' => 0.4]);
    }
}
