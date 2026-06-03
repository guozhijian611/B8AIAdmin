<?php
/**
 * Here is your custom functions.
 */

use Webman\Http\Response;

if (!function_exists('b8_trace_id')) {
    /**
     * 获取当前请求 Trace ID。
     */
    function b8_trace_id(): ?string
    {
        if (!class_exists(\OpenB8\WebmanOtelTrace\Support\TraceContext::class)) {
            return null;
        }

        try {
            $context = \OpenB8\WebmanOtelTrace\Support\TraceContext::current();
        } catch (\Throwable) {
            return null;
        }

        $traceId = $context['trace_id'] ?? null;
        return is_string($traceId) && $traceId !== '' ? $traceId : null;
    }
}

if (!function_exists('b8_json_response')) {
    /**
     * 统一 JSON 响应；安装并启用 trace 上下文时自动返回 trace_id。
     */
    function b8_json_response(array $payload, int $option = JSON_UNESCAPED_UNICODE): Response
    {
        $traceId = b8_trace_id();
        if ($traceId !== null && !array_key_exists('trace_id', $payload)) {
            $payload['trace_id'] = $traceId;
        }

        return json($payload, $option);
    }
}

if (!function_exists('b8_success')) {
    /**
     * 成功 JSON 响应，兼容 SaiAdmin success($data, $msg) 的调用语义。
     */
    function b8_success(array|string $data = [], string $msg = 'success', int $option = JSON_UNESCAPED_UNICODE): Response
    {
        if (is_string($data)) {
            $msg = $data;
        }

        return b8_json_response(['code' => 200, 'message' => $msg, 'data' => $data], $option);
    }
}

if (!function_exists('b8_fail')) {
    /**
     * 失败 JSON 响应，HTTP 状态仍保持 200，业务状态通过 code 表示。
     */
    function b8_fail(string $msg = 'fail', int $code = 400, int $option = JSON_UNESCAPED_UNICODE): Response
    {
        return b8_json_response(['code' => $code, 'message' => $msg], $option);
    }
}
