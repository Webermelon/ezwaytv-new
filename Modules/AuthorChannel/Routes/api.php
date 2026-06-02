<?php

use Illuminate\Support\Facades\Route;
use Modules\AuthorChannel\Http\Controllers\API\AuthorChannelAPIController;
use Modules\AuthorChannel\Http\Controllers\API\AuthorChannelVideoController;

/*
|--------------------------------------------------------------------------
| Author Channel API Routes (renamed to Ondemand)
|--------------------------------------------------------------------------
| Base: /api/v3/ondemand
|
| Public  (no auth):
|   GET    /api/v3/ondemand                    → index (list + search)
|   GET    /api/v3/ondemand/{username}         → show (profile)
|   GET    /api/v3/ondemand/{username}/videos  → videos (paginated)
|
| Authenticated (Bearer token via Sanctum):
|   GET    /api/v3/ondemand/my                 → my channels
|   POST   /api/v3/ondemand                    → create channel
|   PUT    /api/v3/ondemand/{id}               → update own channel
|   DELETE /api/v3/ondemand/{id}               → delete own channel
*/

// ------------------------------------------------------------------
// Public routes (no auth)
// ------------------------------------------------------------------
Route::prefix('api/v3/ondemand')->group(function () {

    Route::get('/',                        [AuthorChannelAPIController::class, 'index']);
    Route::get('{username}/videos',        [AuthorChannelAPIController::class, 'videos']);
    Route::get('{username}',               [AuthorChannelAPIController::class, 'show']);

});

// ------------------------------------------------------------------
// Authenticated routes
// ------------------------------------------------------------------
Route::prefix('api/v3/ondemand')->middleware(['api', 'auth:sanctum'])->group(function () {

    Route::get('my',                       [AuthorChannelAPIController::class, 'myChannels']);
    Route::post('/',                       [AuthorChannelAPIController::class, 'store']);
    Route::put('{id}',                     [AuthorChannelAPIController::class, 'update']);
    Route::delete('{id}',                  [AuthorChannelAPIController::class, 'destroy']);

});

// ------------------------------------------------------------------
// Legacy: video assignment (kept for backward compat)
// ------------------------------------------------------------------
Route::group(['prefix' => 'api', 'middleware' => ['api', 'auth:sanctum']], function () {
    Route::get('ondemand/{id}/videos',                  [AuthorChannelVideoController::class, 'index'])->name('api.ondemand.videos.index');
    Route::post('ondemand/{id}/videos/assign',          [AuthorChannelVideoController::class, 'assign'])->name('api.ondemand.videos.assign');
    Route::delete('ondemand/{id}/videos/{videoId}',     [AuthorChannelVideoController::class, 'unassign'])->name('api.ondemand.videos.unassign');
});

