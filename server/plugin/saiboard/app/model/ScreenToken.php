<?php

namespace plugin\saiboard\app\model;

use plugin\saiadmin\basic\think\BaseModel;

class ScreenToken extends BaseModel
{
    protected $pk = 'id';
    protected $table = 'saiboard_screen_token';
    protected $hidden = ['delete_time', 'token_hash'];

    public function searchScreenIdAttr($query, $value): void
    {
        $query->where('screen_id', $value);
    }

    public function searchNameAttr($query, $value): void
    {
        $query->where('name', 'like', '%' . $value . '%');
    }

    public function searchStatusAttr($query, $value): void
    {
        $query->where('status', $value);
    }
}
