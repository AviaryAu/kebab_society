<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\DataSource;
use App\Enums\RestaurantStatus;
use App\Enums\VerificationStatus;
use App\Models\KebabStyle;
use App\Models\Restaurant;
use App\Models\Suburb;
use App\Services\KebabScoringService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Seeds the Sydney register from the researched dataset in
 * database/data/sydney-kebab-register.json.
 *
 * These are real businesses. Nothing about them is invented here:
 *
 *  - Names, addresses and Google ratings come from the research dataset.
 *  - Coordinates were geocoded once, at build time, by scripts/geocode_seed.py
 *    unless the enriched dataset already included exact coordinates.
 *  - The Kebab Society Rating is derived by KebabScoringService, never typed in.
 *  - Trading hours are imported only when the research dataset provided
 *    explicit hours. Unknown hours remain blank rather than guessed.
 *  - No restaurant is marked Society Certified until an editor has actually
 *    been. That happens through the admin, not through a seeder.
 */
class RestaurantSeeder extends Seeder
{
    private const REGISTER = 'data/sydney-kebab-register.json';

    public function __construct(private readonly KebabScoringService $scoring) {}

    public function run(): void
    {
        $register = $this->register();

        $this->seedSuburbs($register['suburbs']);

        $suburbs = Suburb::query()->pluck('id', 'slug');
        $styles = KebabStyle::query()->pluck('id', 'slug');

        foreach ($register['restaurants'] as $data) {
            $suburbId = $suburbs[$data['suburb_slug']] ?? null;

            if ($suburbId === null) {
                continue;
            }

            $restaurant = Restaurant::query()->updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'name' => $data['name'],
                    'address_line' => $data['address_line'],
                    'suburb_id' => $suburbId,
                    'postcode' => $data['postcode'],
                    'latitude' => $data['latitude'],
                    'longitude' => $data['longitude'],
                    'location_precision' => $data['location_precision'],
                    'google_rating' => $data['google_rating'],
                    'google_review_count' => $data['google_review_count'],
                    'google_place_id' => $data['google_place_id'] ?? null,
                    'google_data_updated_at' => $this->googleUpdatedAt($data),
                    'opening_hours' => $data['opening_hours'] ?? null,
                    'status' => $data['research_status'] === 'needs_current_verification'
                        ? RestaurantStatus::Draft
                        : RestaurantStatus::Published,
                    'verification_status' => VerificationStatus::from($data['verification_status']),
                    'data_source' => DataSource::ImportedDataset,
                    'research_category' => $data['category'] ?? null,
                    'research_status' => $data['research_status'] ?? null,
                    'research_source' => $data['source'] ?? null,
                    'research_last_verified' => $data['data_last_verified'] ?? null,
                    'research_verification_notes' => $data['verification_notes'] ?? null,
                ],
            );

            $restaurant->kebabStyles()->syncWithoutDetaching(
                collect($data['styles'])
                    ->filter(fn (string $slug): bool => isset($styles[$slug]))
                    ->values()
                    ->mapWithKeys(fn (string $slug, int $index): array => [
                        $styles[$slug] => ['is_signature' => $index === 0],
                    ])
                    ->all()
            );

            $this->scoring->apply($restaurant);
        }
    }

    private function seedSuburbs(array $suburbs): void
    {
        foreach ($suburbs as $suburb) {
            Suburb::query()->updateOrCreate(
                ['slug' => $suburb['slug']],
                [
                    'name' => $suburb['name'],
                    'postcode' => $suburb['postcode'],
                    'region' => $suburb['region'],
                    'latitude' => $suburb['latitude'],
                    'longitude' => $suburb['longitude'],
                ],
            );
        }
    }

    /**
     * @return array{suburbs: array<int, array<string, mixed>>, restaurants: array<int, array<string, mixed>>}
     */
    private function register(): array
    {
        $path = database_path(self::REGISTER);

        if (! is_file($path)) {
            throw new RuntimeException(
                "Register not found at {$path}. Run: python3 scripts/geocode_seed.py"
            );
        }

        return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function googleUpdatedAt(array $data): ?Carbon
    {
        if (! empty($data['data_last_verified'])) {
            return Carbon::parse((string) $data['data_last_verified'])->startOfDay();
        }

        return $data['google_rating'] !== null ? Carbon::now() : null;
    }
}
