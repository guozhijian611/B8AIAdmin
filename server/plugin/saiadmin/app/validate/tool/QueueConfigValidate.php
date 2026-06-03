<?php
// +----------------------------------------------------------------------
// | saiadmin [ saiadmin快速开发框架 ]
// +----------------------------------------------------------------------
// | Author: sai <1430792918@qq.com>
// +----------------------------------------------------------------------
namespace plugin\saiadmin\app\validate\tool;

use plugin\saiadmin\basic\BaseValidate;

/**
 * 队列配置验证器
 */
class QueueConfigValidate extends BaseValidate
{
    protected $rule = [
        'name' => 'require',
        'driver' => 'require|in:redis,rabbitmq',
        'connection' => 'require',
        'queue_name' => 'require',
        'exchange_type' => 'in:direct,fanout,topic,header,headers',
        'is_delayed' => 'in:1,2',
        'status' => 'require|in:1,2',
    ];

    protected $message = [
        'name.require' => '配置名称必须填写',
        'driver.require' => '队列驱动必须填写',
        'driver.in' => '队列驱动仅支持 redis 或 rabbitmq',
        'connection.require' => '连接名必须填写',
        'queue_name.require' => '队列名称必须填写',
        'exchange_type.in' => '交换机类型不正确',
        'is_delayed.in' => '延迟队列配置不正确',
        'status.require' => '状态必须填写',
        'status.in' => '状态不正确',
    ];

    protected $scene = [
        'save' => [
            'name',
            'driver',
            'connection',
            'queue_name',
            'exchange_type',
            'is_delayed',
            'status',
        ],
        'update' => [
            'name',
            'driver',
            'connection',
            'queue_name',
            'exchange_type',
            'is_delayed',
            'status',
        ],
    ];
}
