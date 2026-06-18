<?php

use plugin\saiboard\app\admin\controller\DatasourceController;
use plugin\saiboard\app\admin\controller\QueryTemplateController;
use plugin\saiboard\app\admin\controller\ScreenController;
use plugin\saiboard\app\api\controller\BoardController;
use Webman\Route;

Route::group('/app/saiboard/admin', function () {
    fastRoute('Screen', ScreenController::class);
    Route::post('/Screen/changeStatus', [ScreenController::class, 'changeStatus']);
    Route::post('/Screen/saveLayout', [ScreenController::class, 'saveLayout']);
    Route::post('/Screen/publish', [ScreenController::class, 'publish']);
    Route::post('/Screen/copy', [ScreenController::class, 'copy']);
    Route::get('/Screen/runtimeMetrics', [ScreenController::class, 'runtimeMetrics']);

    fastRoute('Datasource', DatasourceController::class);
    Route::post('/Datasource/changeStatus', [DatasourceController::class, 'changeStatus']);
    Route::post('/Datasource/test', [DatasourceController::class, 'test']);
    Route::get('/Datasource/options', [DatasourceController::class, 'options']);
    Route::get('/Datasource/schema', [DatasourceController::class, 'schema']);

    fastRoute('QueryTemplate', QueryTemplateController::class);
    Route::post('/QueryTemplate/changeStatus', [QueryTemplateController::class, 'changeStatus']);
    Route::post('/QueryTemplate/preview', [QueryTemplateController::class, 'preview']);
    Route::get('/QueryTemplate/options', [QueryTemplateController::class, 'options']);
});

Route::group('/app/saiboard/api', function () {
    Route::get('/screen/{code}', [BoardController::class, 'getScreen']);
    Route::get('/data', [BoardController::class, 'data']);
});
