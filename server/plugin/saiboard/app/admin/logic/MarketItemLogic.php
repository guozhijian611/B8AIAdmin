<?php

namespace plugin\saiboard\app\admin\logic;

use plugin\saiadmin\basic\think\BaseLogic;
use plugin\saiadmin\exception\ApiException;
use plugin\saiboard\app\model\MarketItem;

class MarketItemLogic extends BaseLogic
{
    protected bool $scope = true;
    private const SUMMARY_FIELDS = 'id,type,name,category,description,cover_image,component_count,is_public,status,created_by,create_time,update_time';

    public function __construct()
    {
        $this->model = new MarketItem();
        $this->orderField = 'id';
        $this->orderType = 'DESC';
    }

    public function search(array $searchWhere = []): mixed
    {
        return $this->accessibleQuery(parent::search($searchWhere));
    }

    public function getList($query): mixed
    {
        $request = request();
        $saiType = $request ? $request->input('saiType', 'list') : 'list';
        $page = $request ? $request->input('page', 1) : 1;
        $limit = $request ? $request->input('limit', 10) : 10;
        $orderField = $request ? $request->input('orderField', '') : '';
        $orderType = $request ? $request->input('orderType', $this->orderType) : $this->orderType;

        $query->field(self::SUMMARY_FIELDS)->order($orderField ?: $this->orderField, $orderType);
        if ($saiType === 'all') {
            return $query->select()->toArray();
        }

        return $query->paginate($limit, false, ['page' => $page])->toArray();
    }

    public function read($id): mixed
    {
        $model = $this->accessibleQuery(MarketItem::where('id', (int) $id))->findOrEmpty();
        if ($model->isEmpty()) {
            throw new ApiException('模板不存在或无权访问');
        }

        return $model;
    }

    public function add(array $data): mixed
    {
        return parent::add($this->sanitizePayload($data));
    }

    public function edit($id, array $data): mixed
    {
        $model = $this->owned((int) $id);
        return $model->save($this->sanitizePayload($data, true));
    }

