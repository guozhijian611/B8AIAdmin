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

    public function getBgConfigAttr($value): array
    {
        return json_decode((string) $value, true) ?: [];
    }

    public function setBgConfigAttr($value): string
    {
        return $this->encodeJson($value);
    }

    public function getDraftLayoutAttr($value): array
    {
        return json_decode((string) $value, true) ?: $this->defaultLayout();
    }

    public function setDraftLayoutAttr($value): string
    {
        return $this->encodeJson($value ?: $this->defaultLayout());
    }

    public function getLayoutAttr($value): array
    {
        return json_decode((string) $value, true) ?: $this->defaultLayout();
    }

    public function setLayoutAttr($value): string
    {
        return $this->encodeJson($value ?: $this->defaultLayout());
    }

    private function defaultLayout(): array
    {
        return [
            'canvas' => ['width' => 1920, 'height' => 1080],
            'components' => [],
        ];
    }

    private function encodeJson(mixed $value): string
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : [];
        }

        return json_encode($value ?: [], JSON_UNESCAPED_UNICODE);
    }
}
