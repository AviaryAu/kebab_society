<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class KebabEmergencyTest extends TestCase
{
    use RefreshDatabase;

    /** Sydney Town Hall, give or take. */
    private const LATITUDE = -33.8731;

    private const LONGITUDE = 151.2065;

    #[Test]
    public function it_returns_the_closest_open_kebabs_first(): void
    {
        Restaurant::factory()->alwaysOpen()->create([
            'name' => 'Far Away',
            'latitude' => -33.95,
            'longitude' => 151.15,
        ]);

        Restaurant::factory()->alwaysOpen()->create([
            'name' => 'Just Around The Corner',
            'latitude' => -33.8740,
            'longitude' => 151.2070,
        ]);

        $response = $this->getJson(sprintf(
            '/api/kebab-emergency?latitude=%s&longitude=%s&limit=2',
            self::LATITUDE,
            self::LONGITUDE,
        ))->assertOk();

        $response->assertJsonPath('any_open', true);
        $response->assertJsonPath('results.0.name', 'Just Around The Corner');
        $this->assertGreaterThan(
            $response->json('results.0.distance_km'),
            $response->json('results.1.distance_km'),
        );
    }

    #[Test]
    public function it_still_answers_when_nothing_is_trading(): void
    {
        Restaurant::factory()->closed()->create(['name' => 'Shut']);

        $this->getJson(sprintf('/api/kebab-emergency?latitude=%s&longitude=%s', self::LATITUDE, self::LONGITUDE))
            ->assertOk()
            ->assertJsonPath('any_open', false)
            ->assertJsonPath('results.0.name', 'Shut');
    }

    #[Test]
    public function it_counts_how_many_kebabs_are_within_one_kilometre(): void
    {
        Restaurant::factory()->alwaysOpen()->create(['latitude' => -33.8740, 'longitude' => 151.2070]);
        Restaurant::factory()->alwaysOpen()->create(['latitude' => -34.05, 'longitude' => 151.15]);

        $this->getJson(sprintf('/api/kebab-emergency?latitude=%s&longitude=%s', self::LATITUDE, self::LONGITUDE))
            ->assertOk()
            ->assertJsonPath('within_one_km', 1);
    }

    #[Test]
    public function coordinates_are_required_and_validated(): void
    {
        $this->getJson('/api/kebab-emergency')->assertStatus(422);
        $this->getJson('/api/kebab-emergency?latitude=999&longitude=999')->assertStatus(422);
    }
}
