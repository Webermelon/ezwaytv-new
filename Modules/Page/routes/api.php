<?php
use Illuminate\Support\Facades\Route;
use Modules\Page\Http\Controllers\API\PagesController;

Route::get('page-list', [PagesController::class, 'pageList']);
Route::get('page-detail/{slug}', [PagesController::class, 'pageDetail']);
Route::get('faq-list', [PagesController::class, 'faqList']);



