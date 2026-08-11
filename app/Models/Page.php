<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Editorial content: guides and standalone pages.
 */
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasFactory;

    public const STATUSES = ['published', 'draft'];

    public const TYPES = ['guide', 'page'];

    protected $fillable = [
        'title',
        'slug',
        'type',
        'excerpt',
        'body',
        'image',
        'status',
        'published_at',
        'featured',
        'sort_order',
        'meta_title',
        'meta_description',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'immutable_datetime',
            'featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @param  Builder<Page>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', 'published')
            ->where(function (Builder $query): void {
                $query->whereNull('published_at')->orWhere('published_at', '<=', now());
            });
    }

    /**
     * @param  Builder<Page>  $query
     */
    public function scopeMatching(Builder $query, string $term): void
    {
        $term = trim($term);

        if ($term === '') {
            return;
        }

        $query->where(function (Builder $query) use ($term): void {
            $query->where('title', 'like', "%{$term}%")
                ->orWhere('excerpt', 'like', "%{$term}%");
        });
    }

    public function publicUrl(): string
    {
        return $this->type === 'guide' ? "/guides/{$this->slug}" : "/{$this->slug}";
    }

    /**
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'type' => $this->type,
            'excerpt' => $this->excerpt,
            'body' => $this->body,
            'image' => $this->image,
            'url' => $this->publicUrl(),
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'published_at' => $this->published_at?->toIso8601String(),
            'published_label' => $this->published_at?->format('j F Y'),
        ];
    }
}
