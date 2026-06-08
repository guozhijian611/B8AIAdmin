<?php
// +----------------------------------------------------------------------
// | saiadmin [ saiadmin快速开发框架 ]
// +----------------------------------------------------------------------
// | Author: sai <1430792918@qq.com>
// +----------------------------------------------------------------------
use Webman\Route;
use support\Response;
use Tinywan\Jwt\JwtToken;
use plugin\saiadmin\exception\ApiException;
use plugin\saiadmin\app\cache\ConfigCache;
use plugin\saiadmin\app\cache\DictCache;

if (!function_exists('getCurrentInfo')) {
    /**
     * 获取当前登录用户
     */
    function getCurrentInfo(): bool|array
    {
        if (!request()) {
            return false;
        }
        try {
            $token = JwtToken::getExtend();
        } catch (\Throwable $e) {
            return false;
        }
        return $token;
    }
}

if (!function_exists('fastRoute')) {
    /**
     * 快速注册路由[index|save|update|read|destroy|import|export]
     * @param string $name
     * @param string $controller
     * @return void
     */
    function fastRoute(string $name, string $controller): void
    {
        $name = trim($name, '/');
        if (method_exists($controller, 'index'))
            Route::get("/$name/index", [$controller, 'index']);
        if (method_exists($controller, 'save'))
            Route::post("/$name/save", [$controller, 'save']);
        if (method_exists($controller, 'update'))
            Route::put("/$name/update", [$controller, 'update']);
        if (method_exists($controller, 'read'))
            Route::get("/$name/read", [$controller, 'read']);
        if (method_exists($controller, 'destroy'))
            Route::delete("/$name/destroy", [$controller, 'destroy']);
        if (method_exists($controller, 'import'))
            Route::post("/$name/import", [$controller, 'import']);
        if (method_exists($controller, 'export'))
            Route::post("/$name/export", [$controller, 'export']);
    }
}

if (!function_exists('downloadFile')) {
    /**
     * 下载模板
     * @param $file_name
     * @return Response
     */
    function downloadFile($file_name): Response
    {
        $base_dir = config('plugin.saiadmin.saithink.template', base_path() . '/public/template');
        if (file_exists($base_dir . DIRECTORY_SEPARATOR . $file_name)) {
            return response()->download($base_dir . DIRECTORY_SEPARATOR . $file_name, urlencode($file_name));
        } else {
            throw new ApiException('模板不存在');
        }
    }
}

