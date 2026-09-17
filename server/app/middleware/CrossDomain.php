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

        return self::withCorsHeaders($request, $response);
    }

    public static function withCorsHeaders(Request $request, Response $response): Response
    {
        $origin = $request->header('origin');
        
        $defaultOrigins = 'http://127.0.0.1:5173,http://localhost:5173,http://127.0.0.1:3006,http://localhost:3006';
        $allowedOriginsStr = env('CORS_ORIGINS', $defaultOrigins);
        $allowedOriginsStr = $allowedOriginsStr === null ? $defaultOrigins : $allowedOriginsStr;
        $allowedOrigins = array_map('trim', explode(',', $allowedOriginsStr));
        
        if ($origin && in_array($origin, $allowedOrigins, true)) {
            $response->withHeaders([
                'Access-Control-Allow-Origin' => $origin,
                'Access-Control-Allow-Credentials' => 'true',
            ]);
        }
        
        $response->withHeaders([
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Authorization, Content-Type, X-Requested-With, Accept, Origin',
            'Access-Control-Expose-Headers' => 'Content-Type, Authorization',
            'Access-Control-Max-Age' => '86400',
            'Vary' => 'Origin',
        ]);

        return $response;
    }
}
