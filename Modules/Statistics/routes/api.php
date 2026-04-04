<?php

use Illuminate\Support\Facades\Route;
use Modules\Statistics\Http\Controllers\API\StatisticsController;

// Public stats tracking (auth optional)
Route::prefix('statistics')->group(function () {
    Route::post('track-view',        [StatisticsController::class, 'trackView']);
    Route::post('track-play',        [StatisticsController::class, 'trackPlay']);
    Route::post('update-watch-time', [StatisticsController::class, 'updateWatchTime']);
});
