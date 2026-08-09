<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Restaurant;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The compact restaurant payload used by map markers, preview cards and lists.
 *
 * @mixin Restaurant
 */
class RestaurantPreviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $now = CarbonImmutable::now();
        $tier = $this->ratingTier();
        $isOpen = $this->isOpenAt($now);
        $closingTime = $isOpen ? $this->closingTime($now) : null;
        $nextOpening = $isOpen ? null : $this->nextOpeningTime($now);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'suburb' => $this->whenLoaded('suburb', fn (): array => [
                'name' => $this->suburb->name,
                'slug' => $this->suburb->slug,
                'region' => $this->suburb->region,
            ]),
            'address_line' => $this->address_line,
            'postcode' => $this->postcode,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'phone' => $this->phone,

            'kebab_rating' => $this->kebab_rating,
            'is_rated' => $this->isRated(),
            'tier' => $tier->toArray(),
            'marker_icon' => $tier->markerUrl(),

            'google_rating' => $this->google_rating,
            'google_review_count' => $this->google_review_count,
            'society_rating' => $this->society_rating,
            'society_review_count' => $this->society_review_count,
            'check_in_count' => $this->check_in_count,

            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'is_open_now' => $isOpen,
            'closes_at' => $closingTime?->format('g:ia'),
            'opens_at' => $nextOpening?->calendar(),
            'trades_late_night' => $this->tradesLateNight(),
            'has_hours' => ! $this->hours()->isEmpty(),

            'price_level' => $this->price_level,
            'society_approved' => $this->isSocietyApproved(),
            'styles' => KebabStyleResource::collection($this->whenLoaded('kebabStyles')),
            'photos' => RestaurantPhotoResource::collection($this->whenLoaded('photos')),

            'url' => route('restaurants.show', $this->slug),
            'directions_url' => $this->directionsUrl(),
            'distance_km' => $this->whenNotNull($this->getAttribute('distance_km')),
        ];
    }

    protected function directionsUrl(): string
    {
        return 'https://www.google.com/maps/dir/?api=1&destination='
            .rawurlencode("{$this->latitude},{$this->longitude}");
    }
}
