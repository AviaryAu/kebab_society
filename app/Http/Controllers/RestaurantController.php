<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\RestaurantDetailResource;
use App\Http\Resources\RestaurantPreviewResource;
use App\Models\Restaurant;
use App\Services\KebabDiscoveryService;
use App\Support\RestaurantFilters;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RestaurantController extends Controller
{
    /** How many nearby alternatives to offer on a restaurant page. */
    private const NEARBY_LIMIT = 4;

    public function __construct(private readonly KebabDiscoveryService $discovery) {}

    public function show(Restaurant $restaurant): Response
    {
        if (! $restaurant->status->isDiscoverable()) {
            throw new NotFoundHttpException;
        }

        $restaurant->load(['suburb', 'kebabStyles', 'photos']);

        $nearby = $this->discovery
            ->nearest(
                $restaurant->latitude,
                $restaurant->longitude,
                new RestaurantFilters,
                self::NEARBY_LIMIT + 1,
            )
            ->reject(fn (Restaurant $candidate): bool => $candidate->is($restaurant))
            ->take(self::NEARBY_LIMIT)
            ->values();

        return Inertia::render('Restaurants/Show', [
            'restaurant' => new RestaurantDetailResource($restaurant),
            'nearby' => RestaurantPreviewResource::collection($nearby),
            'map' => config('kebab.map'),
        ]);
    }
}
