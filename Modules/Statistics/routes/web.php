<?php

use Illuminate\Support\Facades\Route;
use Modules\Statistics\Http\Controllers\Backend\StatisticsController;
use Modules\Statistics\Http\Controllers\Backend\ContentBoostController;

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
        Route::get('/ondemand',  [StatisticsController::class, 'ondemand'])->name('ondemand');
        Route::get('/page-views',  [StatisticsController::class, 'pageViews'])->name('page_views');
        Route::get('/play-events', [StatisticsController::class, 'playEvents'])->name('play_events');
        Route::get('/settings',  [StatisticsController::class, 'settings'])->name('settings');
        Route::get('/booster-settings', [StatisticsController::class, 'boosterSettings'])->name('booster_settings');
        Route::post('/settings', [StatisticsController::class, 'saveSettings'])->name('settings.save');

        // Content Booster (admin only)
        Route::get('/booster',          [ContentBoostController::class, 'index'])->name('booster');
        Route::get('/booster/search',   [ContentBoostController::class, 'search'])->name('booster.search');
        Route::get('/booster/stats',    [ContentBoostController::class, 'stats'])->name('booster.stats');
        Route::post('/booster/save',    [ContentBoostController::class, 'save'])->name('booster.save');
        Route::delete('/booster/{id}',  [ContentBoostController::class, 'destroy'])->name('booster.destroy');
    });
});
