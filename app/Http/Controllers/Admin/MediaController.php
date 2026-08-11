<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

/**
 * Image uploads for the editor and for event/venue/page artwork.
 *
 * Everything is re-encoded to WebP, which both compresses well and destroys any
 * payload smuggled inside an uploaded file.
 */
class MediaController extends Controller
{
    public function __construct(private readonly ImageManager $images) {}

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:12288'],
        ]);

        $config = config('kslive.media');
        $disk = (string) $config['disk'];
        $directory = trim((string) $config['directory'], '/');

        $image = $this->images
            ->decodePath($request->file('image')->getRealPath())
            ->orient()
            ->scaleDown((int) $config['max_width']);

        $path = $directory.'/'.now()->format('Y/m').'/'.Str::random(20).'.webp';

        Storage::disk($disk)->put(
            $path,
            (string) $image->encode(new WebpEncoder(quality: (int) $config['quality'], strip: true)),
            'public',
        );

        return response()->json([
            'url' => Storage::disk($disk)->url($path),
            'path' => $path,
            'width' => $image->width(),
            'height' => $image->height(),
        ]);
    }
}
