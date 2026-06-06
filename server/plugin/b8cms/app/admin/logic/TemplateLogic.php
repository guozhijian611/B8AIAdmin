<?php

namespace plugin\b8cms\app\admin\logic;

use plugin\b8cms\app\model\Template;
use plugin\saiadmin\basic\think\BaseLogic;
use support\think\Db;

class TemplateLogic extends BaseLogic
{
    public function __construct()
    {
        $this->model = new Template();
        $this->orderField = 'sort';
        $this->orderType = 'ASC';
    }

    public function activate(int $id): bool
    {
        return $this->transaction(function () use ($id) {
            Db::table('b8cms_template')->whereNull('delete_time')->update(['is_active' => 2]);
            Db::table('b8cms_template')->where('id', $id)->update(['is_active' => 1, 'status' => 1]);
            return true;
        });
    }
}
