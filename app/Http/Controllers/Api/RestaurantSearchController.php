<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RestaurantPreviewResource;
use App\Services\KebabDiscoveryService;
use App\Support\RestaurantFilters;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RestaurantSearchController extends Controller
{
    public function __construct(private readonly KebabDiscoveryService $discovery) {}

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $filters = RestaurantFilters::fromRequest($request);

        return RestaurantPreviewResource::collection($this->discovery->search($filters));
    }
}
