<?php

namespace plugin\saiboard\app\model;

use plugin\saiadmin\basic\think\BaseModel;

class Datasource extends BaseModel
{
    protected $pk = 'id';
    protected $table = 'saiboard_datasource';

    public function searchNameAttr($query, $value): void
    {
        $query->where('name', 'like', '%' . $value . '%');
    }

    public function searchTypeAttr($query, $value): void
    {
        $query->where('type', $value);
    }

    public function searchStatusAttr($query, $value): void
    {
        $query->where('status', $value);
    }

    public function getConfigAttr($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        return json_decode((string) $value, true) ?: [];
    }

    public function setConfigAttr($value): string
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : [];
        }

        return json_encode($value ?: [], JSON_UNESCAPED_UNICODE);
    }
}
