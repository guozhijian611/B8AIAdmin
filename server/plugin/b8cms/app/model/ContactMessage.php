<?php

namespace plugin\b8cms\app\model;

use plugin\saiadmin\basic\think\BaseModel;

class ContactMessage extends BaseModel
{
    protected $pk = 'id';
    protected $table = 'b8cms_contact_message';

    public function searchKeywordAttr($query, $value): void
    {
        $query->where('name|email|phone|subject', 'like', '%' . $value . '%');
    }

    public function searchLangCodeAttr($query, $value): void
    {
        $query->where('lang_code', $value);
    }

    public function searchStatusAttr($query, $value): void
    {
        $query->where('status', $value);
    }
}
