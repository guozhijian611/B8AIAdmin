<?php

namespace plugin\b8cms\app\admin\logic;

use plugin\b8cms\app\model\SiteSetting;
use plugin\saiadmin\basic\think\BaseLogic;

class SiteSettingLogic extends BaseLogic
{
    public function __construct()
    {
        $this->model = new SiteSetting();
        $this->orderField = 'sort';
        $this->orderType = 'ASC';
    }
}
