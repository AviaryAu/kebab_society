<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Suburb;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Suburb>
 */
class SuburbFactory extends Factory
{
    protected $model = Suburb::class;

    public function definition(): array
    {
        $name = fake()->unique()->streetName();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'postcode' => (string) fake()->numberBetween(2000, 2999),
            'region' => fake()->randomElement(['Inner West', 'South West', 'Eastern Suburbs', 'Western Sydney']),
            'latitude' => fake()->randomFloat(7, -34.1, -33.7),
            'longitude' => fake()->randomFloat(7, 150.9, 151.3),
        ];
    }
}
