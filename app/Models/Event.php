<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ImageLicence;
use Carbon\CarbonImmutable;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;

/**
 * Something happening in Sydney, at a time, in a place.
 */
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    public const STATUSES = ['published', 'draft'];

    protected $fillable = [
        'title',
        'slug',
        'description',
        'body',
        'start_datetime',
        'end_datetime',
        'venue_id',
        'suburb',
        'category_slug',
        'image',
        'price',
        'ticket_url',
        'latitude',
        'longitude',
        'featured',
        'status',
        'meta_title',
        'meta_description',
        'ingest_source_id',
        'external_id',
        'source_url',
        'source_name',
        'source_attribution_url',
        'fingerprint',
        'last_synced_at',
        'import_locked',
        'copy_generated_at',
        'copy_model',
        'facts_hash',
        'image_licence',
        'image_credit',
        'image_source_url',
    ];

    protected function casts(): array
    {
        return [
            'start_datetime' => 'immutable_datetime',
            'end_datetime' => 'immutable_datetime',
            'latitude' => 'float',
            'longitude' => 'float',
            'featured' => 'boolean',
            'import_locked' => 'boolean',
            'image_licence' => ImageLicence::class,
            'last_synced_at' => 'immutable_datetime',
            'copy_generated_at' => 'immutable_datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsTo<Venue, $this>
     */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /**
     * @return BelongsTo<IngestSource, $this>
     */
    public function ingestSource(): BelongsTo
    {
        return $this->belongsTo(IngestSource::class, 'ingest_source_id');
    }

    /**
     * @param  Builder<Event>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', 'published');
    }

    /**
     * Rows an importer is allowed to rewrite. An administrator editing an
     * ingested event sets import_locked, which takes it out of scope for good.
     *
     * @param  Builder<Event>  $query
     */
    public function scopeSyncable(Builder $query): void
    {
        $query->where('import_locked', false);
    }

    /**
     * @param  Builder<Event>  $query
     */
    public function scopeMatching(Builder $query, string $term): void
    {
        $term = trim($term);

        if ($term === '') {
            return;
        }

        $query->where(function (Builder $query) use ($term): void {
            $query->where('title', 'like', "%{$term}%")
                ->orWhere('suburb', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%");
        });
    }

    /**
     * Category names live in config/kslive.php; the record only stores the slug.
     */
    public function categoryName(): string
    {
        $category = collect(config('kslive.categories'))->firstWhere('slug', $this->category_slug);

        return Arr::get($category ?? [], 'name', 'Live');
    }

    public function wasIngested(): bool
    {
        return $this->ingest_source_id !== null;
    }

    /**
     * The facts the copy is written from.
     *
     * The hash below is taken from exactly this array, so the two can never
     * drift: if a fact changes, the copy is regenerated, and if it does not,
     * we do not spend a request.
     *
     * @return array<string, string>
     */
    public function copyFacts(): array
    {
        return array_filter([
            'title' => (string) $this->title,
            'venue' => (string) $this->venue?->name,
            'suburb' => (string) $this->suburb,
            'date' => $this->start_datetime?->format('l j F Y') ?? '',
            'start_time' => $this->start_datetime?->format('g:i A') ?? '',
            'end_time' => $this->end_datetime?->format('g:i A') ?? '',
            'category' => $this->categoryName(),
            'price' => (string) $this->price,
        ], static fn (string $value): bool => trim($value) !== '');
    }

    /**
     * A digest of the facts the copy is written from. When this is unchanged we
     * skip regeneration, which is what keeps a six-hourly re-sync from burning
     * the day's AI request budget rewriting copy nobody asked for.
     */
    public function factsHash(): string
    {
        $facts = $this->copyFacts();
        ksort($facts);

        return hash('sha256', json_encode($facts, JSON_THROW_ON_ERROR));
    }

    public function needsGeneratedCopy(): bool
    {
        if (! $this->wasIngested()) {
            return false;
        }

        return $this->copy_generated_at === null
            || $this->facts_hash !== $this->factsHash();
    }

    /**
     * Attribution is the consideration we offer for the facts we took, so it
     * travels with the event rather than being assembled in a template.
     *
     * @return array{name: string, url: string}|null
     */
    public function attribution(): ?array
    {
        $url = $this->source_attribution_url ?: $this->source_url;

        if ($this->source_name === null || $url === null) {
            return null;
        }

        return ['name' => $this->source_name, 'url' => $url];
    }

    /**
     * The shape the public pages and the map consume.
     *
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        /** @var CarbonImmutable $start */
        $start = $this->start_datetime;
        $end = $this->end_datetime;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'body' => $this->body,
            'start_datetime' => $start->toIso8601String(),
            'end_datetime' => $end?->toIso8601String(),
            'venue_slug' => $this->venue?->slug,
            'venue' => $this->venue?->name,
            'suburb' => $this->suburb,
            'category' => $this->categoryName(),
            'category_slug' => $this->category_slug,
            'image' => $this->image,
            'price' => $this->price,
            'ticket_url' => $this->ticket_url,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'featured' => $this->featured,
            'day' => $start->format('D j M'),
            'date' => $start->format('l j F'),
            'time' => $start->format('g:i A'),
            'end_time' => $end?->format('g:i A'),
            'image_credit' => $this->image_credit,
            'attribution' => $this->attribution(),
        ];
    }
}
