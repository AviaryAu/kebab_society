<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DataSource;
use App\Enums\RestaurantStatus;
use App\Enums\VerificationStatus;
use App\Models\Restaurant;
use App\Models\Suburb;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Restaurant>
 */
class RestaurantFactory extends Factory
{
    protected $model = Restaurant::class;

    public function definition(): array
    {
        $name = fake()->unique()->company().' Kebabs';

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'address_line' => fake()->streetAddress(),
            'suburb_id' => Suburb::factory(),
            'postcode' => (string) fake()->numberBetween(2000, 2999),
            'latitude' => fake()->randomFloat(7, -34.1, -33.7),
            'longitude' => fake()->randomFloat(7, 150.9, 151.3),
            'phone' => '(02) 9000 0000',
            'website' => null,
            'google_rating' => fake()->randomFloat(1, 3, 5),
            'google_review_count' => fake()->numberBetween(10, 2000),
            'opening_hours' => self::schedule('10:00', '22:00'),
            'price_level' => fake()->numberBetween(1, 3),
            'status' => RestaurantStatus::Published,
            'kebab_rating' => fake()->randomFloat(1, 2, 5),
            'society_rating' => null,
            'society_review_count' => 0,
            'check_in_count' => 0,
            'editorial_adjustment' => 0,
            'verification_status' => VerificationStatus::Unverified,
            'society_approved_at' => null,
            'data_source' => DataSource::SeedData,
        ];
    }

    public function societyApproved(): self
    {
        return $this->state(fn (): array => [
            'verification_status' => VerificationStatus::SocietyCertified,
            'society_approved_at' => now()->subMonth(),
        ]);
    }

    public function lateNight(): self
    {
        return $this->state(fn (): array => [
            'opening_hours' => self::schedule('11:00', '04:00'),
        ]);
    }

    public function closed(): self
    {
        return $this->state(fn (): array => ['opening_hours' => []]);
    }

    public function alwaysOpen(): self
    {
        return $this->state(fn (): array => [
            'opening_hours' => self::schedule('00:00', '24:00'),
        ]);
    }

    /**
     * @return array<string, array<int, array{open: string, close: string}>>
     */
    private static function schedule(string $open, string $close): array
    {
        return collect(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'])
            ->mapWithKeys(fn (string $day): array => [$day => [['open' => $open, 'close' => $close]]])
            ->all();
    }
}
