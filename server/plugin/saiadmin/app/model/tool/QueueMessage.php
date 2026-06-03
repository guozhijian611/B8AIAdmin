<?php
// +----------------------------------------------------------------------
// | saiadmin [ saiadmin快速开发框架 ]
// +----------------------------------------------------------------------
// | Author: sai <1430792918@qq.com>
// +----------------------------------------------------------------------
namespace plugin\saiadmin\app\model\tool;

use plugin\saiadmin\basic\think\BaseModel;

/**
 * 队列外部消息模型
 *
 * sa_tool_queue_message 队列外部消息表
 */
class QueueMessage extends BaseModel
{
    protected $pk = 'id';

    protected $table = 'sa_tool_queue_message';

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

    public function searchEventNameAttr($query, $value): void
    {
        $query->where('event_name', 'like', '%' . $value . '%');
    }

    public function searchMessageKeyAttr($query, $value): void
    {
        $query->where('message_key', 'like', '%' . $value . '%');
    }

    public function searchSourceAttr($query, $value): void
    {
        $query->where('source', 'like', '%' . $value . '%');
    }
}
