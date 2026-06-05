<?php

use plugin\saipay\app\api\controller\DemoController;
use plugin\saipay\app\api\controller\NotifyController;
use plugin\saipay\app\admin\controller\OrderController;
use Webman\Route;

Route::group('/app/saipay/api/demo', function () {
    Route::get('/paymentMethods', [DemoController::class, 'paymentMethods']);
    Route::get('/manualScan', [DemoController::class, 'manualScan']);
    Route::post('/confirmManualPaid', [DemoController::class, 'confirmManualPaid']);
});

Route::post('/app/saipay/admin/Order/confirmManualPaid', [OrderController::class, 'confirmManualPaid']);
Route::post('/app/saipay/admin/Order/rejectManualPaid', [OrderController::class, 'rejectManualPaid']);

Route::group('/app/saipay/api/notify', function () {
    Route::post('/alipay', [NotifyController::class, 'alipay']);
    Route::post('/wechat', [NotifyController::class, 'wechat']);
    Route::post('/unipay', [NotifyController::class, 'unipay']);
    Route::post('/checkOrder', [NotifyController::class, 'checkOrder']);
});
