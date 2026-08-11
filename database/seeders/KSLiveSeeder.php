<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Page;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Lifts the sample Keep Sydney Live content out of config/kslive.php and into
 * the database, where the admin can edit it.
 */
class KSLiveSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('kslive.venues', []) as $venue) {
            Venue::query()->updateOrCreate(
                ['slug' => $venue['slug']],
                [
                    'name' => $venue['name'],
                    'suburb' => $venue['suburb'],
                    'address' => $venue['address'] ?? null,
                    'description' => $venue['description'] ?? null,
                    'image' => $venue['image'] ?? null,
                    'website' => $venue['website'] ?? null,
                    'social_url' => $venue['social_url'] ?? null,
                    'transport' => $venue['transport'] ?? null,
                    'latitude' => $venue['latitude'] ?? null,
                    'longitude' => $venue['longitude'] ?? null,
                    'status' => 'published',
                ],
            );
        }

        $venues = Venue::query()->pluck('id', 'slug');

        foreach (config('kslive.events', []) as $event) {
            Event::query()->updateOrCreate(
                ['slug' => $event['slug']],
                [
                    'title' => $event['title'],
                    'description' => $event['description'] ?? null,
                    'start_datetime' => CarbonImmutable::parse($event['start_datetime']),
                    'end_datetime' => isset($event['end_datetime'])
                        ? CarbonImmutable::parse($event['end_datetime'])
                        : null,
                    'venue_id' => $venues[$event['venue_slug'] ?? ''] ?? null,
                    'suburb' => $event['suburb'],
                    'category_slug' => $event['category_slug'],
                    'image' => $event['image'] ?? null,
                    'price' => $event['price'] ?? null,
                    'ticket_url' => $event['ticket_url'] ?? null,
                    'latitude' => $event['latitude'] ?? null,
                    'longitude' => $event['longitude'] ?? null,
                    'featured' => (bool) ($event['featured'] ?? false),
                    'status' => 'published',
                ],
            );
        }

        foreach (config('kslive.guides', []) as $index => $guide) {
            Page::query()->updateOrCreate(
                ['slug' => $guide['slug']],
                [
                    'title' => $guide['title'],
                    'type' => 'guide',
                    'excerpt' => $guide['excerpt'] ?? null,
                    'body' => '<p>'.e($guide['excerpt'] ?? '').'</p>',
                    'status' => 'published',
                    'published_at' => now()->subDays($index),
                    'sort_order' => $index,
                ],
            );
        }
    }
}
