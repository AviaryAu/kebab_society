<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SourceTier;
use App\Enums\SourceTrust;
use App\Models\IngestSource;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<IngestSource>
 */
class IngestSourceFactory extends Factory
{
    protected $model = IngestSource::class;

    public function definition(): array
    {
        $name = Str::title($this->faker->unique()->words(2, true));

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'adapter' => 'ticketmaster',
            'tier' => SourceTier::Api,
            'trust' => SourceTrust::Licensed,
            'endpoint' => 'https://example.test/api/events',
            'website' => 'https://example.test',
            'frequency_minutes' => 360,
            'rate_limit_per_minute' => 30,
            'auto_publish' => true,
            'allow_image_import' => true,
            'is_enabled' => true,
            'default_category_slug' => 'music',
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (): array => ['is_enabled' => false]);
    }

    /**
     * An editorial lister: facts and a citation only, and always reviewed.
     */
    public function editorial(): static
    {
        return $this->state(fn (): array => [
            'tier' => SourceTier::Editorial,
            'trust' => SourceTrust::Signal,
            'auto_publish' => false,
            'allow_image_import' => false,
        ]);
    }

    public function venueDirect(): static
    {
        return $this->state(fn (): array => [
            'tier' => SourceTier::Structured,
            'trust' => SourceTrust::Verified,
        ]);
    }

    public function neverRun(): static
    {
        return $this->state(fn (): array => ['last_run_at' => null]);
    }
}
