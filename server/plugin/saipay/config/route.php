<?php

use plugin\saipay\app\api\controller\NotifyController;
use Webman\Route;

Route::group('/app/saipay/api/notify', function () {
    Route::post('/alipay', [NotifyController::class, 'alipay']);
    Route::post('/wechat', [NotifyController::class, 'wechat']);
    Route::post('/unipay', [NotifyController::class, 'unipay']);
    Route::post('/checkOrder', [NotifyController::class, 'checkOrder']);
});
