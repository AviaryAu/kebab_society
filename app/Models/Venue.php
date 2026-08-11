<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\VenueFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A room Sydney turns up to.
 */
class Venue extends Model
{
    /** @use HasFactory<VenueFactory> */
    use HasFactory;

    public const STATUSES = ['published', 'draft'];

    protected $fillable = [
        'name',
        'slug',
        'suburb',
        'address',
        'description',
        'body',
        'image',
        'website',
        'social_url',
        'phone',
        'transport',
        'latitude',
        'longitude',
        'status',
        'featured',
        'meta_title',
        'meta_description',
    ];

    protected function casts(): array
    {
        return [
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
     * @return HasMany<Event, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * @param  Builder<Venue>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', 'published');
    }

    /**
     * @param  Builder<Venue>  $query
     */
    public function scopeMatching(Builder $query, string $term): void
    {
        $term = trim($term);

        if ($term === '') {
            return;
        }

        $query->where(function (Builder $query) use ($term): void {
            $query->where('name', 'like', "%{$term}%")
                ->orWhere('suburb', 'like', "%{$term}%")
                ->orWhere('address', 'like', "%{$term}%");
        });
    }

    /**
     * The shape the public pages and the map consume.
     *
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'suburb' => $this->suburb,
            'address' => $this->address,
            'description' => $this->description,
            'body' => $this->body,
            'website' => $this->website,
            'social_url' => $this->social_url,
            'phone' => $this->phone,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'image' => $this->image,
            'transport' => $this->transport,
            'featured' => $this->featured,
        ];
    }
}
