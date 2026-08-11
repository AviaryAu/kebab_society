<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    protected $model = Page::class;

    public function definition(): array
    {
        $title = Str::title($this->faker->unique()->words(4, true));

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'type' => 'guide',
            'excerpt' => $this->faker->sentence(12),
            'body' => '<p>'.$this->faker->paragraph().'</p>',
            'status' => 'published',
            'published_at' => now()->subDay(),
            'featured' => false,
            'sort_order' => 0,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['status' => 'draft', 'published_at' => null]);
    }

    public function standalone(): static
    {
        return $this->state(fn (): array => ['type' => 'page']);
    }
}
