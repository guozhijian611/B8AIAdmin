<?php

namespace plugin\b8cms\app\admin\logic;

use plugin\b8cms\app\model\Comment;
use plugin\saiadmin\basic\think\BaseLogic;

class CommentLogic extends BaseLogic
{
    public function __construct()
    {
        $this->model = new Comment();
        $this->orderField = 'create_time';
        $this->orderType = 'DESC';
    }
}
