<?php

namespace plugin\saiboard\app\validate;

use plugin\saiadmin\basic\BaseValidate;

class DatasourceValidate extends BaseValidate
{
    protected $rule = [
        'name' => 'require|max:60',
        'type' => 'require|in:mysql,http',
        'status' => 'require|in:1,2',
    ];

    protected $message = [
        'name.require' => '数据源名称必须填写',
        'name.max' => '数据源名称最多60个字符',
        'type.require' => '数据源类型必须填写',
        'type.in' => '数据源类型不正确',
        'status.require' => '状态必须填写',
        'status.in' => '状态值不正确',
    ];

    protected $scene = [
        'save' => ['name', 'type', 'status'],
        'update' => ['name', 'type', 'status'],
        'test' => ['name', 'type', 'status'],
    ];
}
