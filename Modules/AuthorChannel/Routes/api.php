<?php

use Illuminate\Support\Facades\Route;
use Modules\AuthorChannel\Http\Controllers\API\AuthorChannelVideoController;

Route::group(['prefix' => 'api', 'middleware' => ['api', 'auth:sanctum']], function () {
    Route::get('author-channels/{id}/videos', [AuthorChannelVideoController::class, 'index'])->name('api.author_channels.videos.index');
    Route::post('author-channels/{id}/videos/assign', [AuthorChannelVideoController::class, 'assign'])->name('api.author_channels.videos.assign');
    Route::delete('author-channels/{id}/videos/{videoId}', [AuthorChannelVideoController::class, 'unassign'])->name('api.author_channels.videos.unassign');
});
