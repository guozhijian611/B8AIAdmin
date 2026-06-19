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

    public function getContentAttr(mixed $value, array $data = []): array
    {
        return $this->decodeJsonArray($value, $data, 'content');
    }

    public function setContentAttr(mixed $value): string
    {
        return $this->encodeJsonArray($value);
    }

    private function decodeJsonArray(mixed $value, array $data, string $field): array
    {
        if ($value === null && array_key_exists($field, $data)) {
            $value = $data[$field];
        }
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function encodeJsonArray(mixed $value): string
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : [];
        }
        if (!is_array($value)) {
            $value = [];
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }
}
