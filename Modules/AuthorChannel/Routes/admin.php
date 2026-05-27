<?php

use Illuminate\Support\Facades\Route;
use Modules\AuthorChannel\Http\Controllers\Admin\AuthorChannelController;

Route::group(['prefix' => 'app', 'as' => 'backend.', 'middleware' => ['web', 'auth', 'admin']], function () {
    Route::get('author-channels',                        [AuthorChannelController::class, 'index'])->name('author_channels.index');
    Route::get('author-channels/index_data',             [AuthorChannelController::class, 'index_data'])->name('author_channels.index_data');
    Route::post('author-channels/bulk_action',           [AuthorChannelController::class, 'bulk_action'])->name('author_channels.bulk_action');

    Route::get('author-channels/create',                 [AuthorChannelController::class, 'create'])->name('author_channels.create');
    Route::post('author-channels/store',                 [AuthorChannelController::class, 'store'])->name('author_channels.store');
    Route::get('author-channels/{id}/edit',              [AuthorChannelController::class, 'edit'])->name('author_channels.edit');
    Route::post('author-channels/{id}/update',           [AuthorChannelController::class, 'update'])->name('author_channels.update');
    Route::delete('author-channels/{id}',                [AuthorChannelController::class, 'destroy'])->name('author_channels.delete');
    Route::post('author-channels/{id}/update-status',    [AuthorChannelController::class, 'update_status'])->name('author_channels.update_status');
    Route::post('author-channels/{id}/restore',          [AuthorChannelController::class, 'restore'])->name('author_channels.restore');
    Route::delete('author-channels/{id}/force-delete',   [AuthorChannelController::class, 'forceDelete'])->name('author_channels.force_delete');

    // Video assignment
    Route::post('author-channels/{id}/videos/assign',               [AuthorChannelController::class, 'assignVideo'])->name('author_channels.videos.assign');
    Route::post('author-channels/{id}/videos/{videoId}/unassign',   [AuthorChannelController::class, 'unassignVideo'])->name('author_channels.videos.unassign');
    Route::get('author-channels/{id}/videos/available',             [AuthorChannelController::class, 'availableVideos'])->name('author_channels.videos.available');
});
