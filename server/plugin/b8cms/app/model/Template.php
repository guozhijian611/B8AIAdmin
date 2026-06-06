<?php

namespace plugin\b8cms\app\model;

use plugin\saiadmin\basic\think\BaseModel;

class Template extends BaseModel
{
    protected $pk = 'id';
    protected $table = 'b8cms_template';

    public function searchTemplateKeyAttr($query, $value): void
    {
        $query->where('template_key', 'like', '%' . $value . '%');
    }

    public function searchNameAttr($query, $value): void
    {
        $query->where('name', 'like', '%' . $value . '%');
    }

    public function searchStatusAttr($query, $value): void
    {
        $query->where('status', $value);
    }

    public function getOptionsAttr($value): array
    {
        return json_decode((string) $value, true) ?: [];
    }

    public function setOptionsAttr($value): string
    {
        return json_encode($value ?: [], JSON_UNESCAPED_UNICODE);
    }
}
