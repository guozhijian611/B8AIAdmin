<?php

namespace plugin\b8cms\app\validate;

use plugin\saiadmin\basic\BaseValidate;

class CommentValidate extends BaseValidate
{
    protected $rule = [
        'id' => 'require',
        'nickname' => 'require|max:80',
        'email' => 'require|email|max:160',
        'comment' => 'require',
        'status' => 'require|in:1,2,3',
    ];

    protected $message = [
        'id.require' => '评论ID必须填写',
        'nickname.require' => '昵称必须填写',
        'nickname.max' => '昵称最多80个字符',
        'email.require' => '邮箱必须填写',
        'email.email' => '邮箱格式不正确',
        'email.max' => '邮箱最多160个字符',
        'comment.require' => '评论内容必须填写',
        'status.require' => '状态必须填写',
        'status.in' => '状态不正确',
    ];

    protected $scene = [
        'update' => ['id', 'nickname', 'email', 'comment', 'status'],
        'handle' => ['id', 'status'],
    ];
}
