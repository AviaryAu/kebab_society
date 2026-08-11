<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $brand = [
            'name' => config('kslive.brand.name', config('app.name')),
            'tagline' => config('kslive.brand.tagline', 'Keep Sydney Live.'),
            'description' => config(
                'kslive.brand.description',
                'Keep Sydney Live is an independent Sydney events and culture platform.'
            ),
            'is_late_night' => now()->hour < 5 || now()->hour >= 22,
        ];

        return [
            ...parent::share($request),
            'brand' => $brand,
            // Retained so legacy pages do not break while the pivot lands.
            'society' => $brand,
            'auth' => [
                'user' => fn () => $request->user() === null ? null : [
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'is_admin' => $request->user()->isAdmin(),
                ],
            ],
            'flash' => [
                'message' => fn () => $request->session()->get('message'),
            ],
        ];
    }
}
