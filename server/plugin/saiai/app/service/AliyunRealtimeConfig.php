<?php

namespace plugin\saiai\app\service;

use plugin\saiai\app\model\config\AiConfig;
use plugin\saiadmin\exception\ApiException;

class AliyunRealtimeConfig
{
    public const DEFAULT_MODEL = 'qwen3-omni-flash-realtime-2025-12-01';
    public const DEFAULT_URL = 'wss://dashscope.aliyuncs.com/api-ws/v1/realtime';
    public const DEFAULT_VOICE = 'Ethan';

    public static function resolve(?int $configId = null): array
    {
        $query = AiConfig::where('type', 'aliyun_realtime')->where('status', 1);
        $config = $configId
            ? (clone $query)->where('id', $configId)->findOrEmpty()
            : (clone $query)->where('is_default', 1)->findOrEmpty();

        if ($config->isEmpty()) {
            $config = $query->findOrEmpty();
        }

        if ($config->isEmpty()) {
            throw new ApiException('未找到已启用的阿里云实时模型配置');
        }

        $data = $config->toArray();
        $apiKey = trim((string) ($data['ai_key'] ?? ''));
        if ($apiKey === '') {
            $apiKey = (string) env('DASHSCOPE_API_KEY', '');
        }

        if ($apiKey === '') {
            throw new ApiException('阿里云实时模型配置缺少 API Key');
        }

        $model = trim((string) ($data['model'] ?? '')) ?: self::DEFAULT_MODEL;
        $apiUrl = self::normalizeRealtimeUrl((string) ($data['ai_url'] ?? ''), $model);
        $options = self::decodeOptions((string) ($data['options'] ?? ''));

        return [
            'id' => (int) $data['id'],
            'name' => (string) ($data['name'] ?? ''),
            'apiUrl' => $apiUrl,
            'apiKey' => $apiKey,
            'model' => $model,
            'options' => $options,
        ];
    }

    public static function normalizeRealtimeUrl(string $apiUrl, string $model): string
    {
        $apiUrl = trim($apiUrl);
        if ($apiUrl === '') {
            $apiUrl = self::DEFAULT_URL;
        }

        if (str_starts_with($apiUrl, 'https://')) {
            $apiUrl = 'wss://' . substr($apiUrl, 8);
        } elseif (str_starts_with($apiUrl, 'http://')) {
            $apiUrl = 'ws://' . substr($apiUrl, 7);
        }

        $parts = parse_url($apiUrl);
        if (($parts['scheme'] ?? '') === '') {
            $apiUrl = 'wss://' . ltrim($apiUrl, '/');
        }

        if (!str_contains($apiUrl, '?')) {
            return rtrim($apiUrl, '/') . '?model=' . rawurlencode($model);
        }

        parse_str((string) parse_url($apiUrl, PHP_URL_QUERY), $query);
        if (!isset($query['model']) || trim((string) $query['model']) === '') {
            return $apiUrl . '&model=' . rawurlencode($model);
        }

        return $apiUrl;
    }

    public static function defaultSession(array $options = []): array
    {
        return [
            'modalities' => $options['modalities'] ?? ['text', 'audio'],
            'voice' => (string) ($options['voice'] ?? self::DEFAULT_VOICE),
            'input_audio_format' => 'pcm',
            'output_audio_format' => 'pcm',
            'instructions' => (string) ($options['instructions'] ?? '你是 B8AIadmin 的实时语音助手，请用准确、简洁、友好的中文回答用户。'),
            'turn_detection' => $options['turn_detection'] ?? null,
            'input_audio_transcription' => $options['input_audio_transcription'] ?? [
                'model' => 'qwen3-asr-flash-realtime',
            ],
        ];
    }

    private static function decodeOptions(string $options): array
    {
        $options = trim($options);
        if ($options === '') {
            return [];
        }

        $data = json_decode($options, true);
        return is_array($data) ? $data : [];
    }
}
