<?php

namespace plugin\b8cms\app\validate;

use plugin\saiadmin\basic\BaseValidate;

class TemplateValidate extends BaseValidate
{
    protected $rule = [
        'template_key' => 'require',
        'name' => 'require',
        'status' => 'require|in:1,2',
    ];

    protected $message = [
        'template_key.require' => '模板标识必须填写',
        'name.require' => '模板名称必须填写',
        'status.require' => '状态必须填写',
        'status.in' => '状态值不正确',
    ];

    protected $scene = [
        'save' => ['template_key', 'name', 'status'],
        'update' => ['template_key', 'name', 'status'],
    ];
}
