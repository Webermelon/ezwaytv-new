<?php

use Illuminate\Support\Facades\Route;
Route::group(['prefix' => 'on-demand', 'middleware' => ['web']], function () {
    Route::view('/', 'react-modernization')->name('author_channels.index');
    Route::view('{username}', 'react-modernization')->name('author_channels.show');
});

Route::group(['prefix' => 'author-channels', 'middleware' => ['web']], function () {
    Route::get('/', fn () => redirect()->route('author_channels.index', [], 301));
    Route::get('{username}', fn ($username) => redirect()->route('author_channels.show', $username, 301));
});
