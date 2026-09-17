<?php

namespace plugin\b8cms\app\admin\logic;

use plugin\b8cms\app\model\Carousel;
use plugin\saiadmin\basic\think\BaseLogic;

class CarouselLogic extends BaseLogic
{
    /**
     * 域策略：业务数据按 created_by 做数据范围隔离（非 tenant 多租户）
     */
    protected bool $scope = true;
public function __construct()
    {
        $this->model = new Carousel();
        $this->orderField = 'sort';
        $this->orderType = 'ASC';
    }
}
