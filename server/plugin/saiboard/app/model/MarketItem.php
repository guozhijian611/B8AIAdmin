<?php

namespace plugin\saiboard\app\model;

use plugin\saiadmin\basic\think\BaseModel;

class MarketItem extends BaseModel
{
    protected $pk = 'id';
    protected $table = 'saiboard_market_item';

    public function searchTypeAttr($query, $value): void
    {
        $query->where('type', $value);
    }

    public function searchNameAttr($query, $value): void
    {
        $query->where('name', 'like', '%' . $value . '%');
    }

    public function searchCategoryAttr($query, $value): void
    {
        $query->where('category', 'like', '%' . $value . '%');
    }

    public function searchIsPublicAttr($query, $value): void
    {
        $query->where('is_public', $value);
    }

    public function searchStatusAttr($query, $value): void
    {
        $query->where('status', $value);
    }

    public function getContentAttr($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        return json_decode((string) $value, true) ?: [];
    }

    public function setContentAttr($value): string
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : [];
        }

        return json_encode($value ?: [], JSON_UNESCAPED_UNICODE);
    }
}
