<?php

namespace plugin\b8cms\app\admin\logic;

use plugin\b8cms\app\model\ContactMessage;
use plugin\saiadmin\basic\think\BaseLogic;

class ContactMessageLogic extends BaseLogic
{
    public function __construct()
    {
        $this->model = new ContactMessage();
        $this->orderField = 'create_time';
        $this->orderType = 'DESC';
    }
}
