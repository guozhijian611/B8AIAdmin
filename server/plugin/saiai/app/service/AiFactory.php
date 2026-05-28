<?php
// +----------------------------------------------------------------------
// | saiadmin [ saiadmin快速开发框架 ]
// +----------------------------------------------------------------------
// | Author: sai <1430792918@qq.com>
// +----------------------------------------------------------------------
namespace plugin\saiai\app\service;

use plugin\saiadmin\exception\ApiException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\AI\Platform\Bridge\Generic\Factory as GenericPlatformFactory;
use Symfony\AI\Platform\Bridge\Gemini\Factory as GeminiPlatformFactory;
use Symfony\AI\Platform\Bridge\OpenAi\Factory as OpenAIPlatformFactory;
use Symfony\AI\Platform\Bridge\DeepSeek\Factory as DeepPlatformFactory;
use Symfony\AI\Agent\Agent;
use Symfony\AI\Agent\Toolbox\AgentProcessor;
use Symfony\AI\Agent\Toolbox\Toolbox;
use plugin\saiai\app\tool\DocTool;
use plugin\saiai\app\tool\DbTool;
use plugin\saiai\app\model\config\AiConfig;

class AiFactory
{
    private const REQUEST_TIMEOUT = 60;

    private const DEEPSEEK_MODELS = [
        'deepseek-chat',
        'deepseek-reasoner',
    ];

    public static function createAgent(string $type, ?string $model = null, bool $enableTools = true): Agent
    {
        $resolved = self::resolveConfig($type, $model);
        $apiUrl = $resolved['apiUrl'];
        $apiKey = $resolved['apiKey'];
        $resolvedModel = $resolved['model'];
        $platformType = $resolved['platformType'];
        $httpClient = HttpClient::create([
            'timeout' => self::REQUEST_TIMEOUT,
            'max_duration' => self::REQUEST_TIMEOUT + 5,
        ]);

        switch ($platformType) {
            case 'generic':
                $platform = GenericPlatformFactory::createPlatform($apiUrl, $apiKey, $httpClient);
                break;
            case 'openai':
                $platform = OpenAIPlatformFactory::createPlatform($apiKey, $httpClient);
                break;
            case 'deepseek':
                $platform = DeepPlatformFactory::createPlatform($apiKey, $httpClient);
                break;
            case 'gemini':
                $platform = GeminiPlatformFactory::createPlatform($apiKey, $httpClient);
                break;
            default:
                throw new ApiException('不支持的模型平台：' . $platformType);
        }

        if (!$enableTools) {
            return new Agent($platform, $resolvedModel);
        }

        $toolbox = new Toolbox([
            new DocTool(),
            new DbTool(),
        ]);
        $agentProcessor = new AgentProcessor($toolbox);

        return new Agent($platform, $resolvedModel, [$agentProcessor], [$agentProcessor]);
    }

    protected static function resolveConfig(string $type, ?string $model = null): array
    {
        $config = AiConfig::where('type', $type)->where('status', 1)->findOrEmpty();
        if ($config->isEmpty()) {
            $config = AiConfig::where('is_default', 1)->where('status', 1)->findOrEmpty();
        }

        if ($config->isEmpty()) {
            throw new ApiException('未找到可用的 AI 配置，请先在后台启用模型配置');
        }

        $platformType = trim((string) $config->type);
        $apiUrl = self::normalizeApiUrl((string) $config->ai_url, $platformType);
        $apiKey = trim((string) $config->ai_key);
        $resolvedModel = trim((string) ($model ?: $config->model));

        self::validateConfig($platformType, $resolvedModel, $apiUrl, $apiKey);

        return [
            'apiUrl' => $apiUrl,
            'apiKey' => $apiKey,
            'model' => $resolvedModel,
            'platformType' => $platformType,
        ];
    }

    protected static function validateConfig(string $platformType, string $model, string $apiUrl, string $apiKey): void
    {
        if ($apiKey === '') {
            throw new ApiException('当前 AI 配置缺少 API Key');
        }

        if ($model === '') {
            throw new ApiException('当前 AI 配置缺少模型名称');
        }

        switch ($platformType) {
            case 'generic':
                if ($apiUrl === '') {
                    throw new ApiException('Generic 平台必须配置 AI 接口基础地址');
                }
                break;
            case 'deepseek':
                if (!in_array($model, self::DEEPSEEK_MODELS, true)) {
                    throw new ApiException(sprintf(
                        'DeepSeek 平台仅支持模型：%s，当前配置为：%s',
                        implode('、', self::DEEPSEEK_MODELS),
                        $model
                    ));
                }
                break;
            case 'openai':
            case 'gemini':
                break;
            default:
                throw new ApiException('不支持的模型平台：' . $platformType);
        }
    }

    protected static function normalizeApiUrl(string $apiUrl, string $platformType): string
    {
        $apiUrl = rtrim(trim($apiUrl), '/');
        if ($platformType !== 'generic' || $apiUrl === '') {
            return $apiUrl;
        }

        foreach ([
            '/v1/chat/completions',
            '/chat/completions',
            '/v1/embeddings',
            '/embeddings',
        ] as $suffix) {
            if (str_ends_with(strtolower($apiUrl), $suffix)) {
                return substr($apiUrl, 0, -strlen($suffix));
            }
        }

        return $apiUrl;
    }
}
