<?php

namespace plugin\b8cms\app\model;

use plugin\saiadmin\basic\think\BaseModel;

class Navigation extends BaseModel
{
    protected $pk = 'id';
    protected $table = 'b8cms_navigation';

    public function searchLangCodeAttr($query, $value): void
    {
        $query->where('lang_code', $value);
    }

    public function searchPositionAttr($query, $value): void
    {
        $query->where('position', $value);
    }

    public function searchTitleAttr($query, $value): void
    {
        $query->where('title', 'like', '%' . $value . '%');
    }

    public function searchStatusAttr($query, $value): void
    {
        $query->where('status', $value);
    }
}
