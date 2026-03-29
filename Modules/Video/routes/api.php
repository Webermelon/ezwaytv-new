<?php

use Illuminate\Support\Facades\Route;
use Modules\Video\Http\Controllers\API\VideosController;
use Modules\Video\Http\Controllers\API\CreatorChannelController;

Route::get('video-list', [VideosController::class, 'videoList']);
Route::get('video-details', [VideosController::class, 'videoDetails']);
Route::prefix('v3')->group(function () {
	Route::get('video-list', [VideosController::class, 'videoListV3']);
});

// -----------------------------------------------------------------------
// Creator Channel – public (no auth required)
// -----------------------------------------------------------------------
Route::prefix('creator')->group(function () {
	Route::get('public-channels', [CreatorChannelController::class, 'publicChannels']);
	Route::get('public-channels/{slug}', [CreatorChannelController::class, 'publicChannelDetail']);
});

// -----------------------------------------------------------------------
// Creator Channel – requires active subscription
// -----------------------------------------------------------------------
Route::prefix('creator')->middleware(['auth:sanctum', 'checkApiDevice'])->group(function () {
	Route::get('channels', [CreatorChannelController::class, 'index']);
	Route::post('channels', [CreatorChannelController::class, 'store']);
	Route::get('channels/{id}', [CreatorChannelController::class, 'show']);
	Route::put('channels/{id}', [CreatorChannelController::class, 'update']);
	Route::delete('channels/{id}', [CreatorChannelController::class, 'destroy']);

	Route::get('channels/{id}/videos', [CreatorChannelController::class, 'channelVideos']);
	Route::post('channels/{id}/videos', [CreatorChannelController::class, 'addVideo']);
	Route::delete('channels/{channelId}/videos/{videoId}', [CreatorChannelController::class, 'removeVideo']);
});


