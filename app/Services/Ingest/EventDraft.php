<?php

declare(strict_types=1);

namespace App\Services\Ingest;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * The facts an adapter extracted about one event, before matching, enrichment
 * or persistence.
 *
 * Note what is not here: no body, no long description destined for publication.
 * `sourceDescription` is context for the copywriter and the review screen only,
 * and the pipeline never writes it to a public column. Everything a reader
 * eventually sees is either a plain fact or copy we wrote ourselves.
 */
final readonly class EventDraft
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $externalId,
        public string $title,
        public CarbonImmutable $startsAt,
        public ?CarbonImmutable $endsAt = null,
        public ?string $venueName = null,
        public ?string $venueExternalId = null,
        public ?string $address = null,
        public ?string $suburb = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public ?string $categorySlug = null,
        public ?string $price = null,
        public ?string $ticketUrl = null,
        public ?string $sourceUrl = null,
        public ?string $imageUrl = null,
        public ?string $imageCredit = null,
        public ?string $sourceDescription = null,
        public array $raw = [],
    ) {}

    /**
     * Identity of the happening itself, independent of who reported it. Two
     * sources describing the same gig must land on the same value, so it is
     * built only from things they will agree on.
     */
    public function fingerprint(): string
    {
        return hash('sha256', implode('|', [
            Str::slug($this->title),
            Str::slug((string) $this->venueName),
            $this->startsAt->format('Y-m-d'),
        ]));
    }

    /**
     * The subset the copywriter is allowed to see for `signal` sources: facts
     * only, so the generated blurb cannot be a derivative of someone's article.
     *
     * @return array<string, mixed>
     */
    public function facts(): array
    {
        return array_filter([
            'title' => $this->title,
            'venue' => $this->venueName,
            'suburb' => $this->suburb,
            'date' => $this->startsAt->format('l j F Y'),
            'start_time' => $this->startsAt->format('g:i A'),
            'end_time' => $this->endsAt?->format('g:i A'),
            'category' => $this->categorySlug,
            'price' => $this->price,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    public function hasLocation(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /**
     * Past events are dropped at the door rather than filtered downstream.
     */
    public function isUpcoming(): bool
    {
        return ($this->endsAt ?? $this->startsAt)->isFuture();
    }

    /**
     * Rebuild a draft from a staged import.
     *
     * The review queue stores the normalised shape rather than a serialised
     * object, so an approval weeks later does not depend on this class having
     * kept the same constructor.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $raw
     */
    public static function fromNormalised(array $data, array $raw = []): self
    {
        return new self(
            externalId: (string) ($data['external_id'] ?? ''),
            title: (string) ($data['title'] ?? ''),
            startsAt: CarbonImmutable::parse($data['starts_at']),
            endsAt: isset($data['ends_at']) ? CarbonImmutable::parse($data['ends_at']) : null,
            venueName: $data['venue_name'] ?? null,
            venueExternalId: $data['venue_external_id'] ?? null,
            address: $data['address'] ?? null,
            suburb: $data['suburb'] ?? null,
            latitude: isset($data['latitude']) ? (float) $data['latitude'] : null,
            longitude: isset($data['longitude']) ? (float) $data['longitude'] : null,
            categorySlug: $data['category_slug'] ?? null,
            price: $data['price'] ?? null,
            ticketUrl: $data['ticket_url'] ?? null,
            sourceUrl: $data['source_url'] ?? null,
            imageUrl: $data['image_url'] ?? null,
            imageCredit: $data['image_credit'] ?? null,
            raw: $raw,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'external_id' => $this->externalId,
            'title' => $this->title,
            'starts_at' => $this->startsAt->toIso8601String(),
            'ends_at' => $this->endsAt?->toIso8601String(),
            'venue_name' => $this->venueName,
            'venue_external_id' => $this->venueExternalId,
            'address' => $this->address,
            'suburb' => $this->suburb,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'category_slug' => $this->categorySlug,
            'price' => $this->price,
            'ticket_url' => $this->ticketUrl,
            'source_url' => $this->sourceUrl,
            'image_url' => $this->imageUrl,
            'image_credit' => $this->imageCredit,
        ];
    }
}
