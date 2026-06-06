<?php

namespace plugin\b8cms\app\validate;

use plugin\saiadmin\basic\BaseValidate;

class CommentFilterValidate extends BaseValidate
{
    protected $rule = [
        'rule_type' => 'require|in:word,email',
        'match_type' => 'require|in:contains,exact,domain,regex',
        'value' => 'require|max:255',
        'status' => 'require|in:1,2',
    ];

    protected $message = [
        'rule_type.require' => '规则类型必须填写',
        'rule_type.in' => '规则类型不正确',
        'match_type.require' => '匹配方式必须填写',
        'match_type.in' => '匹配方式不正确',
        'value.require' => '规则值必须填写',
        'value.max' => '规则值最多255个字符',
        'status.require' => '状态必须填写',
        'status.in' => '状态不正确',
    ];

    protected $scene = [
        'save' => ['rule_type', 'match_type', 'value', 'status'],
        'update' => ['rule_type', 'match_type', 'value', 'status'],
    ];
}
