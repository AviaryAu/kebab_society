<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EventController as AdminEventController;
use App\Http\Controllers\Admin\EventImportController;
use App\Http\Controllers\Admin\IngestSourceController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\PageController as AdminPageController;
use App\Http\Controllers\Admin\RestaurantController as AdminRestaurantController;
use App\Http\Controllers\Admin\RestaurantPhotoController;
use App\Http\Controllers\Admin\SessionController;
use App\Http\Controllers\Admin\VenueController as AdminVenueController;
use App\Http\Controllers\KSLiveController;
use Illuminate\Support\Facades\Route;

Route::get('/', [KSLiveController::class, 'home'])->name('home');
Route::get('/map', [KSLiveController::class, 'map'])->name('map');
Route::get('/events', [KSLiveController::class, 'events'])->name('events.index');
Route::get('/events/tonight', [KSLiveController::class, 'tonight'])->name('events.tonight');
Route::get('/events/this-weekend', [KSLiveController::class, 'weekend'])->name('events.weekend');
Route::get('/events/{event}', [KSLiveController::class, 'event'])->name('events.show');

Route::get('/venues', [KSLiveController::class, 'venues'])->name('venues.index');
Route::get('/venues/{venue}', [KSLiveController::class, 'venue'])->name('venues.show');

Route::get('/locations', [KSLiveController::class, 'locations'])->name('locations.index');
Route::get('/locations/{location}', [KSLiveController::class, 'location'])->name('locations.show');

Route::get('/guides', [KSLiveController::class, 'guides'])->name('guides.index');
Route::get('/guides/{page}', [KSLiveController::class, 'guide'])->name('guides.show');

Route::get('/music', [KSLiveController::class, 'category'])->defaults('category', 'music')->name('categories.music');
Route::get('/comedy', [KSLiveController::class, 'category'])->defaults('category', 'comedy')->name('categories.comedy');
Route::get('/theatre', [KSLiveController::class, 'category'])->defaults('category', 'theatre')->name('categories.theatre');
Route::get('/nightlife', [KSLiveController::class, 'category'])->defaults('category', 'nightlife')->name('categories.nightlife');
Route::get('/festivals', [KSLiveController::class, 'category'])->defaults('category', 'festivals')->name('categories.festivals');
Route::get('/food', [KSLiveController::class, 'category'])->defaults('category', 'food-drink')->name('categories.food');
Route::get('/arts', [KSLiveController::class, 'category'])->defaults('category', 'arts')->name('categories.arts');
Route::get('/sport', [KSLiveController::class, 'category'])->defaults('category', 'sport')->name('categories.sport');

/*
|--------------------------------------------------------------------------
| Platform admin
|--------------------------------------------------------------------------
*/

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::middleware('guest')->group(function (): void {
        Route::get('login', [SessionController::class, 'create'])->name('login');
        Route::post('login', [SessionController::class, 'store'])->name('login.attempt');
    });

    Route::middleware(['auth', 'admin'])->group(function (): void {
        Route::post('logout', [SessionController::class, 'destroy'])->name('logout');

        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::resource('events', AdminEventController::class)
            ->except(['show'])
            ->parameters(['events' => 'event']);

        Route::resource('venues', AdminVenueController::class)
            ->except(['show'])
            ->parameters(['venues' => 'venue']);

        Route::resource('pages', AdminPageController::class)
            ->except(['show'])
            ->parameters(['pages' => 'page']);

        Route::resource('sources', IngestSourceController::class)
            ->except(['show'])
            ->parameters(['sources' => 'source']);

        Route::post('sources/{source}/run', [IngestSourceController::class, 'run'])
            ->name('sources.run');

        Route::get('imports', [EventImportController::class, 'index'])->name('imports.index');
        Route::post('imports/bulk', [EventImportController::class, 'bulk'])->name('imports.bulk');
        Route::post('imports/{import}/approve', [EventImportController::class, 'approve'])
            ->name('imports.approve');
        Route::post('imports/{import}/reject', [EventImportController::class, 'reject'])
            ->name('imports.reject');

        Route::post('media', [MediaController::class, 'store'])->name('media.store');

        Route::get('restaurants', [AdminRestaurantController::class, 'index'])->name('restaurants.index');
        Route::get('restaurants/{restaurant}/edit', [AdminRestaurantController::class, 'edit'])
            ->name('restaurants.edit');
        Route::put('restaurants/{restaurant}', [AdminRestaurantController::class, 'update'])
            ->name('restaurants.update');

        Route::post('restaurants/{restaurant}/photos', [RestaurantPhotoController::class, 'store'])
            ->name('photos.store');
        Route::post('restaurants/{restaurant}/photos/order', [RestaurantPhotoController::class, 'reorder'])
            ->name('photos.reorder');
        Route::patch('photos/{photo}', [RestaurantPhotoController::class, 'update'])->name('photos.update');
        Route::delete('photos/{photo}', [RestaurantPhotoController::class, 'destroy'])->name('photos.destroy');
    });
});

/*
|--------------------------------------------------------------------------
| Editor-managed standalone pages
|--------------------------------------------------------------------------
| Declared last so every named route above wins the slug.
*/

Route::get('/{page}', [KSLiveController::class, 'page'])
    ->where('page', '[a-z0-9-]+')
    ->name('pages.show');
