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

/**
 * 队列消费者进程配置生成服务
 */
class QueueProcessConfigService
{
    public static function redis(): array
    {
        return self::build('redis', RedisQueueConsumer::class);
    }

    public static function rabbitmq(): array
    {
        return self::build('rabbitmq', RabbitmqQueueConsumer::class);
    }

    private static function build(string $driver, string $handler): array
    {
        try {
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
}
