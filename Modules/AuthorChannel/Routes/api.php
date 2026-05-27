<?php

use Illuminate\Support\Facades\Route;
use Modules\AuthorChannel\Http\Controllers\API\AuthorChannelAPIController;
use Modules\AuthorChannel\Http\Controllers\API\AuthorChannelVideoController;

/*
|--------------------------------------------------------------------------
| Author Channel API Routes
|--------------------------------------------------------------------------
| Base: /api/v1/author-channels
|
| Public  (no auth):
|   GET    /api/v1/author-channels                    → index (list + search)
|   GET    /api/v1/author-channels/{username}         → show (profile)
|   GET    /api/v1/author-channels/{username}/videos  → videos (paginated)
|
| Authenticated (Bearer token via Sanctum):
|   GET    /api/v1/author-channels/my                 → my channels
|   POST   /api/v1/author-channels                    → create channel
|   PUT    /api/v1/author-channels/{id}               → update own channel
|   DELETE /api/v1/author-channels/{id}               → delete own channel
*/

// ------------------------------------------------------------------
// Public routes (no auth)
// ------------------------------------------------------------------
Route::prefix('api/v1/author-channels')->group(function () {

    Route::get('/',                        [AuthorChannelAPIController::class, 'index']);
    Route::get('{username}/videos',        [AuthorChannelAPIController::class, 'videos']);
    Route::get('{username}',               [AuthorChannelAPIController::class, 'show']);

});

// ------------------------------------------------------------------
// Authenticated routes
// ------------------------------------------------------------------
Route::prefix('api/v1/author-channels')->middleware(['api', 'auth:sanctum'])->group(function () {

    Route::get('my',                       [AuthorChannelAPIController::class, 'myChannels']);
    Route::post('/',                       [AuthorChannelAPIController::class, 'store']);
    Route::put('{id}',                     [AuthorChannelAPIController::class, 'update']);
    Route::delete('{id}',                  [AuthorChannelAPIController::class, 'destroy']);

});

// ------------------------------------------------------------------
// Legacy: video assignment (kept for backward compat)
// ------------------------------------------------------------------
Route::group(['prefix' => 'api', 'middleware' => ['api', 'auth:sanctum']], function () {
    Route::get('author-channels/{id}/videos',                  [AuthorChannelVideoController::class, 'index'])->name('api.author_channels.videos.index');
    Route::post('author-channels/{id}/videos/assign',          [AuthorChannelVideoController::class, 'assign'])->name('api.author_channels.videos.assign');
    Route::delete('author-channels/{id}/videos/{videoId}',     [AuthorChannelVideoController::class, 'unassign'])->name('api.author_channels.videos.unassign');
});

