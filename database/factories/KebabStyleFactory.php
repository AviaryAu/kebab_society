<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\KebabStyle;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<KebabStyle>
 */
class KebabStyleFactory extends Factory
{
    protected $model = KebabStyle::class;

    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'group' => 'style',
            'description' => fake()->sentence(),
            'sort_order' => fake()->numberBetween(0, 100),
            'is_filterable' => true,
        ];
    }
}
