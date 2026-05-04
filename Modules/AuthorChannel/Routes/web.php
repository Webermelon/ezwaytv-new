<?php

use Illuminate\Support\Facades\Route;
use Modules\AuthorChannel\Http\Controllers\Frontend\AuthorChannelController;

Route::group(['prefix' => 'author-channels', 'middleware' => ['web']], function () {
    Route::get('/', [AuthorChannelController::class, 'index'])->name('author_channels.index');
    Route::get('{id}', [AuthorChannelController::class, 'show'])->name('author_channels.show');
});
