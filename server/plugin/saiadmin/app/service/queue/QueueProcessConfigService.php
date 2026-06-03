<?php
// +----------------------------------------------------------------------
// | saiadmin [ saiadmin快速开发框架 ]
// +----------------------------------------------------------------------
// | Author: sai <1430792918@qq.com>
// +----------------------------------------------------------------------
namespace plugin\saiadmin\app\service\queue;

use plugin\saiadmin\process\queue\RabbitmqQueueConsumer;
use plugin\saiadmin\process\queue\RedisQueueConsumer;
use support\think\Db;
use think\Container;
use Webman\ThinkOrm\DbManager;

/**
 * 队列消费者进程配置生成服务
 */
class QueueProcessConfigService
{
    private static bool $thinkOrmInitialized = false;

    public static function redis(): array
    {
        return self::build('redis', RedisQueueConsumer::class);
    }

    public static function rabbitmq(): array
    {
        if (!self::canStartRabbitmqProcesses()) {
            return [];
        }
        return self::build('rabbitmq', RabbitmqQueueConsumer::class);
    }

    private static function build(string $driver, string $handler): array
    {
        try {
            self::initializeThinkOrm();
            $rows = Db::table('sa_tool_queue_config')
                ->where('driver', $driver)
                ->where('status', 1)
                ->whereNull('delete_time')
                ->select()
                ->toArray();
        } catch (\Throwable) {
            return [];
        }

        $processes = [];
        foreach ($rows as $row) {
            if (($row['message_mode'] ?? 'internal_job') !== 'internal_job') {
                continue;
            }
            $id = (int) $row['id'];
            $name = preg_replace('/[^a-zA-Z0-9_]/', '_', (string) $row['queue_name']);
            $processes["saiadmin_{$driver}_queue_{$id}_{$name}"] = [
                'handler' => $handler,
                'count' => max(1, (int) ($row['consumer_count'] ?? 1)),
                'constructor' => [
                    'configId' => $id,
                ],
            ];
        }

        return $processes;
    }

    private static function initializeThinkOrm(): void
    {
        if (self::$thinkOrmInitialized) {
            return;
        }
        self::$thinkOrmInitialized = true;

        $config = config('think-orm', []);
        if (!$config) {
            $configFile = config_path('think-orm.php');
            if (is_file($configFile)) {
                $config = include $configFile;
            }
        }
        if (!$config) {
            return;
        }

        Container::getInstance()->bind('think\DbManager', DbManager::class);
        Db::setConfig($config);
    }

    private static function canStartRabbitmqProcesses(): bool
    {
        $eventLoop = ltrim(self::configuredEventLoop(), '\\');
        return in_array($eventLoop, [
            'Workerman\\Events\\Fiber',
            'Workerman\\Events\\Swow',
            'Workerman\\Events\\Swoole',
        ], true);
    }

    private static function configuredEventLoop(): string
    {
        $eventLoop = function_exists('env') ? (string) env('WEBMAN_EVENT_LOOP', '') : '';
        if ($eventLoop !== '') {
            return $eventLoop;
        }

        $configFile = config_path('server.php');
        if (!is_file($configFile)) {
            return '';
        }

        $config = include $configFile;
        return (string) ($config['event_loop'] ?? '');
    }
}
