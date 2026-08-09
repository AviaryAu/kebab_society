<?php

declare(strict_types=1);

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
