<?php

namespace plugin\b8cms\app\admin\logic;

use plugin\b8cms\app\model\Content;
use plugin\saiadmin\basic\think\BaseLogic;

class ContentLogic extends BaseLogic
{
    /**
     * 域策略：业务数据按 created_by 做数据范围隔离（非 tenant 多租户）
     */
    protected bool $scope = true;
public function __construct()
    {
        $this->model = new Content();
        $this->orderField = 'sort';
        $this->orderType = 'ASC';
    }
}
