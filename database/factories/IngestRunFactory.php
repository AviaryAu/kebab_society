<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\IngestRun;
use App\Models\IngestSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IngestRun>
 */
class IngestRunFactory extends Factory
{
    protected $model = IngestRun::class;

    public function definition(): array
    {
        return [
            'ingest_source_id' => IngestSource::factory(),
            'started_at' => now()->subMinutes(5),
            'finished_at' => now()->subMinutes(4),
            'status' => 'success',
            'items_seen' => 10,
            'items_created' => 4,
            'items_updated' => 3,
            'items_skipped' => 3,
            'items_failed' => 0,
            'requests_made' => 2,
            'duration_ms' => 60000,
            'dry_run' => false,
        ];
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => 'failed',
            'error' => 'Connection refused',
            'items_created' => 0,
        ]);
    }

    public function running(): static
    {
        return $this->state(fn (): array => [
            'status' => 'running',
            'finished_at' => null,
            'duration_ms' => null,
        ]);
    }
}
