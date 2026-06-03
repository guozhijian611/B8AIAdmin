<?php

namespace app\middleware;

use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 全局跨域中间件
 */
class CrossDomain implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        $response = $request->method() === 'OPTIONS'
            ? response('', 204)
            : $handler($request);

        $origin = $request->header('origin', '*');
        $requestHeaders = $request->header('access-control-request-headers', '*');

        $response->withHeaders([
            'Access-Control-Allow-Origin' => $origin,
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => $requestHeaders,
            'Access-Control-Expose-Headers' => 'Content-Type, Authorization',
            'Access-Control-Max-Age' => '86400',
            'Vary' => 'Origin',
        ]);

        return $response;
    }
}
