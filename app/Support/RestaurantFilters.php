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
        public int $minimumScore = 0,
        public ?string $suburb = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'styles' => ['nullable', 'array', 'max:20'],
            'styles.*' => ['string', 'max:60'],
            'open_now' => ['nullable', 'boolean'],
            'late_night' => ['nullable', 'boolean'],
            'society_certified' => ['nullable', 'boolean'],
            'min_score' => ['nullable', 'integer', 'between:0,100'],
            'suburb' => ['nullable', 'string', 'max:120'],
        ]);

        return new self(
            search: trim((string) ($validated['search'] ?? '')),
            styles: array_values(array_unique($validated['styles'] ?? [])),
            openNow: (bool) ($validated['open_now'] ?? false),
            lateNight: (bool) ($validated['late_night'] ?? false),
            societyCertified: (bool) ($validated['society_certified'] ?? false),
            minimumScore: (int) ($validated['min_score'] ?? 0),
            suburb: $validated['suburb'] ?? null,
        );
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
            'min_score' => $this->minimumScore,
            'suburb' => $this->suburb,
        ];
    }
}
