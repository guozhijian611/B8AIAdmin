<?php
// +----------------------------------------------------------------------
// | saiadmin [ saiadmin快速开发框架 ]
// +----------------------------------------------------------------------
// | Author: sai <1430792918@qq.com>
// +----------------------------------------------------------------------
namespace plugin\saiadmin\app\service\queue;

use plugin\saiadmin\app\model\tool\QueueConfig;
use plugin\saiadmin\app\model\tool\QueueMessage;
use plugin\saiadmin\app\model\tool\QueueTask;
use plugin\saiadmin\exception\ApiException;
use Webman\RedisQueue\Redis;

/**
 * 队列运行状态服务
 */
class QueueRuntimeService
{
    private const REDIS_WAITING_KEY = '{redis-queue}-waiting';
    private const REDIS_DELAYED_KEY = '{redis-queue}-delayed';

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
            if (($config['message_mode'] ?? 'internal_job') !== 'internal_job') {
                continue;
            }
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

    public function runtimeList(array $where): array
    {
        $query = QueueConfig::withSearch(array_keys(array_filter($where, static function ($value) {
            return $value !== '' && $value !== null && $value !== [];
        })), $where);

        $request = request();
        $page = $request ? (int) $request->input('page', 1) : 1;
        $limit = $request ? (int) $request->input('limit', 10) : 10;
        $orderField = $request ? (string) $request->input('orderField', 'sort') : 'sort';
        $orderType = $request ? (string) $request->input('orderType', 'DESC') : 'DESC';

        $pageData = $query
            ->whereNull('delete_time')
            ->order($orderField ?: 'sort', $orderType ?: 'DESC')
            ->paginate($limit, false, ['page' => $page])
            ->toArray();

        $pageData['data'] = array_map(function (array $config) {
            return $this->appendRuntimeStats($config);
        }, $pageData['data'] ?? []);

        return $pageData;
    }

    public function purge(int $configId): array
    {
        $config = QueueConfig::whereNull('delete_time')->findOrEmpty($configId);
        if ($config->isEmpty()) {
            throw new ApiException('队列配置不存在');
        }

        $data = $config->toArray();
        if ((string) ($data['driver'] ?? '') === 'redis') {
            return $this->purgeRedis($data);
        }
        if ((string) ($data['driver'] ?? '') === 'rabbitmq') {
            return $this->purgeRabbitmq($data);
        }

        throw new ApiException('不支持的队列驱动：' . ($data['driver'] ?? ''));
    }

    private function appendRuntimeStats(array $config): array
    {
        $messageMode = (string) ($config['message_mode'] ?? 'internal_job');
        $dbStats = $messageMode === 'external_message'
            ? $this->messageRecordStats((int) $config['id'])
            : $this->taskRecordStats((int) $config['id']);
        $broker = $this->brokerStats($config);

        return array_merge($config, [
            'db_pending' => $dbStats['pending'],
            'db_processing' => $dbStats['processing'],
            'db_completed' => $dbStats['completed'],
            'db_failed' => $dbStats['failed'],
            'db_cancelled' => $dbStats['cancelled'],
            'broker' => $broker,
            'broker_ready' => (int) ($broker['ready'] ?? 0),
            'broker_delayed' => (int) ($broker['delayed'] ?? 0),
            'broker_unacked' => (int) ($broker['unacked'] ?? 0),
            'broker_consumers' => (int) ($broker['consumers'] ?? 0),
            'broker_status' => empty($broker['error']) ? 'ok' : 'error',
            'broker_error' => $broker['error'] ?? '',
        ]);
    }

    private function taskRecordStats(int $configId): array
    {
        return [
            'pending' => QueueTask::where('config_id', $configId)->where('status', 0)->count(),
            'processing' => QueueTask::where('config_id', $configId)->where('status', 1)->count(),
            'completed' => QueueTask::where('config_id', $configId)->where('status', 2)->count(),
            'failed' => QueueTask::where('config_id', $configId)->where('status', 3)->count(),
            'cancelled' => QueueTask::where('config_id', $configId)->where('status', 4)->count(),
        ];
    }

    private function messageRecordStats(int $configId): array
    {
        return [
            'pending' => QueueMessage::where('config_id', $configId)->where('status', 0)->count(),
            'processing' => QueueMessage::where('config_id', $configId)->where('status', 1)->count(),
            'completed' => QueueMessage::where('config_id', $configId)->where('status', 2)->count(),
            'failed' => QueueMessage::where('config_id', $configId)->where('status', 3)->count(),
            'cancelled' => QueueMessage::where('config_id', $configId)->where('status', 4)->count(),
        ];
    }

    private function brokerStats(array $config): array
    {
        return match ((string) ($config['driver'] ?? '')) {
            'redis' => $this->redisBrokerStats($config),
            'rabbitmq' => $this->rabbitmqBrokerStats($config),
            default => ['support' => false, 'error' => '不支持的队列驱动'],
        };
    }

