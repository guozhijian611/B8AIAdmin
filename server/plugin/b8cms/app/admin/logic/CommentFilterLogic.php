<?php

namespace plugin\b8cms\app\admin\logic;

use plugin\b8cms\app\model\CommentFilter;
use plugin\saiadmin\basic\think\BaseLogic;

class CommentFilterLogic extends BaseLogic
{
    public function __construct()
    {
        $this->model = new CommentFilter();
        $this->orderField = 'id';
        $this->orderType = 'DESC';
    }
}
