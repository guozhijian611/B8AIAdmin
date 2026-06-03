<?php
// +----------------------------------------------------------------------
// | saiadmin [ saiadmin快速开发框架 ]
// +----------------------------------------------------------------------
// | Author: sai <1430792918@qq.com>
// +----------------------------------------------------------------------
namespace plugin\saiadmin\app\service\queue;

use plugin\saiadmin\app\model\tool\QueueConfig;
use plugin\saiadmin\app\model\tool\QueueTask;
use Webman\RedisQueue\Redis;

/**
 * 队列运行状态服务
 */
class QueueRuntimeService
{
    public function stats(): array
    {
        $statusCounts = QueueTask::field('status, count(*) as total')
            ->group('status')
            ->select()
            ->toArray();
        $statusMap = [];
        foreach ($statusCounts as $item) {
            $statusMap[(int) $item['status']] = (int) $item['total'];
        }

        $configs = QueueConfig::whereNull('delete_time')->order('sort', 'desc')->select()->toArray();
        $queueStats = [];
        foreach ($configs as $config) {
            $queueStats[] = [
                'id' => $config['id'],
                'name' => $config['name'],
                'driver' => $config['driver'],
                'connection' => $config['connection'],
                'queue_name' => $config['queue_name'],
                'status' => $config['status'],
                'pending' => QueueTask::where('config_id', $config['id'])->where('status', 0)->count(),
                'processing' => QueueTask::where('config_id', $config['id'])->where('status', 1)->count(),
                'completed' => QueueTask::where('config_id', $config['id'])->where('status', 2)->count(),
                'failed' => QueueTask::where('config_id', $config['id'])->where('status', 3)->count(),
                'broker' => $config['driver'] === 'redis' ? $this->redisBrokerStats($config) : ['support' => false],
            ];
        }

        return [
            'status' => [
                'pending' => $statusMap[0] ?? 0,
                'processing' => $statusMap[1] ?? 0,
                'completed' => $statusMap[2] ?? 0,
                'failed' => $statusMap[3] ?? 0,
                'cancelled' => $statusMap[4] ?? 0,
            ],
            'queues' => $queueStats,
        ];
    }

    private function redisBrokerStats(array $config): array
    {
        try {
            $redis = Redis::connection((string) $config['connection']);
            return [
                'support' => true,
                'waiting' => (int) $redis->lLen('{redis-queue}-waiting' . $config['queue_name']),
                'delayed_total' => (int) $redis->zCard('{redis-queue}-delayed'),
            ];
        } catch (\Throwable $e) {
            return [
                'support' => true,
                'error' => $e->getMessage(),
            ];
        }
    }
}
