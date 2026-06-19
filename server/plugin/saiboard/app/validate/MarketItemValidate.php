<?php

namespace plugin\saiboard\app\validate;

use plugin\saiadmin\basic\BaseValidate;

class MarketItemValidate extends BaseValidate
{
    protected $rule = [
        'type' => 'require|in:screen,component',
        'name' => 'require|max:80',
        'category' => 'max:60',
        'description' => 'max:255',
        'cover_image' => 'max:255',
        'is_public' => 'require|in:1,2',
        'status' => 'require|in:1,2',
    ];

    protected $message = [
        'type.require' => '模板类型必须填写',
        'type.in' => '模板类型不正确',
        'name.require' => '模板名称必须填写',
        'name.max' => '模板名称最多80个字符',
        'category.max' => '分类最多60个字符',
        'description.max' => '说明最多255个字符',
        'cover_image.max' => '封面图最多255个字符',
        'is_public.require' => '公开状态必须填写',
        'is_public.in' => '公开状态不正确',
        'status.require' => '状态必须填写',
        'status.in' => '状态值不正确',
    ];

    protected $scene = [
        'save' => ['type', 'name', 'category', 'description', 'cover_image', 'is_public', 'status'],
        'update' => ['type', 'name', 'category', 'description', 'cover_image', 'is_public', 'status'],
    ];
}
