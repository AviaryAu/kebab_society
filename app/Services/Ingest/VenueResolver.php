<?php

declare(strict_types=1);

namespace App\Services\Ingest;

use App\Models\IngestSource;
use App\Models\Suburb;
use App\Models\Venue;
use Illuminate\Support\Str;

/**
 * Finds the room an event is in, or creates it.
 *
 * Venues arrive as free text from every source, so the same room shows up as
 * "Enmore Theatre", "The Enmore" and "Enmore Theatre, Newtown". Getting this
 * wrong fragments a venue page across three records, so matching is
 * deliberately generous before it gives up and creates something new.
 */
class VenueResolver
{
    public function __construct(private readonly Geocoder $geocoder) {}

    public function resolve(IngestSource $source, EventDraft $draft): ?Venue
    {
        if ($draft->venueName === null || trim($draft->venueName) === '') {
            return null;
        }

        $venue = $this->findExisting($source, $draft);

        if ($venue !== null) {
            $this->backfill($venue, $draft);

            return $venue;
        }

        return $this->create($source, $draft);
    }

    private function findExisting(IngestSource $source, EventDraft $draft): ?Venue
    {
        // The source's own identifier is the strongest signal available.
        if ($draft->venueExternalId !== null) {
            $venue = Venue::query()
                ->where('ingest_source_id', $source->id)
                ->where('external_id', $draft->venueExternalId)
                ->first();

            if ($venue !== null) {
                return $venue;
            }
        }

        $slug = Str::slug($draft->venueName);

        if ($venue = Venue::query()->where('slug', $slug)->first()) {
            return $venue;
        }

        // Fall back to comparing names with the noise stripped, scoped to the
        // suburb so two "Town Halls" in different places stay separate.
        $normalised = $this->normaliseName($draft->venueName);

        return Venue::query()
            ->when(
                $draft->suburb !== null,
                fn ($query) => $query->where('suburb', $draft->suburb),
            )
            ->get()
            ->first(fn (Venue $venue): bool => $this->normaliseName($venue->name) === $normalised);
    }

    private function create(IngestSource $source, EventDraft $draft): Venue
    {
        [$latitude, $longitude] = $this->coordinates($draft);

        return Venue::query()->create([
            'name' => $draft->venueName,
            'slug' => $this->uniqueSlug($draft->venueName),
            'suburb' => $draft->suburb ?? $this->suburbFromCoordinates($latitude, $longitude) ?? 'Sydney',
            'address' => $draft->address,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'ingest_source_id' => $source->id,
            'external_id' => $draft->venueExternalId,
            'source_url' => $draft->sourceUrl,
            'last_synced_at' => now(),

            // Ingested venues start as drafts. A venue page with nothing but a
            // name and a pin is not worth publishing, and an editor promoting
            // it is a deliberate act.
            'status' => 'draft',
        ]);
    }

    /**
     * Fill gaps on an existing venue without overwriting anything an editor
     * may have written.
     */
    private function backfill(Venue $venue, EventDraft $draft): void
    {
        if ($venue->import_locked) {
            return;
        }

        $updates = array_filter([
            'address' => $venue->address === null ? $draft->address : null,
            'latitude' => $venue->latitude === null ? $draft->latitude : null,
            'longitude' => $venue->longitude === null ? $draft->longitude : null,
        ], static fn (mixed $value): bool => $value !== null);

        if ($updates !== []) {
            $venue->fill($updates);
        }

        $venue->last_synced_at = now();
        $venue->save();
    }

    /**
     * @return array{0: float|null, 1: float|null}
     */
    private function coordinates(EventDraft $draft): array
    {
        if ($draft->hasLocation()) {
            return [$draft->latitude, $draft->longitude];
        }

        $query = trim(implode(', ', array_filter([
            $draft->venueName,
            $draft->address,
            $draft->suburb,
        ])));

        $point = $query === '' ? null : $this->geocoder->locate($query);

        return [$point['latitude'] ?? null, $point['longitude'] ?? null];
    }

    /**
     * Nearest known suburb, used only when a source gave us a pin but no name
     * for where it is.
     */
    private function suburbFromCoordinates(?float $latitude, ?float $longitude): ?string
    {
        if ($latitude === null || $longitude === null) {
            return null;
        }

        return Suburb::query()
            ->select('name', 'latitude', 'longitude')
            ->get()
            ->sortBy(fn (Suburb $suburb): float => (
                ($suburb->latitude - $latitude) ** 2 + ($suburb->longitude - $longitude) ** 2
            ))
            ->first()?->name;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (Venue::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    /**
     * Strip the words that differ between listings of the same room.
     */
    private function normaliseName(string $name): string
    {
        $name = Str::lower($name);
        $name = preg_replace('/\b(the|at|sydney)\b/', '', $name) ?? $name;
        $name = preg_replace('/[^a-z0-9]+/', '', $name) ?? $name;

        return trim($name);
    }
}
