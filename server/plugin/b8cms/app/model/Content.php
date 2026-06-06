<?php

namespace plugin\b8cms\app\model;

use plugin\saiadmin\basic\think\BaseModel;

class Content extends BaseModel
{
    protected $pk = 'id';
    protected $table = 'b8cms_content';

    public function searchContentTypeAttr($query, $value): void
    {
        $query->where('content_type', $value);
    }

    public function searchLangCodeAttr($query, $value): void
    {
        $query->where('lang_code', $value);
    }

    public function searchTitleAttr($query, $value): void
    {
        $query->where('title', 'like', '%' . $value . '%');
    }

    public function searchSlugAttr($query, $value): void
    {
        $query->where('slug', 'like', '%' . $value . '%');
    }

    public function searchCategoryAttr($query, $value): void
    {
        $query->where('category', 'like', '%' . $value . '%');
    }

    public function searchStatusAttr($query, $value): void
    {
        $query->where('status', $value);
    }

    public function getImagesAttr($value): array
    {
        return json_decode((string) $value, true) ?: [];
    }

    public function setImagesAttr($value): string
    {
        return json_encode($value ?: [], JSON_UNESCAPED_UNICODE);
    }

    public function getExtraAttr($value): array
    {
        return json_decode((string) $value, true) ?: [];
    }

    public function setExtraAttr($value): string
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : [];
        }

        return json_encode($value ?: [], JSON_UNESCAPED_UNICODE);
    }
}
