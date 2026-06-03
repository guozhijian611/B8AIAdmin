<?php
// +----------------------------------------------------------------------
// | saiadmin [ saiadmin快速开发框架 ]
// +----------------------------------------------------------------------
// | Author: sai <1430792918@qq.com>
// +----------------------------------------------------------------------
namespace plugin\saiadmin\process\queue;

use plugin\saiadmin\app\model\tool\QueueConfig;
use plugin\saiadmin\app\service\queue\QueueExecutorService;
use plugin\saiadmin\app\service\queue\QueueProcessConfigService;
use support\Context;
use Webman\RedisQueue\Client;

/**
 * Redis 队列消费者进程
 */
class RedisQueueConsumer
{
    public function __construct(protected int $configId)
    {
    }

    public function onWorkerStart(): void
    {
        QueueProcessConfigService::initializeOpenTelemetryContextStorage();

        $config = QueueConfig::findOrEmpty($this->configId);
        if ($config->isEmpty() || (int) $config->status !== 1 || $config->driver !== 'redis') {
            echo "Redis queue config {$this->configId} not available" . PHP_EOL;
            return;
        }

        try {
            Client::connection((string) $config->connection)->subscribe((string) $config->queue_name, function ($data) {
                try {
                    (new QueueExecutorService())->consume((int) ($data['id'] ?? 0));
                } finally {
                    Context::destroy();
                }
            });
            echo "Redis queue [{$config->queue_name}] consumer started" . PHP_EOL;
        } catch (\Throwable $e) {
            echo "Redis queue [{$config->queue_name}] consumer start failed: {$e->getMessage()}" . PHP_EOL;
        }
    }
}
