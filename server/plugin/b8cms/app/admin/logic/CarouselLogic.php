<?php

namespace plugin\b8cms\app\admin\logic;

use plugin\b8cms\app\model\Carousel;
use plugin\saiadmin\basic\think\BaseLogic;

class CarouselLogic extends BaseLogic
{
    public function __construct()
    {
        $this->model = new Carousel();
        $this->orderField = 'sort';
        $this->orderType = 'ASC';
    }
}
