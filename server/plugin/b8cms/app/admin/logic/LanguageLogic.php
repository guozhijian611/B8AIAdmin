<?php

namespace plugin\b8cms\app\admin\logic;

use plugin\b8cms\app\model\Language;
use plugin\saiadmin\basic\think\BaseLogic;
use support\think\Db;

class LanguageLogic extends BaseLogic
{
    public function __construct()
    {
        $this->model = new Language();
        $this->orderField = 'sort';
        $this->orderType = 'ASC';
    }

    public function setDefault(int $id): bool
    {
        return $this->transaction(function () use ($id) {
            Db::table('b8cms_language')->whereNull('delete_time')->update(['is_default' => 2]);
            Db::table('b8cms_language')->where('id', $id)->update(['is_default' => 1]);
            return true;
        });
    }
}
