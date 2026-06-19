<?php

namespace plugin\saiboard\app\model;

use plugin\saiadmin\basic\think\BaseModel;

class Screen extends BaseModel
{
    protected $pk = 'id';
    protected $table = 'saiboard_screen';

    public function searchNameAttr($query, $value): void
    {
        $query->where('name', 'like', '%' . $value . '%');
    }

    public function searchCodeAttr($query, $value): void
    {
        $query->where('code', 'like', '%' . $value . '%');
    }

    public function searchStatusAttr($query, $value): void
    {
        $query->where('status', $value);
    }

    public function searchIsPublicAttr($query, $value): void
    {
        $query->where('is_public', $value);
    }

    public function getBgConfigAttr(mixed $value, array $data = []): array
    {
        return $this->decodeJsonArray($value, $data, 'bg_config');
    }

    public function setBgConfigAttr(mixed $value): string
    {
        return $this->encodeJson($value);
    }

    public function getDraftLayoutAttr(mixed $value, array $data = []): array
    {
        return $this->decodeJsonArray($value, $data, 'draft_layout', $this->defaultLayout());
    }

    public function setDraftLayoutAttr(mixed $value): string
    {
        return $this->encodeJson($value, $this->defaultLayout());
    }

    public function getLayoutAttr(mixed $value, array $data = []): array
    {
        return $this->decodeJsonArray($value, $data, 'layout', $this->defaultLayout());
    }

    public function setLayoutAttr(mixed $value): string
    {
        return $this->encodeJson($value, $this->defaultLayout());
    }

    private function defaultLayout(): array
    {
        return [
            'canvas' => ['width' => 1920, 'height' => 1080],
            'components' => [],
        ];
    }

    private function decodeJsonArray(mixed $value, array $data, string $field, array $default = []): array
    {
        if ($value === null && array_key_exists($field, $data)) {
            $value = $data[$field];
        }
        if (is_array($value)) {
            return $value ?: $default;
        }
        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) && $decoded !== [] ? $decoded : $default;
        }

        return $default;
    }

    private function encodeJson(mixed $value, array $default = []): string
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : [];
        }
        if (!is_array($value)) {
            $value = [];
        }

        return json_encode($value ?: $default, JSON_UNESCAPED_UNICODE);
    }
}
