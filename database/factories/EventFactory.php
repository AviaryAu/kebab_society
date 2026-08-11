<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Event;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        $title = Str::title($this->faker->unique()->words(3, true));
        $start = now()->addDays($this->faker->numberBetween(1, 20))->setTime(19, 30);

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'description' => $this->faker->sentence(14),
            'start_datetime' => $start,
            'end_datetime' => $start->copy()->addHours(3),
            'venue_id' => Venue::factory(),
            'suburb' => 'Newtown',
            'category_slug' => $this->faker->randomElement(['music', 'comedy', 'nightlife', 'arts']),
            'price' => '$'.$this->faker->numberBetween(10, 90),
            'ticket_url' => 'https://example.com/tickets/'.Str::slug($title),
            'latitude' => $this->faker->randomFloat(7, -34.0, -33.7),
            'longitude' => $this->faker->randomFloat(7, 151.0, 151.3),
            'featured' => false,
            'status' => 'published',
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['status' => 'draft']);
    }

    public function tonight(): static
    {
        return $this->state(fn (): array => [
            'start_datetime' => now('Australia/Sydney')->setTime(20, 0),
            'end_datetime' => now('Australia/Sydney')->setTime(23, 0),
        ]);
    }
}
