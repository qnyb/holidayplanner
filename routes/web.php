<?php

use App\Http\Controllers\TimelineController;
use App\Http\Controllers\TravelSpotController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TimelineController::class, 'index'])->name('home');
Route::post('/spots/{travelSpot}/toggle', [TimelineController::class, 'toggleVisited'])->name('spots.toggle');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::get('travel-spots/export', [TravelSpotController::class, 'export'])
        ->name('travel-spots.export');

    Route::post('travel-spots/import', [TravelSpotController::class, 'import'])
        ->name('travel-spots.import');

    Route::resource('travel-spots', TravelSpotController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    Route::post('travel-spots/fetch-meta', [TravelSpotController::class, 'fetchMeta'])
        ->name('travel-spots.fetch-meta');
});

require __DIR__.'/settings.php';
