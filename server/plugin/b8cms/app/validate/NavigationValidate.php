<?php

namespace plugin\b8cms\app\validate;

use plugin\saiadmin\basic\BaseValidate;

class NavigationValidate extends BaseValidate
{
    protected $rule = [
        'lang_code' => 'require',
        'position' => 'require|in:header,footer',
        'title' => 'require',
        'url' => 'require',
        'status' => 'require|in:1,2',
    ];

    protected $message = [
        'lang_code.require' => '语言必须填写',
        'position.require' => '导航位置必须填写',
        'position.in' => '导航位置不正确',
        'title.require' => '导航标题必须填写',
        'url.require' => '链接地址必须填写',
        'status.require' => '状态必须填写',
        'status.in' => '状态值不正确',
    ];

    protected $scene = [
        'save' => ['lang_code', 'position', 'title', 'url', 'status'],
        'update' => ['lang_code', 'position', 'title', 'url', 'status'],
    ];
}
