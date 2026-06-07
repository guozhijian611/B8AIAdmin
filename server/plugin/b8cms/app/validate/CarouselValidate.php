<?php

namespace plugin\b8cms\app\validate;

use plugin\saiadmin\basic\BaseValidate;

class CarouselValidate extends BaseValidate
{
    protected $rule = [
        'lang_code' => 'require',
        'position' => 'require',
        'title' => 'require',
        'target' => 'require|in:_self,_blank',
        'status' => 'require|in:1,2',
    ];

    protected $message = [
        'lang_code.require' => '语言必须填写',
        'position.require' => '轮播位置必须填写',
        'title.require' => '标题必须填写',
        'target.require' => '打开方式必须填写',
        'target.in' => '打开方式不正确',
        'status.require' => '状态必须填写',
        'status.in' => '状态值不正确',
    ];

    protected $scene = [
        'save' => ['lang_code', 'position', 'title', 'target', 'status'],
        'update' => ['lang_code', 'position', 'title', 'target', 'status'],
    ];
}
