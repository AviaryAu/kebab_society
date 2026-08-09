<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Restaurant;
use App\Models\RestaurantPhoto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

/**
 * Stores and processes restaurant photographs.
 *
 * The uploaded original is never served. Each upload is converted to WebP and
 * written in the renditions configured in config/kebab.php, so the interface can
 * ask for the size it needs (card, hero, thumbnail) rather than shipping a 6MB
 * phone photo to a hungry person on mobile data.
 *
 * Storage is disk-agnostic: the public disk locally, Laravel Cloud's object
 * storage via the s3 driver in production.
 */
class RestaurantPhotoService
{
    public function __construct(private readonly ImageManager $images) {}

    public function store(Restaurant $restaurant, UploadedFile $file, ?User $uploader = null): RestaurantPhoto
    {
        $disk = (string) config('kebab.photos.disk');
        $quality = (int) config('kebab.photos.quality');
        $directory = trim((string) config('kebab.photos.directory'), '/')."/{$restaurant->id}";

        // Phone photos carry their rotation in EXIF; bake it in before resizing.
        $image = $this->images->decodePath($file->getRealPath())->orient();

        $originalWidth = $image->width();
        $originalHeight = $image->height();

        $basename = Str::slug($restaurant->slug).'-'.Str::random(8);
        $renditions = [];

        // Largest first, so each rendition is scaled from the previous one.
        $formats = collect(config('kebab.photos.formats'))->sortByDesc('width');

        foreach ($formats as $format => $size) {
            $image->scaleDown($size['width'], $size['height']);
            $path = "{$directory}/{$basename}-{$format}.webp";

            Storage::disk($disk)->put(
                $path,
                (string) $image->encode(new WebpEncoder(quality: $quality, strip: true)),
                'public',
            );

            $renditions[$format] = $path;
        }

        return DB::transaction(function () use (
            $restaurant,
            $file,
            $uploader,
            $disk,
            $directory,
            $renditions,
            $originalWidth,
            $originalHeight
        ): RestaurantPhoto {
            $isFirst = ! $restaurant->photos()->exists();

            return $restaurant->photos()->create([
                'disk' => $disk,
                'directory' => $directory,
                'renditions' => $renditions,
                'original_filename' => (string) $file->getClientOriginalName(),
                'mime_type' => 'image/webp',
                'width' => $originalWidth,
                'height' => $originalHeight,
                'bytes' => (int) $file->getSize(),
                'sort_order' => (int) $restaurant->photos()->max('sort_order') + 1,
                'is_primary' => $isFirst,
                'uploaded_by' => $uploader?->id,
            ]);
        });
    }

    /**
     * Promote a photo to the one used for previews and social cards.
     */
    public function makePrimary(RestaurantPhoto $photo): void
    {
        DB::transaction(function () use ($photo): void {
            RestaurantPhoto::query()
                ->where('restaurant_id', $photo->restaurant_id)
                ->update(['is_primary' => false]);

            $photo->forceFill(['is_primary' => true])->save();
        });
    }

    public function delete(RestaurantPhoto $photo): void
    {
        $restaurantId = $photo->restaurant_id;
        $wasPrimary = $photo->is_primary;

        $photo->delete();

        if (! $wasPrimary) {
            return;
        }

        // Never leave a restaurant without a lead photograph.
        RestaurantPhoto::query()
            ->where('restaurant_id', $restaurantId)
            ->orderBy('sort_order')
            ->first()
            ?->forceFill(['is_primary' => true])
            ->save();
    }

    /**
     * @param  array<int, int>  $orderedIds
     */
    public function reorder(Restaurant $restaurant, array $orderedIds): void
    {
        DB::transaction(function () use ($restaurant, $orderedIds): void {
            foreach (array_values($orderedIds) as $position => $id) {
                $restaurant->photos()->whereKey($id)->update(['sort_order' => $position]);
            }
        });
    }
}
