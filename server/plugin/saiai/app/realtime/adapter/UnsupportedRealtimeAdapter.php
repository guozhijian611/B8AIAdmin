<?php

namespace plugin\saiai\app\realtime\adapter;

use plugin\saiai\app\realtime\RealtimeSessionState;

class UnsupportedRealtimeAdapter implements RealtimeProviderAdapterInterface
{
    public function __construct(private readonly string $provider)
    {
    }

    public function name(): string
    {
        return $this->provider;
    }

    public function upstreamUrl(array $config): string
    {
        throw new \RuntimeException($this->provider . ' realtime adapter 尚未配置上游连接');
    }

    public function upstreamHeaders(array $config): array
    {
        return [];
    }

    public function defaultSession(array $options = []): array
    {
        return [
            'modalities' => ['text', 'audio'],
            'instructions' => (string) ($options['instructions'] ?? ''),
            'input_audio_format' => 'pcm16',
            'output_audio_format' => 'pcm16',
            'voice' => (string) ($options['voice'] ?? ''),
            'turn_detection' => $options['turn_detection'] ?? ['type' => 'server_vad'],
            'temperature' => $options['temperature'] ?? 0.8,
            'tools' => $options['tools'] ?? [],
        ];
    }

    public function toProviderEvents(array $event, RealtimeSessionState $state): array
    {
        throw new \RuntimeException($this->provider . ' realtime adapter 尚未实现事件转换');
    }

    public function fromProviderEvent(array $event, RealtimeSessionState $state): array
    {
        return [];
    }
}
