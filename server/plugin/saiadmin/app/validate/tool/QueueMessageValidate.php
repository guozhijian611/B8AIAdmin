<?php
// +----------------------------------------------------------------------
// | saiadmin [ saiadmin快速开发框架 ]
// +----------------------------------------------------------------------
// | Author: sai <1430792918@qq.com>
// +----------------------------------------------------------------------
namespace plugin\saiadmin\app\validate\tool;

use plugin\saiadmin\basic\BaseValidate;

/**
 * 队列外部消息验证器
 */
class QueueMessageValidate extends BaseValidate
{
    protected $rule = [
        'config_id' => 'require|number',
        'event_name' => 'require',
        'payload' => 'require',
    ];

    protected $message = [
        'config_id.require' => '队列配置必须选择',
        'config_id.number' => '队列配置不正确',
        'event_name.require' => '事件名称必须填写',
        'payload.require' => '消息载荷必须填写',
    ];

    protected $scene = [
        'publish' => [
            'config_id',
            'event_name',
            'payload',
        ],
    ];
}
