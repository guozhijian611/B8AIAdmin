<?php
// +----------------------------------------------------------------------
// | saiadmin [ saiadmin快速开发框架 ]
// +----------------------------------------------------------------------
// | Author: sai <1430792918@qq.com>
// +----------------------------------------------------------------------
namespace plugin\saiadmin\app\model\tool;

use plugin\saiadmin\basic\think\BaseModel;

/**
 * 队列配置模型
 *
 * sa_tool_queue_config 队列配置表
 */
class QueueConfig extends BaseModel
{
    protected $pk = 'id';

    protected $table = 'sa_tool_queue_config';

    public function getArgumentsAttr($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        return json_decode((string) $value, true) ?: [];
    }

    public function setArgumentsAttr($value): string
    {
        if (is_string($value)) {
            json_decode($value, true);
            return json_last_error() === JSON_ERROR_NONE ? $value : '{}';
        }
        return json_encode($value ?: [], JSON_UNESCAPED_UNICODE);
    }

    public function searchNameAttr($query, $value): void
    {
        $query->where('name', 'like', '%' . $value . '%');
    }

    public function searchDriverAttr($query, $value): void
    {
        $query->where('driver', $value);
    }

    public function searchConnectionAttr($query, $value): void
    {
        $query->where('connection', $value);
    }

    public function searchQueueNameAttr($query, $value): void
    {
        $query->where('queue_name', 'like', '%' . $value . '%');
    }

    public function searchStatusAttr($query, $value): void
    {
        $query->where('status', $value);
    }
}
