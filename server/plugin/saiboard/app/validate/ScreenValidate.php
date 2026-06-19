<?php

namespace plugin\saiboard\app\validate;

use plugin\saiadmin\basic\BaseValidate;

class ScreenValidate extends BaseValidate
{
    protected $rule = [
        'name' => 'require|max:60',
        'code' => 'alphaDash|max:32',
        'width' => 'require|integer',
        'height' => 'require|integer',
        'is_public' => 'require|in:1,2',
        'status' => 'require|in:1,2',
    ];

    protected $message = [
        'name.require' => '大屏名称必须填写',
        'name.max' => '大屏名称最多60个字符',
        'code.alphaDash' => '访问编码只能包含字母、数字、下划线和短横线',
        'code.max' => '访问编码最多32个字符',
        'width.require' => '设计宽度必须填写',
        'width.integer' => '设计宽度必须是整数',
        'height.require' => '设计高度必须填写',
        'height.integer' => '设计高度必须是整数',
        'is_public.require' => '访问方式必须填写',
        'is_public.in' => '访问方式不正确',
        'status.require' => '状态必须填写',
        'status.in' => '状态值不正确',
    ];

    protected $scene = [
        'save' => ['name', 'code', 'width', 'height', 'is_public'],
        'update' => ['name', 'code', 'width', 'height', 'is_public'],
    ];
}
