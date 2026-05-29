<?php

use Illuminate\Support\Facades\Route;
use Modules\AuthorChannel\Http\Controllers\Frontend\AuthorChannelController;

Route::group(['prefix' => 'on-demand', 'middleware' => ['web']], function () {
    Route::get('/', [AuthorChannelController::class, 'index'])->name('author_channels.index');
    Route::get('{username}', [AuthorChannelController::class, 'show'])->name('author_channels.show');
});

Route::group(['prefix' => 'author-channels', 'middleware' => ['web']], function () {
    Route::get('/', fn () => redirect()->route('author_channels.index', [], 301));
    Route::get('{username}', fn ($username) => redirect()->route('author_channels.show', $username, 301));
});
