<?php
// +----------------------------------------------------------------------
// | saiadmin [ saiadmin快速开发框架 ]
// +----------------------------------------------------------------------
// | Author: sai <1430792918@qq.com>
// +----------------------------------------------------------------------
namespace plugin\saiadmin\app\logic\tool;

use plugin\saiadmin\app\model\tool\QueueTask;
use plugin\saiadmin\app\service\queue\QueuePublisherService;
use plugin\saiadmin\app\service\queue\QueueRuntimeService;
use plugin\saiadmin\basic\think\BaseLogic;
use plugin\saiadmin\exception\ApiException;

/**
 * 队列任务逻辑层
 */
class QueueTaskLogic extends BaseLogic
{
    public function __construct()
    {
        $this->model = new QueueTask();
        $this->orderField = 'id';
        $this->orderType = 'DESC';
    }

    public function retry(int $id): bool
    {
        return (new QueuePublisherService())->retry($id);
    }

    public function cancel(int $id): bool
    {
        $model = $this->model->findOrEmpty($id);
        if ($model->isEmpty()) {
            throw new ApiException('队列任务不存在');
        }
        if ((int) $model->status === 1) {
            throw new ApiException('消费中的任务不能取消');
        }
        if ((int) $model->status === 2) {
            throw new ApiException('已完成的任务不能取消');
        }
        return (bool) $model->save(['status' => 4]);
    }

    public function clearCompleted(?int $configId = null): int
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
        return (new QueueRuntimeService())->stats();
    }
}
