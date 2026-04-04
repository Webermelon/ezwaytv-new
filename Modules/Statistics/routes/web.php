<?php

use Illuminate\Support\Facades\Route;
use Modules\Statistics\Http\Controllers\Backend\StatisticsController;

Route::group(['prefix' => 'app', 'as' => 'backend.', 'middleware' => ['auth', 'admin']], function () {

    Route::prefix('statistics')->as('statistics.')->group(function () {
        Route::get('/',          [StatisticsController::class, 'index'])->name('index');
        Route::get('/overview',  [StatisticsController::class, 'overview'])->name('overview');
        Route::get('/chart',     [StatisticsController::class, 'chart'])->name('chart');
        Route::get('/top-content', [StatisticsController::class, 'topContent'])->name('top_content');
        Route::get('/devices',   [StatisticsController::class, 'devices'])->name('devices');
        Route::get('/countries', [StatisticsController::class, 'countries'])->name('countries');
        Route::get('/platforms', [StatisticsController::class, 'platforms'])->name('platforms');
        Route::get('/traffic',   [StatisticsController::class, 'traffic'])->name('traffic');
        Route::get('/users',     [StatisticsController::class, 'topUsers'])->name('top_users');
        Route::get('/settings',  [StatisticsController::class, 'settings'])->name('settings');
        Route::post('/settings', [StatisticsController::class, 'saveSettings'])->name('settings.save');
    });
});
