<?php

namespace plugin\b8cms\app\model;

use plugin\saiadmin\basic\think\BaseModel;

class SiteSetting extends BaseModel
{
    protected $pk = 'id';
    protected $table = 'b8cms_site_setting';

    public function searchSettingKeyAttr($query, $value): void
    {
        $query->where('setting_key', 'like', '%' . $value . '%');
    }

    public function searchGroupKeyAttr($query, $value): void
    {
        $query->where('group_key', $value);
    }

    public function searchLangCodeAttr($query, $value): void
    {
        $query->where('lang_code', $value);
    }

    public function searchStatusAttr($query, $value): void
    {
        $query->where('status', $value);
    }

    public function getValueAttr($value): mixed
    {
        $decoded = json_decode((string) $value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    public function setValueAttr($value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE);
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
