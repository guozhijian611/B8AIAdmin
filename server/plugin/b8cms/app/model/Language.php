<?php

namespace plugin\b8cms\app\model;

use plugin\saiadmin\basic\think\BaseModel;

class Language extends BaseModel
{
    protected $pk = 'id';
    protected $table = 'b8cms_language';

    public function searchCodeAttr($query, $value): void
    {
        $query->where('code', 'like', '%' . $value . '%');
    }

    public function searchNameAttr($query, $value): void
    {
        $query->where('name|native_name', 'like', '%' . $value . '%');
    }

    public function searchStatusAttr($query, $value): void
    {
        $query->where('status', $value);
    }
}
