<?php
// +----------------------------------------------------------------------
// | saiadmin [ saiadmin快速开发框架 ]
// +----------------------------------------------------------------------
// | Author: sai <1430792918@qq.com>
// +----------------------------------------------------------------------
namespace plugin\saiadmin\app\model\tool;

use plugin\saiadmin\basic\think\BaseModel;

/**
 * 队列任务模型
 *
 * sa_tool_queue 队列任务表
 */
class QueueTask extends BaseModel
{
    protected $pk = 'id';

    protected $table = 'sa_tool_queue';

    public function searchConfigIdAttr($query, $value): void
    {
        $query->where('config_id', $value);
    }

    public function searchDriverAttr($query, $value): void
    {
        $query->where('driver', $value);
    }

    public function searchConnectionsAttr($query, $value): void
    {
        $query->where('connections', $value);
    }

    public function searchNameAttr($query, $value): void
    {
        $query->where('name', 'like', '%' . $value . '%');
    }

    public function searchStatusAttr($query, $value): void
    {
        $query->where('status', $value);
    }

    public function searchClassNameAttr($query, $value): void
    {
        $query->where('class_name', 'like', '%' . $value . '%');
    }

    public function searchMethodNameAttr($query, $value): void
    {
        $query->where('method_name', 'like', '%' . $value . '%');
    }

    public function searchSourceAttr($query, $value): void
    {
        $query->where('source', 'like', '%' . $value . '%');
    }
}
