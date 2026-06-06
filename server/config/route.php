<?php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

use app\middleware\CrossDomain;
use support\exception\PageNotFoundException;
use support\Request;
use Webman\Route;

Route::get('/apidoc/openapi/{appKey}', [app\controller\ApidocOpenapiController::class, 'show']);
Route::get('/', [app\controller\IndexController::class, 'index']);
Route::get('/admin', [app\controller\IndexController::class, 'admin']);
Route::get('/admin/', [app\controller\IndexController::class, 'admin']);
Route::get('/h5', [app\controller\IndexController::class, 'h5']);
Route::get('/h5/', [app\controller\IndexController::class, 'h5']);
Route::fallback(function (Request $request) {
    if ($request->method() === 'OPTIONS') {
        return CrossDomain::withCorsHeaders($request, response('', 204));
    }

    throw new PageNotFoundException();
});