    private function redisBrokerStats(array $config): array
    {
        try {
            $redis = Redis::connection((string) $config['connection']);
            $queueName = (string) $config['queue_name'];
            return [
                'support' => true,
                'ready' => (int) $redis->lLen(self::REDIS_WAITING_KEY . $queueName),
                'delayed' => $this->countRedisDelayed($redis, $queueName),
                'delayed_total' => (int) $redis->zCard(self::REDIS_DELAYED_KEY),
                'unacked' => 0,
                'consumers' => 0,
            ];
        } catch (\Throwable $e) {
            return [
                'support' => true,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function countRedisDelayed(\Redis $redis, string $queueName): int
    {
        $count = 0;
        $items = $redis->zRange(self::REDIS_DELAYED_KEY, 0, -1) ?: [];
        foreach ($items as $item) {
            $data = json_decode((string) $item, true);
            if (($data['queue'] ?? '') === $queueName) {
                $count++;
            }
        }
        return $count;
    }

    private function purgeRedis(array $config): array
    {
        try {
            $redis = Redis::connection((string) $config['connection']);
            $queueName = (string) $config['queue_name'];
            $waitingKey = self::REDIS_WAITING_KEY . $queueName;
            $waiting = (int) $redis->lLen($waitingKey);
            if ($waiting > 0) {
                $redis->del($waitingKey);
            }

            $delayed = 0;
            $items = $redis->zRange(self::REDIS_DELAYED_KEY, 0, -1) ?: [];
            foreach ($items as $item) {
                $data = json_decode((string) $item, true);
                if (($data['queue'] ?? '') === $queueName) {
                    $redis->zRem(self::REDIS_DELAYED_KEY, (string) $item);
                    $delayed++;
                }
            }

            return [
                'driver' => 'redis',
                'queue_name' => $queueName,
                'purged' => $waiting + $delayed,
                'waiting' => $waiting,
                'delayed' => $delayed,
            ];
        } catch (\Throwable $e) {
            throw new ApiException('Redis 队列清空失败：' . $e->getMessage());
        }
    }

    private function rabbitmqBrokerStats(array $config): array
    {
        try {
            $queue = $this->rabbitmqRequest($config, 'GET');
            if (($queue['status'] ?? 0) === 404) {
                return [
                    'support' => true,
                    'exists' => false,
                    'ready' => 0,
                    'delayed' => 0,
                    'unacked' => 0,
                    'consumers' => 0,
                    'error' => 'RabbitMQ 队列不存在或 Management API 无权访问',
                ];
            }
            $data = $queue['data'] ?? [];
            return [
                'support' => true,
                'exists' => true,
                'ready' => (int) ($data['messages_ready'] ?? 0),
                'delayed' => 0,
                'unacked' => (int) ($data['messages_unacknowledged'] ?? 0),
                'consumers' => (int) ($data['consumers'] ?? 0),
                'state' => $data['state'] ?? '',
            ];
        } catch (\Throwable $e) {
            return [
                'support' => true,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function purgeRabbitmq(array $config): array
    {
        try {
            $result = $this->rabbitmqRequest($config, 'DELETE', '/contents');
            if (!in_array((int) ($result['status'] ?? 0), [204, 200], true)) {
                throw new ApiException('RabbitMQ Management API 返回异常状态：' . ($result['status'] ?? 0));
            }
            return [
                'driver' => 'rabbitmq',
                'queue_name' => (string) $config['queue_name'],
                'purged' => null,
            ];
        } catch (ApiException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new ApiException('RabbitMQ 队列清空失败：' . $e->getMessage());
        }
    }

    private function rabbitmqRequest(array $config, string $method, string $suffix = ''): array
    {
        $management = $this->rabbitmqManagementConfig((string) ($config['connection'] ?? 'default'));
        $vhost = rawurlencode((string) $management['vhost']);
        $queue = rawurlencode((string) $config['queue_name']);
        $url = sprintf(
            '%s://%s:%d/api/queues/%s/%s%s',
            $management['scheme'],
            $management['host'],
            $management['port'],
            $vhost,
            $queue,
            $suffix
        );

        $ch = curl_init($url);
        if ($ch === false) {
            throw new ApiException('无法初始化 RabbitMQ Management 请求');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_USERPWD => $management['username'] . ':' . $management['password'],
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_CONNECTTIMEOUT => $management['timeout'],
            CURLOPT_TIMEOUT => $management['timeout'],
        ]);

        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false) {
            throw new ApiException('RabbitMQ Management API 请求失败：' . $error);
        }
        if ($status >= 400 && $status !== 404) {
            $message = $this->decodeRabbitmqError((string) $body);
            throw new ApiException(sprintf('RabbitMQ Management API 请求失败（%d）：%s', $status, $message));
        }

        return [
            'status' => $status,
            'data' => $body === '' ? [] : (json_decode((string) $body, true) ?: []),
        ];
    }

    private function rabbitmqManagementConfig(string $connection): array
    {
        $connectionConfig = (array) config('plugin.workbunny.webman-rabbitmq.connections.' . $connection . '.config', []);

        return [
            'scheme' => (string) env('RABBITMQ_MANAGEMENT_SCHEME', 'http'),
            'host' => (string) env('RABBITMQ_MANAGEMENT_HOST', $connectionConfig['host'] ?? env('RABBITMQ_HOST', '127.0.0.1')),
            'port' => (int) env('RABBITMQ_MANAGEMENT_PORT', 15672),
            'vhost' => (string) ($connectionConfig['vhost'] ?? env('RABBITMQ_VHOST', '/')),
            'username' => (string) env('RABBITMQ_MANAGEMENT_USERNAME', $connectionConfig['username'] ?? env('RABBITMQ_USERNAME', 'guest')),
            'password' => (string) env('RABBITMQ_MANAGEMENT_PASSWORD', $connectionConfig['password'] ?? env('RABBITMQ_PASSWORD', 'guest')),
            'timeout' => (int) env('RABBITMQ_MANAGEMENT_TIMEOUT', 3),
        ];
    }

    private function decodeRabbitmqError(string $body): string
    {
        $data = json_decode($body, true);
        if (is_array($data)) {
            return (string) ($data['reason'] ?? $data['error'] ?? $body);
        }
        return $body;
    }
}
