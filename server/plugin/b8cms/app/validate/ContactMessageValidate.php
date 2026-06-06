<?php

namespace plugin\b8cms\app\validate;

use plugin\saiadmin\basic\BaseValidate;

class ContactMessageValidate extends BaseValidate
{
    protected $rule = [
        'name' => 'require',
        'email' => 'require',
        'message' => 'require',
    ];

    protected $message = [
        'name.require' => '姓名必须填写',
        'email.require' => '邮箱必须填写',
        'message.require' => '留言内容必须填写',
    ];

    protected $scene = [
        'save' => ['name', 'email', 'message'],
        'update' => ['name', 'email', 'message'],
    ];
}
