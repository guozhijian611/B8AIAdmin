<?php

namespace plugin\b8cms\app\validate;

use plugin\saiadmin\basic\BaseValidate;

class SiteSettingValidate extends BaseValidate
{
    protected $rule = [
        'setting_key' => 'require',
        'group_key' => 'require',
        'title' => 'require',
        'status' => 'require|in:1,2',
    ];

    protected $message = [
        'setting_key.require' => '配置标识必须填写',
        'group_key.require' => '配置分组必须填写',
        'title.require' => '配置标题必须填写',
        'status.require' => '状态必须填写',
        'status.in' => '状态值不正确',
    ];

    protected $scene = [
        'save' => ['setting_key', 'group_key', 'title', 'status'],
        'update' => ['setting_key', 'group_key', 'title', 'status'],
    ];
}
