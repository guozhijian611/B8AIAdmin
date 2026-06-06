<?php

use plugin\b8cms\app\api\controller\SiteController;
use plugin\b8cms\app\api\controller\SiteViewController;
use Webman\Route;

Route::group('/app/b8cms/api', function () {
    Route::get('/site/bootstrap', [SiteController::class, 'bootstrap']);
    Route::get('/content/list', [SiteController::class, 'contentList']);
    Route::get('/content/detail', [SiteController::class, 'contentDetail']);
    Route::get('/comment/list', [SiteController::class, 'commentList']);
    Route::post('/comment/submit', [SiteController::class, 'submitComment']);
    Route::post('/contact/submit', [SiteController::class, 'submitContact']);
});

$sitePath = '/' . trim((string) config('plugin.b8cms.app.site_path', '/cms'), '/');
$sitePath = $sitePath === '/' ? '' : $sitePath;

Route::get($sitePath ?: '/', [SiteViewController::class, 'home']);
Route::get($sitePath . '/{lang:[A-Za-z]{2}-[A-Za-z]{2}}', [SiteViewController::class, 'home']);
Route::get($sitePath . '/sitemap.xml', [SiteViewController::class, 'sitemap']);
Route::get($sitePath . '/{lang:[A-Za-z]{2}-[A-Za-z]{2}}/sitemap.xml', [SiteViewController::class, 'sitemap']);
Route::get($sitePath . '/article/{slug}.html', [SiteViewController::class, 'article']);
Route::get($sitePath . '/product/{slug}.html', [SiteViewController::class, 'product']);
Route::get($sitePath . '/page/{slug}.html', [SiteViewController::class, 'page']);
Route::get($sitePath . '/{lang}/article/{slug}.html', [SiteViewController::class, 'article']);
Route::get($sitePath . '/{lang}/product/{slug}.html', [SiteViewController::class, 'product']);
Route::get($sitePath . '/{lang}/page/{slug}.html', [SiteViewController::class, 'page']);
Route::get($sitePath . '/article/{slug}', [SiteViewController::class, 'article']);
Route::get($sitePath . '/product/{slug}', [SiteViewController::class, 'product']);
Route::get($sitePath . '/page/{slug}', [SiteViewController::class, 'page']);
Route::get($sitePath . '/{lang}/article/{slug}', [SiteViewController::class, 'article']);
Route::get($sitePath . '/{lang}/product/{slug}', [SiteViewController::class, 'product']);
Route::get($sitePath . '/{lang}/page/{slug}', [SiteViewController::class, 'page']);
