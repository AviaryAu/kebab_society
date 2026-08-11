<?php

declare(strict_types=1);

namespace App\Models;

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
    ];

    protected function casts(): array
    {
        return [
            'start_datetime' => 'immutable_datetime',
            'end_datetime' => 'immutable_datetime',
            'latitude' => 'float',
            'longitude' => 'float',
            'featured' => 'boolean',
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
     * @param  Builder<Event>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', 'published');
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
        ];
    }
}
