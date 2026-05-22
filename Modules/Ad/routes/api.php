<?php
use Modules\Ad\Http\Controllers\API\VastAdsSettingController;
use Modules\Ad\Http\Controllers\API\CustomAdsSettingController;
use Illuminate\Support\Facades\Route;
use Modules\Ad\Http\Controllers\API\VastAdsController;
use Modules\Ad\Http\Controllers\API\VastXmlController;


Route::get('get-vast-ads', [VastAdsSettingController::class, 'vastadsList']);
Route::get('get-custom-ads', [CustomAdsSettingController::class, 'customadsList']);


        Route::prefix('vast-ads')->group(function() {
            Route::get('get-active', [VastAdsController::class, 'getActiveAds'])->name('api.vast-ads.get-active');
        });

        Route::prefix('custom-ads')->group(function() {
            Route::get('get-active', [CustomAdsSettingController::class, 'getActiveAds'])->name('api.custom-ads.get-active');
        });

        // VAST XML Generator Routes (No authentication needed - public XML endpoints)
        Route::prefix('vast-xml')->group(function() {
            Route::get('generate/{id}', [VastXmlController::class, 'generate'])->name('api.vast-xml.generate');
            Route::get('wrapper/{id}', [VastXmlController::class, 'generateWrapper'])->name('api.vast-xml.wrapper');
            Route::post('vmap', [VastXmlController::class, 'generateVmap'])->name('api.vast-xml.vmap');
        });

