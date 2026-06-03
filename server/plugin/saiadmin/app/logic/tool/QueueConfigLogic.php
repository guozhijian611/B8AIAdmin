<?php
// +----------------------------------------------------------------------
// | saiadmin [ saiadmin快速开发框架 ]
// +----------------------------------------------------------------------
// | Author: sai <1430792918@qq.com>
// +----------------------------------------------------------------------
namespace plugin\saiadmin\app\logic\tool;

use plugin\saiadmin\app\model\tool\QueueConfig;
use plugin\saiadmin\basic\think\BaseLogic;
use plugin\saiadmin\exception\ApiException;

/**
 * 队列配置逻辑层
 */
class QueueConfigLogic extends BaseLogic
{
    public function __construct()
    {
        $this->model = new QueueConfig();
        $this->orderField = 'sort';
        $this->orderType = 'DESC';
    }

    public function add(array $data): mixed
    {
        $data = $this->normalizeData($data);
        $this->assertQueueNameAvailable($data);
        return parent::add($data);
    }

    public function edit($id, array $data): mixed
    {
        $data = $this->normalizeData($data);
        $this->assertQueueNameAvailable($data, (int) $id);
        return parent::edit($id, $data);
    }

    public function changeStatus(int $id, int $status): bool
    {
        $model = $this->model->findOrEmpty($id);
        if ($model->isEmpty()) {
            throw new ApiException('队列配置不存在');
        }
        if ($status === 1) {
            $data = $model->toArray();
            $data['status'] = 1;
            $this->assertQueueNameAvailable($data, $id);
        }
        return (bool) $model->save(['status' => $status]);
    }

    public function options(string $messageMode = ''): array
    {
        $query = $this->model->where('status', 1);
        if ($messageMode !== '') {
            $query->where('message_mode', $messageMode);
        }
        return $query->order('sort', 'desc')->select()->toArray();
    }

    private function normalizeData(array $data): array
    {
        $data['driver'] = $data['driver'] ?? 'redis';
        $data['message_mode'] = $this->normalizeMessageMode((string) ($data['message_mode'] ?? 'internal_job'));
        $data['connection'] = $data['connection'] ?? 'default';
        $data['queue_name'] = trim((string) ($data['queue_name'] ?? ''));
        $data['exchange_type'] = $this->normalizeExchangeType((string) ($data['exchange_type'] ?? 'direct'));
        $data['exchange_name'] = $data['driver'] === 'rabbitmq'
            ? trim((string) ($data['exchange_name'] ?? $data['queue_name']))
            : '';
        $data['routing_key'] = $data['driver'] === 'rabbitmq'
            ? trim((string) ($data['routing_key'] ?? $data['queue_name']))
            : '';
        $data['is_delayed'] = (int) ($data['is_delayed'] ?? 2);
        $data['delay_mode'] = $data['is_delayed'] === 1 ? ($data['delay_mode'] ?? 'x_delay') : ($data['delay_mode'] ?? 'none');
        $data['prefetch_count'] = max(0, (int) ($data['prefetch_count'] ?? 0));
        $data['consumer_count'] = $data['message_mode'] === 'internal_job'
            ? max(1, (int) ($data['consumer_count'] ?? 1))
            : 0;
        $data['max_attempts'] = max(1, (int) ($data['max_attempts'] ?? 3));
        $data['retry_delay_seconds'] = max(0, (int) ($data['retry_delay_seconds'] ?? 5));
        $data['sort'] = (int) ($data['sort'] ?? 100);
        $data['status'] = (int) ($data['status'] ?? 1);

        if ($data['driver'] === 'redis') {
            $data['exchange_type'] = 'direct';
            $data['is_delayed'] = 2;
            $data['delay_mode'] = 'none';
            $data['dead_letter_exchange'] = '';
            $data['dead_letter_routing_key'] = '';
            $data['prefetch_count'] = 0;
        }

        if (isset($data['arguments']) && is_string($data['arguments']) && $data['arguments'] !== '') {
            json_decode($data['arguments'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new ApiException('扩展参数必须是合法 JSON');
            }
        }

        return $data;
    }

    private function normalizeExchangeType(string $exchangeType): string
    {
        return match ($exchangeType) {
            'fanout' => 'fanout',
            'topic' => 'topic',
            'header', 'headers' => 'header',
            default => 'direct',
        };
    }

    private function normalizeMessageMode(string $messageMode): string
    {
        return $messageMode === 'external_message' ? 'external_message' : 'internal_job';
    }

    private function assertQueueNameAvailable(array $data, int $excludeId = 0): void
    {
        if ((int) ($data['status'] ?? 1) !== 1) {
            return;
        }

        $driver = (string) ($data['driver'] ?? 'redis');
        $connection = (string) ($data['connection'] ?? 'default');
        $queueName = trim((string) ($data['queue_name'] ?? ''));
        if ($queueName === '') {
            return;
        }

        $query = $this->model
            ->where('driver', $driver)
            ->where('connection', $connection)
            ->where('queue_name', $queueName)
            ->where('status', 1)
            ->whereNull('delete_time');

        if ($excludeId > 0) {
            $query->where('id', '<>', $excludeId);
        }

        $exists = $query->findOrEmpty();
        if (!$exists->isEmpty()) {
            throw new ApiException(sprintf(
                '同一连接下已存在启用队列配置：%s。内部任务和外部消息不能共用同一个队列名。',
                $queueName
            ));
        }
    }
}
