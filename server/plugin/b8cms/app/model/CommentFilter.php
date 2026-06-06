<?php

namespace plugin\b8cms\app\model;

use plugin\saiadmin\basic\think\BaseModel;

class CommentFilter extends BaseModel
{
    protected $pk = 'id';
    protected $table = 'b8cms_comment_filter';

    public function searchRuleTypeAttr($query, $value): void
    {
        $query->where('rule_type', $value);
    }

    public function searchMatchTypeAttr($query, $value): void
    {
        $query->where('match_type', $value);
    }

    public function searchValueAttr($query, $value): void
    {
        $query->where('value', 'like', '%' . $value . '%');
    }

    public function searchStatusAttr($query, $value): void
    {
        $query->where('status', $value);
    }
}