    public function destroy($ids): bool
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $ids), static fn (int $id) => $id > 0)));
        if ($ids === []) {
            return false;
        }

        $ownedIds = array_map('intval', $this->ownedQuery()->whereIn('id', $ids)->column('id'));
        if (count($ownedIds) !== count($ids)) {
            throw new ApiException('模板不存在或无权操作');
        }

        return MarketItem::destroy($ownedIds);
    }

    public function changeStatus(int $id, int $status): bool
    {
        if (!in_array($status, [1, 2], true)) {
            throw new ApiException('状态值不正确');
        }

        return (bool) $this->owned($id)->save(['status' => $status]);
    }

    public function options(array $where = []): array
    {
        $query = $this->accessibleQuery(MarketItem::where('status', 1));
        foreach (['type', 'name', 'category'] as $field) {
            $value = trim((string) ($where[$field] ?? ''));
            if ($value === '') {
                continue;
            }
            if ($field === 'type') {
                $query->where($field, $this->normalizeType($value));
            } else {
                $query->where($field, 'like', '%' . $value . '%');
            }
        }

        return $query->field(self::SUMMARY_FIELDS)->order('id', 'desc')->limit(100)->select()->toArray();
    }

    private function sanitizePayload(array $data, bool $partial = false): array
    {
        unset($data['created_by'], $data['updated_by'], $data['create_time'], $data['update_time'], $data['delete_time']);
        if (!$partial || array_key_exists('type', $data)) {
            $data['type'] = $this->normalizeType($data['type'] ?? 'screen');
        }
        foreach (['name', 'category', 'description', 'cover_image'] as $field) {
            if (!$partial || array_key_exists($field, $data)) {
                $data[$field] = trim((string) ($data[$field] ?? ''));
            }
        }
        if (!$partial && ($data['name'] ?? '') === '') {
            throw new ApiException('请填写模板名称');
        }
        if (array_key_exists('name', $data) && $data['name'] === '') {
            throw new ApiException('请填写模板名称');
        }
        if (!$partial || array_key_exists('is_public', $data)) {
            $data['is_public'] = (int) ($data['is_public'] ?? 2) === 1 ? 1 : 2;
        }
        if (!$partial || array_key_exists('status', $data)) {
            $data['status'] = in_array((int) ($data['status'] ?? 1), [1, 2], true) ? (int) ($data['status'] ?? 1) : 1;
        }
        if (!$partial || array_key_exists('content', $data)) {
            $content = $this->normalizeContent($data['type'] ?? 'screen', $data['content'] ?? []);
            $data['content'] = $content;
            $data['component_count'] = $this->componentCount($content);
        }

        return $data;
    }

    private function normalizeType(mixed $type): string
    {
        $type = (string) $type;
        return in_array($type, ['screen', 'component'], true) ? $type : 'screen';
    }

    private function normalizeContent(string $type, mixed $content): array
    {
        if (is_string($content)) {
            $decoded = json_decode($content, true);
            $content = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        }
        if (!is_array($content)) {
            $content = [];
        }

        return $type === 'component'
            ? ['components' => $this->sanitizeComponents($content['components'] ?? $content['component'] ?? [])]
            : ['layout' => $this->sanitizeLayout($content['layout'] ?? $content)];
    }

    private function sanitizeLayout(mixed $layout): array
    {
        $layout = is_array($layout) ? $layout : [];

        return [
            'canvas' => [
                'width' => max(320, (int) ($layout['canvas']['width'] ?? 1920)),
                'height' => max(240, (int) ($layout['canvas']['height'] ?? 1080)),
            ],
            'bg_config' => is_array($layout['bg_config'] ?? null) ? $layout['bg_config'] : [],
            'components' => $this->sanitizeComponents($layout['components'] ?? []),
        ];
    }

    private function sanitizeComponents(mixed $components): array
    {
        if (is_array($components) && $this->looksLikeComponent($components)) {
            $components = [$components];
        }
        if (!is_array($components)) {
            return [];
        }

        $result = [];
        foreach (array_values($components) as $component) {
            if (!is_array($component)) {
                continue;
            }
            $result[] = $this->sanitizeComponent($component);
            if (count($result) >= 120) {
                break;
            }
        }

        return $result;
    }

    private function looksLikeComponent(array $value): bool
    {
        return isset($value['type']) || isset($value['rect']);
    }

    private function sanitizeComponent(array $component): array
    {
        $dataset = is_array($component['dataset'] ?? null) ? $component['dataset'] : [];
        unset($dataset['queryTemplateId'], $dataset['query_template_id']);

        return [
            'id' => (string) ($component['id'] ?? ''),
            'type' => (string) ($component['type'] ?? 'art-bar-chart'),
            'title' => (string) ($component['title'] ?? ''),
            'rect' => [
                'x' => max(0, (int) ($component['rect']['x'] ?? 0)),
                'y' => max(0, (int) ($component['rect']['y'] ?? 0)),
                'w' => max(120, (int) ($component['rect']['w'] ?? 320)),
                'h' => max(80, (int) ($component['rect']['h'] ?? 180)),
                'z' => max(1, (int) ($component['rect']['z'] ?? 1)),
            ],
            'dataset' => $dataset,
            'option' => is_array($component['option'] ?? null) ? $component['option'] : [],
        ];
    }

    private function componentCount(array $content): int
    {
        if (isset($content['layout']['components']) && is_array($content['layout']['components'])) {
            return count($content['layout']['components']);
        }
        if (isset($content['components']) && is_array($content['components'])) {
            return count($content['components']);
        }

        return 0;
    }

    private function accessibleQuery(mixed $query): mixed
    {
        $owned = $this->ownedIds();
        return $query->where(function ($query) use ($owned) {
            $query->where('is_public', 1);
            if ($owned !== []) {
                $query->whereOr('created_by', 'in', $owned);
            }
        });
    }

    private function owned(int $id): MarketItem
    {
        $model = $this->ownedQuery()->where('id', $id)->findOrEmpty();
        if ($model->isEmpty()) {
            throw new ApiException('模板不存在或无权操作');
        }

        return $model;
    }

    private function ownedQuery(): mixed
    {
        $query = MarketItem::whereNull('delete_time');
        if ($this->scope) {
            $query = $this->userDataScope($query);
        }

        return $query;
    }

    private function ownedIds(): array
    {
        return array_map('intval', $this->ownedQuery()->column('created_by'));
    }
}
