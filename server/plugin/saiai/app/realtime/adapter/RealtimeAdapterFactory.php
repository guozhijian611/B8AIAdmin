<?php

namespace plugin\saiai\app\realtime\adapter;

class RealtimeAdapterFactory
{
    public static function make(string $provider): RealtimeProviderAdapterInterface
    {
        return match (strtolower(trim($provider))) {
            'aliyun', 'aliyun_realtime', 'aliyun_qwen', 'qwen', 'qwen_omni' => new AliyunQwenRealtimeAdapter(),
            'openai', 'openai_realtime' => new UnsupportedRealtimeAdapter('openai_realtime'),
            'gemini', 'gemini_live' => new UnsupportedRealtimeAdapter('gemini_live'),
            'local', 'local_pipeline', 'local_realtime' => new UnsupportedRealtimeAdapter('local_realtime'),
            default => new UnsupportedRealtimeAdapter($provider ?: 'unknown'),
        };
    }
}
