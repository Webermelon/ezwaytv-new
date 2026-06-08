<?php

use Illuminate\Support\Facades\Route;
use Modules\Statistics\Http\Controllers\API\StatisticsController;

// Public stats tracking (auth optional)
Route::prefix('statistics')->group(function () {
    Route::post('track-view',        [StatisticsController::class, 'trackView'])->middleware('throttle:120,1');
    Route::post('track-play',        [StatisticsController::class, 'trackPlay'])->middleware('throttle:120,1');
    Route::post('update-watch-time', [StatisticsController::class, 'updateWatchTime'])->middleware('throttle:240,1');
    Route::get('content-stats',      [StatisticsController::class, 'contentStats']);
});
