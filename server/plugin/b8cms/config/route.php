<?php

use plugin\b8cms\app\api\controller\SiteController;
use plugin\b8cms\app\api\controller\SiteViewController;
use Webman\Route;

Route::group('/app/b8cms/api', function () {
    Route::get('/site/bootstrap', [SiteController::class, 'bootstrap']);
    Route::get('/content/list', [SiteController::class, 'contentList']);
    Route::get('/content/detail', [SiteController::class, 'contentDetail']);
    Route::post('/contact/submit', [SiteController::class, 'submitContact']);
});

Route::get('/', [SiteViewController::class, 'home']);
Route::get('/article/{slug}', [SiteViewController::class, 'article']);
Route::get('/product/{slug}', [SiteViewController::class, 'product']);
Route::get('/page/{slug}', [SiteViewController::class, 'page']);
Route::get('/{lang}/article/{slug}', [SiteViewController::class, 'article']);
Route::get('/{lang}/product/{slug}', [SiteViewController::class, 'product']);
Route::get('/{lang}/page/{slug}', [SiteViewController::class, 'page']);
