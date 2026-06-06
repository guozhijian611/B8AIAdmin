<?php

namespace plugin\b8cms\app\validate;

use plugin\saiadmin\basic\BaseValidate;

class ContentValidate extends BaseValidate
{
    protected $rule = [
        'content_type' => 'require|in:article,product,page',
        'lang_code' => 'require',
        'slug' => 'require',
        'title' => 'require',
        'status' => 'require|in:1,2',
    ];

    protected $message = [
        'content_type.require' => '内容类型必须填写',
        'content_type.in' => '内容类型不正确',
        'lang_code.require' => '语言必须填写',
        'slug.require' => '访问别名必须填写',
        'title.require' => '标题必须填写',
        'status.require' => '状态必须填写',
        'status.in' => '状态值不正确',
    ];

    protected $scene = [
        'save' => ['content_type', 'lang_code', 'slug', 'title', 'status'],
        'update' => ['content_type', 'lang_code', 'slug', 'title', 'status'],
    ];
}
