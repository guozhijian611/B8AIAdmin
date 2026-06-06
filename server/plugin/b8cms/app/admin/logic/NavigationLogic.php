<?php

namespace plugin\b8cms\app\admin\logic;

use plugin\b8cms\app\model\Navigation;
use plugin\saiadmin\basic\think\BaseLogic;

class NavigationLogic extends BaseLogic
{
    public function __construct()
    {
        $this->model = new Navigation();
        $this->orderField = 'sort';
        $this->orderType = 'ASC';
    }
}
