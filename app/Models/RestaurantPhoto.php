<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

/**
 * A photograph of a restaurant.
 *
 * The uploaded original is processed into the renditions configured in
 * config/kebab.php and only those are ever served.
 */
class RestaurantPhoto extends Model
{
    protected $fillable = [
        'restaurant_id',
        'disk',
        'directory',
        'renditions',
        'original_filename',
        'mime_type',
        'width',
        'height',
        'bytes',
        'caption',
        'credit',
        'sort_order',
        'is_primary',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'renditions' => 'array',
            'width' => 'integer',
            'height' => 'integer',
            'bytes' => 'integer',
            'sort_order' => 'integer',
            'is_primary' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Restaurant, $this>
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Public URL for a rendition, falling back to the largest one held.
     */
    public function url(string $format = 'card'): ?string
    {
        $path = $this->renditions[$format] ?? $this->renditions['hero'] ?? null;

        if ($path === null) {
            return null;
        }

        return $this->storage()->url($path);
    }

    /**
     * @return array<string, string>
     */
    public function urls(): array
    {
        return collect($this->renditions ?? [])
            ->map(fn (string $path): string => $this->storage()->url($path))
            ->all();
    }

    /**
     * Remove every stored rendition. Called when the record is deleted.
     */
    public function deleteFiles(): void
    {
        $paths = array_values($this->renditions ?? []);

        if ($paths !== []) {
            $this->storage()->delete($paths);
        }
    }

    private function storage(): FilesystemAdapter
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($this->disk);

        return $disk;
    }

    protected static function booted(): void
    {
        static::deleting(fn (self $photo) => $photo->deleteFiles());
    }
}
