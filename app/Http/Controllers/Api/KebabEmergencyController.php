<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RestaurantPreviewResource;
use App\Services\KebabDiscoveryService;
use App\Support\RestaurantFilters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Kebab Emergency and Kebab Radar.
 *
 * Given a location, return the kebabs that can realistically save the
 * situation: open now, close by, and worth the walk.
 */
class KebabEmergencyController extends Controller
{
    private const MAX_RESULTS = 8;

    /** Anything beyond this is not an emergency response, it is an expedition. */
    private const MAX_RADIUS_KM = 15.0;

    public function __construct(private readonly KebabDiscoveryService $discovery) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'limit' => ['nullable', 'integer', 'between:1,'.self::MAX_RESULTS],
        ]);

        $latitude = (float) $validated['latitude'];
        $longitude = (float) $validated['longitude'];
        $limit = (int) ($validated['limit'] ?? 3);

        $openNow = $this->discovery
            ->nearest($latitude, $longitude, new RestaurantFilters(openNow: true), self::MAX_RESULTS)
            ->filter(fn ($restaurant): bool => $restaurant->getAttribute('distance_km') <= self::MAX_RADIUS_KM)
            ->values();

        // If nothing is trading, the Society still owes the user an answer.
        $results = $openNow->isNotEmpty()
            ? $openNow
            : $this->discovery->nearest($latitude, $longitude, new RestaurantFilters, self::MAX_RESULTS);

        return response()->json([
            'any_open' => $openNow->isNotEmpty(),
            'within_one_km' => $results->filter(
                fn ($restaurant): bool => $restaurant->getAttribute('distance_km') <= 1.0
            )->count(),
            'results' => RestaurantPreviewResource::collection($results->take($limit)->values()),
        ]);
    }
}
