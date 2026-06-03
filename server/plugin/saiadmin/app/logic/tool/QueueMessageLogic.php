<?php
// +----------------------------------------------------------------------
// | saiadmin [ saiadmin快速开发框架 ]
// +----------------------------------------------------------------------
// | Author: sai <1430792918@qq.com>
// +----------------------------------------------------------------------
namespace plugin\saiadmin\app\logic\tool;

use plugin\saiadmin\app\model\tool\QueueMessage;
use plugin\saiadmin\app\service\queue\QueueMessagePublisherService;
use plugin\saiadmin\basic\think\BaseLogic;
use plugin\saiadmin\exception\ApiException;

/**
 * 队列外部消息逻辑层
 */
class QueueMessageLogic extends BaseLogic
{
    public function __construct()
    {
        $this->model = new QueueMessage();
        $this->orderField = 'id';
        $this->orderType = 'DESC';
    }

    public function publish(array $data): int
    {
        return (new QueueMessagePublisherService())->publish(
            (int) $data['config_id'],
            trim((string) $data['event_name']),
            $this->decodeJson((string) ($data['payload'] ?? '{}'), '消息载荷必须是合法 JSON 对象'),
            $this->decodeJson((string) ($data['headers'] ?? '{}'), '消息头必须是合法 JSON 对象'),
            (int) ($data['delay'] ?? 0),
            trim((string) ($data['message_key'] ?? '')),
            trim((string) ($data['source'] ?? 'saiadmin')),
            trim((string) ($data['content_type'] ?? 'application/json'))
        );
    }

    public function retry(int $id): bool
    {
        return (new QueueMessagePublisherService())->retry($id);
    }

    public function cancel(int $id): bool
    {
        $model = $this->model->findOrEmpty($id);
        if ($model->isEmpty()) {
            throw new ApiException('队列消息不存在');
        }
        if ((int) $model->status === 1) {
            throw new ApiException('发布中的消息不能取消');
        }
        if ((int) $model->status === 2) {
            throw new ApiException('已发布的消息不能取消');
        }
        return (bool) $model->save(['status' => 4]);
    }

    public function clearPublished(?int $configId = null): int
    {
        $query = $this->model->where('status', 2);
        if ($configId) {
            $query->where('config_id', $configId);
        }
        $ids = $query->column('id');
        if (empty($ids)) {
            return 0;
        }
        return (int) $this->model->destroy($ids);
    }

    public function stats(): array
    {
        $statusCounts = $this->model->field('status, count(*) as total')
            ->group('status')
            ->select()
            ->toArray();
        $statusMap = [];
        foreach ($statusCounts as $item) {
            $statusMap[(int) $item['status']] = (int) $item['total'];
        }

        return [
            'pending' => $statusMap[0] ?? 0,
            'publishing' => $statusMap[1] ?? 0,
            'published' => $statusMap[2] ?? 0,
            'failed' => $statusMap[3] ?? 0,
            'cancelled' => $statusMap[4] ?? 0,
        ];
    }

    private function decodeJson(string $json, string $message): array
    {
        $data = json_decode($json ?: '{}', true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            throw new ApiException($message);
        }
        return $data;
    }
}
