<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\RestaurantStatus;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateRestaurantRequest;
use App\Http\Resources\RestaurantPhotoResource;
use App\Models\KebabStyle;
use App\Models\Restaurant;
use App\Models\Suburb;
use App\Services\KebabScoringService;
use App\Support\RatingTier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Society's register, editable.
 *
 * Ratings are never typed in here. An administrator edits the evidence (the
 * Google rating, the review count, a bounded editorial adjustment) and
 * KebabScoringService recalculates the published rating.
 */
class RestaurantController extends Controller
{
    private const PER_PAGE = 25;

    private const SORTABLE = ['name', 'kebab_rating', 'google_rating', 'google_review_count', 'updated_at'];

    public function __construct(private readonly KebabScoringService $scoring) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'in:published,draft,temporarily_closed,permanently_closed'],
            'rated' => ['nullable', 'string', 'in:rated,unrated'],
            'certified' => ['nullable', 'string', 'in:yes,no'],
            'sort' => ['nullable', 'string', 'in:'.implode(',', self::SORTABLE)],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $sort = $filters['sort'] ?? 'name';
        $direction = $filters['direction'] ?? 'asc';

        $restaurants = Restaurant::query()
            ->with(['suburb'])
            ->withCount('photos')
            ->when($filters['search'] ?? null, fn (Builder $query, string $term) => $query->matching($term))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when(
                ($filters['rated'] ?? null) === 'rated',
                fn (Builder $query) => $query->whereNotNull('kebab_rating'),
            )
            ->when(
                ($filters['rated'] ?? null) === 'unrated',
                fn (Builder $query) => $query->whereNull('kebab_rating'),
            )
            ->when(
                ($filters['certified'] ?? null) === 'yes',
                fn (Builder $query) => $query->whereNotNull('society_approved_at'),
            )
            ->when(
                ($filters['certified'] ?? null) === 'no',
                fn (Builder $query) => $query->whereNull('society_approved_at'),
            )
            ->orderBy($sort, $direction)
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('Admin/Restaurants/Index', [
            'restaurants' => $restaurants->through(fn (Restaurant $restaurant): array => [
                'id' => $restaurant->id,
                'name' => $restaurant->name,
                'slug' => $restaurant->slug,
                'suburb' => $restaurant->suburb?->name,
                'kebab_rating' => $restaurant->kebab_rating,
                'tier' => $restaurant->ratingTier()->toArray(),
                'google_rating' => $restaurant->google_rating,
                'google_review_count' => $restaurant->google_review_count,
                'status' => $restaurant->status->value,
                'status_label' => $restaurant->status->label(),
                'society_approved' => $restaurant->isSocietyApproved(),
                'photos_count' => $restaurant->photos_count,
                'has_hours' => ! $restaurant->hours()->isEmpty(),
                'edit_url' => route('admin.restaurants.edit', $restaurant->slug),
                'public_url' => route('restaurants.show', $restaurant->slug),
                'updated_at' => $restaurant->updated_at?->diffForHumans(),
            ]),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'status' => $filters['status'] ?? null,
                'rated' => $filters['rated'] ?? null,
                'certified' => $filters['certified'] ?? null,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'summary' => [
                'total' => Restaurant::query()->count(),
                'rated' => Restaurant::query()->whereNotNull('kebab_rating')->count(),
                'certified' => Restaurant::query()->whereNotNull('society_approved_at')->count(),
                'drafts' => Restaurant::query()->where('status', RestaurantStatus::Draft)->count(),
            ],
        ]);
    }

    public function edit(Restaurant $restaurant): Response
    {
        $restaurant->load(['suburb', 'kebabStyles', 'photos']);

        return Inertia::render('Admin/Restaurants/Edit', [
            'restaurant' => [
                'id' => $restaurant->id,
                'name' => $restaurant->name,
                'slug' => $restaurant->slug,
                'description' => $restaurant->description,
                'address_line' => $restaurant->address_line,
                'suburb_id' => $restaurant->suburb_id,
                'postcode' => $restaurant->postcode,
                'latitude' => $restaurant->latitude,
                'longitude' => $restaurant->longitude,
                'location_precision' => $restaurant->location_precision,
                'phone' => $restaurant->phone,
                'website' => $restaurant->website,
                'google_place_id' => $restaurant->google_place_id,
                'google_rating' => $restaurant->google_rating,
                'google_review_count' => $restaurant->google_review_count,
                'price_level' => $restaurant->price_level,
                'status' => $restaurant->status->value,
                'verification_status' => $restaurant->verification_status->value,
                'society_approved' => $restaurant->isSocietyApproved(),
                'editorial_adjustment' => (float) $restaurant->editorial_adjustment,
                'editorial_note' => $restaurant->editorial_note,
                'kebab_rating' => $restaurant->kebab_rating,
                'tier' => $restaurant->ratingTier()->toArray(),
                'rating_breakdown' => $restaurant->rating_breakdown,
                'data_source_label' => $restaurant->data_source->label(),
                'opening_hours' => $restaurant->hours()->toArray(),
                'styles' => $restaurant->kebabStyles->pluck('id'),
                'photos' => RestaurantPhotoResource::collection($restaurant->photos),
                'public_url' => route('restaurants.show', $restaurant->slug),
            ],
            'options' => [
                'suburbs' => Suburb::query()->orderBy('name')->get(['id', 'name', 'region', 'postcode']),
                'styles' => KebabStyle::query()->orderBy('sort_order')->get(['id', 'name', 'group']),
                'statuses' => array_map(
                    fn (RestaurantStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
                    RestaurantStatus::cases(),
                ),
                'verifications' => array_map(
                    fn (VerificationStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
                    VerificationStatus::cases(),
                ),
                'tiers' => array_map(fn (RatingTier $tier): array => $tier->toArray(), RatingTier::allIncludingUnrated()),
                'adjustment_limit' => (float) config('kebab.rating.editorial_adjustment_limit'),
            ],
        ]);
    }

    public function update(UpdateRestaurantRequest $request, Restaurant $restaurant): RedirectResponse
    {
        $data = $request->validated();

        $restaurant->fill([
            ...collect($data)->except(['styles', 'society_approved'])->all(),
            'society_approved_at' => $data['society_approved']
                ? ($restaurant->society_approved_at ?? now())
                : null,
        ])->save();

        $restaurant->kebabStyles()->sync($data['styles'] ?? []);

        // The published rating always follows from the evidence.
        $this->scoring->apply($restaurant);

        return redirect()
            ->route('admin.restaurants.edit', $restaurant->slug)
            ->with('message', "{$restaurant->name} updated. Rating recalculated.");
    }
}
