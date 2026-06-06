<?php

namespace plugin\b8cms\app\admin\logic;

use plugin\b8cms\app\model\Content;
use plugin\saiadmin\basic\think\BaseLogic;

class ContentLogic extends BaseLogic
{
    public function __construct()
    {
        $this->model = new Content();
        $this->orderField = 'sort';
        $this->orderType = 'ASC';
    }
}
