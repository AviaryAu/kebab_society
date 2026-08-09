<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\RestaurantController as AdminRestaurantController;
use App\Http\Controllers\Admin\RestaurantPhotoController;
use App\Http\Controllers\Admin\SessionController;
use App\Http\Controllers\Api\KebabEmergencyController;
use App\Http\Controllers\Api\RestaurantSearchController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\RestaurantController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MapController::class, 'index'])->name('map');

Route::get('/kebabs/{restaurant}', [RestaurantController::class, 'show'])
    ->name('restaurants.show');

Route::get('/leaderboard/{board?}', [LeaderboardController::class, 'show'])
    ->name('leaderboard');

Route::prefix('api')->name('api.')->group(function (): void {
    Route::get('/restaurants', RestaurantSearchController::class)->name('restaurants.search');
    Route::get('/kebab-emergency', KebabEmergencyController::class)->name('kebab-emergency');
});

/*
|--------------------------------------------------------------------------
| The Society's admin
|--------------------------------------------------------------------------
*/

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::middleware('guest')->group(function (): void {
        Route::get('login', [SessionController::class, 'create'])->name('login');
        Route::post('login', [SessionController::class, 'store'])->name('login.attempt');
    });

    Route::middleware(['auth', 'admin'])->group(function (): void {
        Route::post('logout', [SessionController::class, 'destroy'])->name('logout');

        Route::get('/', [AdminRestaurantController::class, 'index'])->name('restaurants.index');
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
