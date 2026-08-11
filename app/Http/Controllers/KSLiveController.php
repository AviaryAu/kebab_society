<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Page;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class KSLiveController extends Controller
{
    public function home(): Response
    {
        $events = $this->eventCollection();
        $venues = $this->venueCollection();

        return Inertia::render('Live/Home', [
            'brand' => config('kslive.brand'),
            // A different opening line on every visit.
            'headline' => Arr::random(config('kslive.headlines')),
            'categories' => config('kslive.categories'),
            'tonight' => $this->tonightEvents($events)->take(6)->values(),
            'weekend' => $this->weekendEvents($events)->take(4)->values(),
            'featured' => $events->where('featured', true)->take(3)->values(),
            'neighbourhoods' => collect(config('kslive.neighbourhoods'))->values(),
            'guides' => $this->guideCollection()->take(6)->values(),
            'venues' => $venues->take(4)->values(),
            'mapCounts' => [
                'events' => $events->count(),
                'venues' => $venues->count(),
            ],
        ]);
    }

    public function map(): Response
    {
        $events = $this->eventCollection();
        $venues = $this->venueCollection();

        return Inertia::render('Live/MapExplorer', [
            'map' => config('kslive.map'),
            'items' => $this->mapItems($events, $venues),
        ]);
    }

    public function events(): Response
    {
        return Inertia::render('Live/EventsIndex', [
            'title' => "What's On",
            'description' => 'Discover Sydney events across music, comedy, nightlife, food and culture.',
            'events' => $this->eventCollection()->values(),
        ]);
    }

    public function tonight(): Response
    {
        return Inertia::render('Live/EventsIndex', [
            'title' => 'Tonight',
            'description' => "What's happening across Sydney tonight.",
            'events' => $this->tonightEvents($this->eventCollection())->values(),
        ]);
    }

    public function weekend(): Response
    {
        return Inertia::render('Live/EventsIndex', [
            'title' => 'This Weekend',
            'description' => 'Sydney events happening this weekend.',
            'events' => $this->weekendEvents($this->eventCollection())->values(),
        ]);
    }

    public function category(string $category): Response
    {
        $categories = collect(config('kslive.categories'));
        $selected = $categories->firstWhere('slug', $category);

        abort_if($selected === null, 404);

        return Inertia::render('Live/EventsIndex', [
            'title' => Arr::get($selected, 'name', 'Events'),
            'description' => sprintf('%s events happening across Sydney.', Arr::get($selected, 'name', 'Live')),
            'events' => $this->eventCollection()->where('category_slug', $category)->values(),
        ]);
    }

    public function event(Event $event): Response
    {
        abort_unless($event->status === 'published', 404);

        $event->load('venue');
        $events = $this->eventCollection();
        $selected = $event->toPublicArray();

        return Inertia::render('Live/EventShow', [
            'event' => $selected,
            'venue' => $event->venue?->toPublicArray(),
            'related' => $events
                ->where('category_slug', $selected['category_slug'])
                ->where('slug', '!=', $selected['slug'])
                ->take(3)
                ->values(),
            'nearby' => $events
                ->where('suburb', $selected['suburb'])
                ->where('slug', '!=', $selected['slug'])
                ->take(3)
                ->values(),
        ]);
    }

    public function venues(): Response
    {
        return Inertia::render('Live/VenuesIndex', [
            'venues' => $this->venueCollection()->values(),
        ]);
    }

    public function venue(Venue $venue): Response
    {
        abort_unless($venue->status === 'published', 404);

        return Inertia::render('Live/VenueShow', [
            'venue' => $venue->toPublicArray(),
            'events' => $this->eventCollection()->where('venue_slug', $venue->slug)->values(),
            'nearby' => $this->venueCollection()
                ->where('slug', '!=', $venue->slug)
                ->take(3)
                ->values(),
        ]);
    }

    public function locations(): Response
    {
        $events = $this->eventCollection();

        return Inertia::render('Live/LocationsIndex', [
            'locations' => collect(config('kslive.neighbourhoods'))
                ->map(function (string $slug) use ($events): array {
                    $name = $this->locationNameFromSlug($slug);

                    return [
                        'slug' => $slug,
                        'name' => $name,
                        'count' => $events->where('suburb', $name)->count(),
                    ];
                })
                ->values(),
        ]);
    }

    public function location(string $location): Response
    {
        $name = $this->locationNameFromSlug($location);

        abort_if(! collect(config('kslive.neighbourhoods'))->contains($location), 404);

        return Inertia::render('Live/LocationShow', [
            'location' => [
                'slug' => $location,
                'name' => $name,
            ],
            'events' => $this->eventCollection()->where('suburb', $name)->values(),
            'venues' => $this->venueCollection()->where('suburb', $name)->values(),
        ]);
    }

    public function guides(): Response
    {
        return Inertia::render('Live/GuidesIndex', [
            'guides' => $this->guideCollection()->values(),
        ]);
    }

    public function guide(Page $page): Response
    {
        abort_unless($page->type === 'guide' && $this->isPublished($page), 404);

        return Inertia::render('Live/PageShow', [
            'page' => $page->toPublicArray(),
            'kicker' => 'Guide',
            'related' => $this->guideCollection()
                ->where('slug', '!=', $page->slug)
                ->take(3)
                ->values(),
        ]);
    }

    public function page(Page $page): Response
    {
        abort_unless($page->type === 'page' && $this->isPublished($page), 404);

        return Inertia::render('Live/PageShow', [
            'page' => $page->toPublicArray(),
            'kicker' => 'Keep Sydney Live',
            'related' => collect(),
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function eventCollection(): Collection
    {
        return Event::query()
            ->published()
            ->with('venue:id,name,slug')
            ->orderBy('start_datetime')
            ->get()
            ->map(fn (Event $event): array => $event->toPublicArray())
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function venueCollection(): Collection
    {
        return Venue::query()
            ->published()
            ->orderBy('name')
            ->get()
            ->map(fn (Venue $venue): array => $venue->toPublicArray())
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function guideCollection(): Collection
    {
        return Page::query()
            ->published()
            ->where('type', 'guide')
            ->orderBy('sort_order')
            ->orderByDesc('published_at')
            ->get()
            ->map(fn (Page $page): array => $page->toPublicArray())
            ->values();
    }

    private function isPublished(Page $page): bool
    {
        return $page->status === 'published'
            && ($page->published_at === null || $page->published_at->isPast());
    }

    private function tonightEvents(Collection $events): Collection
    {
        $today = CarbonImmutable::now('Australia/Sydney')->toDateString();
        $tonight = $events->filter(function (array $event) use ($today): bool {
            return CarbonImmutable::parse($event['start_datetime'])->toDateString() === $today;
        })->values();

        if ($tonight->isNotEmpty()) {
            return $tonight;
        }

        $firstDate = $events->first();

        if ($firstDate === null) {
            return collect();
        }

        $targetDate = CarbonImmutable::parse($firstDate['start_datetime'])->toDateString();

        return $events->filter(function (array $event) use ($targetDate): bool {
            return CarbonImmutable::parse($event['start_datetime'])->toDateString() === $targetDate;
        })->values();
    }

    private function weekendEvents(Collection $events): Collection
    {
        $now = CarbonImmutable::now('Australia/Sydney');
        $weekendStart = $now->startOfWeek()->addDays(5)->startOfDay();
        $weekendEnd = $weekendStart->addDays(2)->endOfDay();

        return $events->filter(function (array $event) use ($weekendStart, $weekendEnd): bool {
            $start = CarbonImmutable::parse($event['start_datetime']);

            return $start->betweenIncluded($weekendStart, $weekendEnd);
        })->values();
    }

    private function locationNameFromSlug(string $slug): string
    {
        $name = str($slug)->replace('-', ' ')->title()->value();

        return str($name)->replace('Cbd', 'CBD')->value();
    }

    private function mapItems(Collection $events, Collection $venues): Collection
    {
        $eventItems = $events
            ->filter(fn (array $event): bool => $event['latitude'] !== null && $event['longitude'] !== null)
            ->values()
            ->map(fn (array $event): array => [
                'id' => 'event-'.$event['slug'],
                'type' => 'event',
                'name' => $event['title'],
                'latitude' => $event['latitude'],
                'longitude' => $event['longitude'],
                'suburb' => $event['suburb'],
                'category' => $event['category'],
                'category_slug' => $event['category_slug'],
                'day' => $event['day'],
                'time' => $event['time'],
                'url' => '/events/'.$event['slug'],
            ]);

        $venueItems = $venues
            ->filter(fn (array $venue): bool => $venue['latitude'] !== null && $venue['longitude'] !== null)
            ->values()
            ->map(fn (array $venue): array => [
                'id' => 'venue-'.$venue['slug'],
                'type' => 'venue',
                'name' => $venue['name'],
                'latitude' => $venue['latitude'],
                'longitude' => $venue['longitude'],
                'suburb' => $venue['suburb'],
                'category' => 'Venue',
                'category_slug' => 'venue',
                'day' => null,
                'time' => null,
                'url' => '/venues/'.$venue['slug'],
            ]);

        return $eventItems->concat($venueItems)->values();
    }
}
