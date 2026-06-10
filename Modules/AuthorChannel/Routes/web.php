<?php

use Illuminate\Support\Facades\Route;
use Modules\Frontend\Http\Controllers\ReactMetaController;

Route::group(['prefix' => 'on-demand', 'middleware' => ['web']], function () {
    Route::get('/', [ReactMetaController::class, 'onDemandIndex'])->name('author_channels.index');
    Route::get('{username}', [ReactMetaController::class, 'onDemandShow'])->name('author_channels.show');
});

Route::group(['prefix' => 'author-channels', 'middleware' => ['web']], function () {
    Route::get('/', fn () => redirect()->route('author_channels.index', [], 301));
    Route::get('{username}', fn ($username) => redirect()->route('author_channels.show', $username, 301));
});
