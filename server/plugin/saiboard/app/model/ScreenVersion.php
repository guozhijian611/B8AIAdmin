<?php

namespace plugin\saiboard\app\model;

use plugin\saiadmin\basic\think\BaseModel;

class ScreenVersion extends BaseModel
{
    protected $pk = 'id';
    protected $table = 'saiboard_screen_version';

    public function searchScreenIdAttr($query, $value): void
    {
        $query->where('screen_id', $value);
    }

    public function getBgConfigAttr($value): array
    {
        return json_decode((string) $value, true) ?: [];
    }

    public function setBgConfigAttr($value): string
    {
        return $this->encodeJson($value);
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
