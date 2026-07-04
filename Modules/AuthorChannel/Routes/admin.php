<?php

use Illuminate\Support\Facades\Route;
use Modules\AuthorChannel\Http\Controllers\Admin\AuthorChannelController;

Route::group(['prefix' => 'app', 'as' => 'backend.', 'middleware' => ['web', 'auth', 'admin']], function () {
    Route::get('on-demand-channels',                        [AuthorChannelController::class, 'index'])->name('author_channels.index');
    Route::get('on-demand-channels/index_data',             [AuthorChannelController::class, 'index_data'])->name('author_channels.index_data');
    Route::post('on-demand-channels/bulk_action',           [AuthorChannelController::class, 'bulk_action'])->name('author_channels.bulk_action');

    Route::get('on-demand-channels/create',                 [AuthorChannelController::class, 'create'])->name('author_channels.create');
    Route::post('on-demand-channels/store',                 [AuthorChannelController::class, 'store'])->name('author_channels.store');
    Route::get('on-demand-channels/{id}/edit',              [AuthorChannelController::class, 'edit'])->name('author_channels.edit');
    Route::post('on-demand-channels/{id}/update',           [AuthorChannelController::class, 'update'])->name('author_channels.update');
    Route::delete('on-demand-channels/{id}',                [AuthorChannelController::class, 'destroy'])->name('author_channels.delete');
    Route::post('on-demand-channels/{id}/update-status',    [AuthorChannelController::class, 'update_status'])->name('author_channels.update_status');
    Route::post('on-demand-channels/{id}/restore',          [AuthorChannelController::class, 'restore'])->name('author_channels.restore');
    Route::delete('on-demand-channels/{id}/force-delete',   [AuthorChannelController::class, 'forceDelete'])->name('author_channels.force_delete');

    // Video assignment
    Route::post('on-demand-channels/{id}/videos/assign',               [AuthorChannelController::class, 'assignVideo'])->name('author_channels.videos.assign');
    Route::post('on-demand-channels/{id}/videos/{videoId}/unassign',   [AuthorChannelController::class, 'unassignVideo'])->name('author_channels.videos.unassign');
    Route::get('on-demand-channels/{id}/videos/available',             [AuthorChannelController::class, 'availableVideos'])->name('author_channels.videos.available');
    Route::post('on-demand-channels/{id}/playlists',                   [AuthorChannelController::class, 'storePlaylist'])->name('author_channels.playlists.store');
    Route::post('on-demand-channels/{id}/playlists/{playlistId}/videos', [AuthorChannelController::class, 'addPlaylistVideo'])->name('author_channels.playlists.videos.add');
    Route::post('on-demand-channels/{id}/playlists/{playlistId}/videos/{videoId}/remove', [AuthorChannelController::class, 'removePlaylistVideo'])->name('author_channels.playlists.videos.remove');
    Route::delete('on-demand-channels/{id}/playlists/{playlistId}',    [AuthorChannelController::class, 'destroyPlaylist'])->name('author_channels.playlists.destroy');

    Route::get('author-channels', fn () => redirect()->route('backend.author_channels.index', [], 301));
    Route::get('author-channels/create', fn () => redirect()->route('backend.author_channels.create', [], 301));
    Route::get('author-channels/{id}/edit', fn ($id) => redirect()->route('backend.author_channels.edit', $id, 301));
    Route::get('author-channels/index_data', [AuthorChannelController::class, 'index_data']);
    Route::post('author-channels/bulk_action', [AuthorChannelController::class, 'bulk_action']);
    Route::post('author-channels/store', [AuthorChannelController::class, 'store']);
    Route::post('author-channels/{id}/update', [AuthorChannelController::class, 'update']);
    Route::delete('author-channels/{id}', [AuthorChannelController::class, 'destroy']);
    Route::post('author-channels/{id}/update-status', [AuthorChannelController::class, 'update_status']);
    Route::post('author-channels/{id}/restore', [AuthorChannelController::class, 'restore']);
    Route::delete('author-channels/{id}/force-delete', [AuthorChannelController::class, 'forceDelete']);
    Route::post('author-channels/{id}/videos/assign', [AuthorChannelController::class, 'assignVideo']);
    Route::post('author-channels/{id}/videos/{videoId}/unassign', [AuthorChannelController::class, 'unassignVideo']);
    Route::get('author-channels/{id}/videos/available', [AuthorChannelController::class, 'availableVideos']);
    Route::post('author-channels/{id}/playlists', [AuthorChannelController::class, 'storePlaylist']);
    Route::post('author-channels/{id}/playlists/{playlistId}/videos', [AuthorChannelController::class, 'addPlaylistVideo']);
    Route::post('author-channels/{id}/playlists/{playlistId}/videos/{videoId}/remove', [AuthorChannelController::class, 'removePlaylistVideo']);
    Route::delete('author-channels/{id}/playlists/{playlistId}', [AuthorChannelController::class, 'destroyPlaylist']);
});
