<?php

namespace plugin\b8cms\app\model;

use plugin\saiadmin\basic\think\BaseModel;

class Comment extends BaseModel
{
    protected $pk = 'id';
    protected $table = 'b8cms_comment';

    public function searchKeywordAttr($query, $value): void
    {
        $query->where('nickname|email|comment|content_title', 'like', '%' . $value . '%');
    }

    public function searchContentIdAttr($query, $value): void
    {
        $query->where('content_id', $value);
    }

    public function searchStatusAttr($query, $value): void
    {
        $query->where('status', $value);
    }

    public function searchEmailAttr($query, $value): void
    {
        $query->where('email', 'like', '%' . $value . '%');
    }

    public function searchIpAttr($query, $value): void
    {
        $query->where('ip', 'like', '%' . $value . '%');
    }
}