if (!function_exists('formatBytes')) {
    /**
     * 根据字节计算大小
     * @param $bytes
     * @return string
     */
    function formatBytes($bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}

if (!function_exists('getConfigGroup')) {
    /**
     * 读取配置组
     * @param $group
     * @param bool $toKeyValue 是否展开为 key => value
     * @return array
     */
    function getConfigGroup($group, bool $toKeyValue = false): array
    {
        return ConfigCache::getConfig($group, $toKeyValue);
    }
}

if (!function_exists('dictDataList')) {
    /**
     * 根据字典编码获取字典列表
     * @param string $code 字典编码
     * @return array
     */
    function dictDataList(string $code): array
    {
        return DictCache::getDict($code);
    }
}

if (!function_exists('queue_send')) {
    /**
     * 投递队列任务
     */
    function queue_send(
        int $configId,
        object|string $class,
        string $method,
        array $arguments = [],
        int $delay = 0,
        string $source = 'saiadmin'
    ): bool {
        (new \plugin\saiadmin\app\service\queue\QueuePublisherService())->dispatch(
            $configId,
            $class,
            $method,
            $arguments,
            $delay,
            $source
        );
        return true;
    }
}

if (!function_exists('redis_send')) {
    /**
     * 按 Redis 队列名称投递任务
     */
    function redis_send(
        object|string|null $class = null,
        string $method = '',
        array $arguments = [],
        int $delay = 0,
        string $queueName = 'fast_queue',
        string $connection = 'default'
    ): bool {
        if ($class === null) {
            throw new \plugin\saiadmin\exception\ApiException('请填写执行类');
        }
        $config = \plugin\saiadmin\app\model\tool\QueueConfig::where('driver', 'redis')
            ->where('connection', $connection)
            ->where('queue_name', $queueName)
            ->where('status', 1)
            ->findOrEmpty();
        if ($config->isEmpty()) {
            throw new \plugin\saiadmin\exception\ApiException('Redis队列配置不存在或未启用');
        }
        return queue_send((int) $config->id, $class, $method, $arguments, $delay, 'redis');
    }
}

if (!function_exists('rabbitmq_send')) {
    /**
     * 按 RabbitMQ 队列名称投递任务
     */
    function rabbitmq_send(
        object|string|null $class = null,
        string $method = '',
        array $arguments = [],
        int $delay = 0,
        string $queueName = 'fast_queue',
        string $connection = 'default'
    ): bool {
        if ($class === null) {
            throw new \plugin\saiadmin\exception\ApiException('请填写执行类');
        }
        $config = \plugin\saiadmin\app\model\tool\QueueConfig::where('driver', 'rabbitmq')
            ->where('connection', $connection)
            ->where('queue_name', $queueName)
            ->where('status', 1)
            ->findOrEmpty();
        if ($config->isEmpty()) {
            throw new \plugin\saiadmin\exception\ApiException('RabbitMQ队列配置不存在或未启用');
        }
        return queue_send((int) $config->id, $class, $method, $arguments, $delay, 'rabbitmq');
    }
}

if (!function_exists('queue_publish')) {
    /**
     * 投递外部消息，消息体会直接发送完整 JSON 给第三方消费者
     */
    function queue_publish(
        int $configId,
        string $eventName,
        array $payload,
        array $headers = [],
        int $delay = 0,
        string $messageKey = '',
        string $source = 'saiadmin'
    ): bool {
        (new \plugin\saiadmin\app\service\queue\QueueMessagePublisherService())->publish(
            $configId,
            $eventName,
            $payload,
            $headers,
            $delay,
            $messageKey,
            $source
        );
        return true;
    }
}

if (!function_exists('redis_publish')) {
    /**
     * 按 Redis 队列名称投递外部消息
     */
    function redis_publish(
        string $eventName,
        array $payload,
        array $headers = [],
        int $delay = 0,
        string $queueName = 'external_queue',
        string $connection = 'default',
        string $messageKey = '',
        string $source = 'saiadmin'
    ): bool {
        $config = \plugin\saiadmin\app\model\tool\QueueConfig::where('driver', 'redis')
            ->where('message_mode', 'external_message')
            ->where('connection', $connection)
            ->where('queue_name', $queueName)
            ->where('status', 1)
            ->findOrEmpty();
        if ($config->isEmpty()) {
            throw new \plugin\saiadmin\exception\ApiException('Redis外部消息队列配置不存在或未启用');
        }
        return queue_publish((int) $config->id, $eventName, $payload, $headers, $delay, $messageKey, $source);
    }
}

if (!function_exists('rabbitmq_publish')) {
    /**
     * 按 RabbitMQ 队列名称投递外部消息
     */
    function rabbitmq_publish(
        string $eventName,
        array $payload,
        array $headers = [],
        int $delay = 0,
        string $queueName = 'external_queue',
        string $connection = 'default',
        string $messageKey = '',
        string $source = 'saiadmin'
    ): bool {
        $config = \plugin\saiadmin\app\model\tool\QueueConfig::where('driver', 'rabbitmq')
            ->where('message_mode', 'external_message')
            ->where('connection', $connection)
            ->where('queue_name', $queueName)
            ->where('status', 1)
            ->findOrEmpty();
        if ($config->isEmpty()) {
            throw new \plugin\saiadmin\exception\ApiException('RabbitMQ外部消息队列配置不存在或未启用');
        }
        return queue_publish((int) $config->id, $eventName, $payload, $headers, $delay, $messageKey, $source);
    }
}
