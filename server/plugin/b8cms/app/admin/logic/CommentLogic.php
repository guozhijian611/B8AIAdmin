<?php

namespace plugin\b8cms\app\admin\logic;

use plugin\b8cms\app\model\Comment;
use plugin\saiadmin\basic\think\BaseLogic;

class CommentLogic extends BaseLogic
{
    /**
     * 域策略：业务数据按 created_by 做数据范围隔离（非 tenant 多租户）
     */
    protected bool $scope = true;
public function __construct()
    {
        $this->model = new Comment();
        $this->orderField = 'create_time';
        $this->orderType = 'DESC';
    }
}
