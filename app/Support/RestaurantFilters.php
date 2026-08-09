<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * The set of map/list filters a visitor can apply.
 */
final readonly class RestaurantFilters
{
    /**
     * @param  array<int, string>  $styles
     */
    public function __construct(
        public string $search = '',
        public array $styles = [],
        public bool $openNow = false,
        public bool $lateNight = false,
        public bool $societyCertified = false,
        public float $minimumRating = 0.0,
        public ?string $suburb = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        self::normaliseBooleans($request);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'styles' => ['nullable', 'array', 'max:20'],
            'styles.*' => ['string', 'max:60'],
            'open_now' => ['nullable', 'boolean'],
            'late_night' => ['nullable', 'boolean'],
            'society_certified' => ['nullable', 'boolean'],
            'min_rating' => ['nullable', 'numeric', 'between:0,5'],
            'suburb' => ['nullable', 'string', 'max:120'],
        ]);

        return new self(
            search: trim((string) ($validated['search'] ?? '')),
            styles: array_values(array_unique($validated['styles'] ?? [])),
            openNow: (bool) ($validated['open_now'] ?? false),
            lateNight: (bool) ($validated['late_night'] ?? false),
            societyCertified: (bool) ($validated['society_certified'] ?? false),
            minimumRating: (float) ($validated['min_rating'] ?? 0),
            suburb: $validated['suburb'] ?? null,
        );
    }

    /**
     * Query strings carry booleans as text ("true", "on", "1"). Normalise them
     * so a shared or hand-typed link never fails validation and bounces the
     * visitor back to an unfiltered map.
     */
    private static function normaliseBooleans(Request $request): void
    {
        foreach (['open_now', 'late_night', 'society_certified'] as $key) {
            if (! $request->has($key)) {
                continue;
            }

            $request->merge([
                $key => filter_var($request->input($key), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'search' => $this->search,
            'styles' => $this->styles,
            'open_now' => $this->openNow,
            'late_night' => $this->lateNight,
            'society_certified' => $this->societyCertified,
            'min_rating' => $this->minimumRating,
            'suburb' => $this->suburb,
        ];
    }
}
