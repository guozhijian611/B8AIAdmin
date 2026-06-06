<?php

namespace plugin\b8cms\app\validate;

use plugin\saiadmin\basic\BaseValidate;

class LanguageValidate extends BaseValidate
{
    protected $rule = [
        'code' => 'require',
        'name' => 'require',
        'native_name' => 'require',
        'status' => 'require|in:1,2',
    ];

    protected $message = [
        'code.require' => '语言标识必须填写',
        'name.require' => '语言名称必须填写',
        'native_name.require' => '本地化名称必须填写',
        'status.require' => '状态必须填写',
        'status.in' => '状态值不正确',
    ];

    protected $scene = [
        'save' => ['code', 'name', 'native_name', 'status'],
        'update' => ['code', 'name', 'native_name', 'status'],
    ];
}
