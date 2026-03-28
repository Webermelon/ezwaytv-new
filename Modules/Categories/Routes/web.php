<?php

use Illuminate\Support\Facades\Route;
use Modules\Categories\Http\Controllers\CategoriesController;

Route::group(['prefix' => 'app', 'as' => 'backend.', 'middleware' => ['auth', 'admin']], function () {

    Route::group(['prefix' => '/categories', 'as' => 'categories.'], function () {
        Route::get('/index_data', [CategoriesController::class, 'index_data'])->name('index_data');
        Route::post('bulk-action', [CategoriesController::class, 'bulk_action'])->name('bulk_action');
        Route::post('update-status/{id}', [CategoriesController::class, 'update_status'])->name('update_status');
        Route::post('restore/{id}', [CategoriesController::class, 'restore'])->name('restore');
        Route::delete('force-delete/{id}', [CategoriesController::class, 'forceDelete'])->name('force_delete');
    });

    Route::resource('categories', CategoriesController::class)->names('categories');
});
