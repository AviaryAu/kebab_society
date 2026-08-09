<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\KebabStyleResource;
use App\Http\Resources\RestaurantPreviewResource;
use App\Models\KebabStyle;
use App\Models\Suburb;
use App\Services\KebabDiscoveryService;
use App\Services\KebabRankingService;
use App\Support\RestaurantFilters;
use App\Support\ScoreTier;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MapController extends Controller
{
    public function __construct(
        private readonly KebabDiscoveryService $discovery,
        private readonly KebabRankingService $ranking,
    ) {}

    public function index(Request $request): Response
    {
        $filters = RestaurantFilters::fromRequest($request);
        $restaurants = $this->discovery->search($filters);

        return Inertia::render('Map/Index', [
            'restaurants' => RestaurantPreviewResource::collection($restaurants),
            'filters' => $filters->toArray(),
            'styles' => KebabStyleResource::collection(
                KebabStyle::query()->where('is_filterable', true)->orderBy('sort_order')->get()
            ),
            'suburbs' => Suburb::query()
                ->orderBy('name')
                ->get(['name', 'slug', 'region', 'postcode', 'latitude', 'longitude'])
                ->map(fn (Suburb $suburb): array => [
                    'name' => $suburb->name,
                    'slug' => $suburb->slug,
                    'region' => $suburb->region,
                    'postcode' => $suburb->postcode,
                    'latitude' => $suburb->latitude,
                    'longitude' => $suburb->longitude,
                ]),
            'tiers' => array_map(fn (ScoreTier $tier): array => $tier->toArray(), ScoreTier::all()),
            'map' => config('kebab.map'),
            'leaderboards' => $this->ranking->definitions()
                ->map(fn ($definition): array => $definition->toArray())
                ->values(),
        ]);
    }
}
