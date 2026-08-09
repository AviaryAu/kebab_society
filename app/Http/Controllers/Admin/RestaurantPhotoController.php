<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\RestaurantPhoto;
use App\Services\RestaurantPhotoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RestaurantPhotoController extends Controller
{
    public function __construct(private readonly RestaurantPhotoService $photos) {}

    public function store(Request $request, Restaurant $restaurant): RedirectResponse
    {
        $maxKilobytes = (int) config('kebab.photos.max_upload_kilobytes');

        $validated = $request->validate([
            'photos' => ['required', 'array', 'max:10'],
            'photos.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp,heic', "max:{$maxKilobytes}"],
        ]);

        foreach ($validated['photos'] as $file) {
            $this->photos->store($restaurant, $file, $request->user());
        }

        $count = count($validated['photos']);

        return back()->with('message', $count === 1 ? 'Photograph filed.' : "{$count} photographs filed.");
    }

    public function update(Request $request, RestaurantPhoto $photo): RedirectResponse
    {
        $validated = $request->validate([
            'caption' => ['nullable', 'string', 'max:160'],
            'credit' => ['nullable', 'string', 'max:120'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        $photo->fill([
            'caption' => $validated['caption'] ?? null,
            'credit' => $validated['credit'] ?? null,
        ])->save();

        if ($validated['is_primary'] ?? false) {
            $this->photos->makePrimary($photo);
        }

        return back()->with('message', 'Photograph updated.');
    }

    public function destroy(RestaurantPhoto $photo): RedirectResponse
    {
        $this->photos->delete($photo);

        return back()->with('message', 'Photograph removed.');
    }

    public function reorder(Request $request, Restaurant $restaurant): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $this->photos->reorder($restaurant, $validated['ids']);

        return back()->with('message', 'Order saved.');
    }
}
