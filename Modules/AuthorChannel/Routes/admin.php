<?php

use Illuminate\Support\Facades\Route;
use Modules\AuthorChannel\Http\Controllers\Admin\AuthorChannelController;

Route::group(['prefix' => 'app', 'as' => 'backend.', 'middleware' => ['web', 'auth', 'admin']], function () {
    Route::get('author-channels', [AuthorChannelController::class, 'index'])->name('author_channels.index');
    Route::get('author-channels/create', [AuthorChannelController::class, 'create'])->name('author_channels.create');
    Route::post('author-channels/store', [AuthorChannelController::class, 'store'])->name('author_channels.store');
    Route::get('author-channels/{id}/edit', [AuthorChannelController::class, 'edit'])->name('author_channels.edit');
    Route::post('author-channels/{id}/update', [AuthorChannelController::class, 'update'])->name('author_channels.update');
    Route::post('author-channels/{id}/delete', [AuthorChannelController::class, 'destroy'])->name('author_channels.delete');
});
