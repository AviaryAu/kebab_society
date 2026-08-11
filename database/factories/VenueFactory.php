<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Venue>
 */
class VenueFactory extends Factory
{
    protected $model = Venue::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->company().' Hall';

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'suburb' => $this->faker->randomElement(['Newtown', 'Surry Hills', 'Marrickville', 'Sydney CBD']),
            'address' => $this->faker->streetAddress().', Sydney NSW',
            'description' => $this->faker->sentence(12),
            'website' => 'https://example.com/'.Str::slug($name),
            'social_url' => 'https://instagram.com/'.Str::slug($name),
            'transport' => 'Short walk from the station.',
            'latitude' => $this->faker->randomFloat(7, -34.0, -33.7),
            'longitude' => $this->faker->randomFloat(7, 151.0, 151.3),
            'status' => 'published',
            'featured' => false,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['status' => 'draft']);
    }
}
