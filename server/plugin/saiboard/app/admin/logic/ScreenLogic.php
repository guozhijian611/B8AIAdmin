<?php

namespace plugin\saiboard\app\admin\logic;

use plugin\saiadmin\basic\think\BaseLogic;
use plugin\saiadmin\exception\ApiException;
use plugin\saiboard\app\model\Datasource;
use plugin\saiboard\app\model\Screen;
use plugin\saiboard\app\model\ScreenToken;
use plugin\saiboard\app\model\ScreenVersion;
use plugin\saiboard\app\service\DataSourceExecutor;

class ScreenLogic extends BaseLogic
{
    protected bool $scope = true;

    public function __construct()
    {
        $this->model = new Screen();
        $this->orderField = 'id';
        $this->orderType = 'DESC';
    }

    public function add(array $data): mixed
    {
        $data = $this->normalizePayload($data);
        return parent::add($data);
    }

    public function edit($id, array $data): mixed
    {
        $data = $this->normalizePayload($data, (int) $id);
        return parent::edit($id, $data);
    }

    public function saveLayout(int $id, array $layout): bool
    {
        return (bool) $this->transaction(function () use ($id, $layout) {
            $screen = $this->read($id);
            $layout = $this->normalizeLayout($layout, (int) $screen->width, (int) $screen->height);
            $this->assertLayoutTemplates($layout, (int) ($screen->created_by ?? 0));
            $result = (bool) $screen->save([
                'draft_layout' => $layout,
            ]);
            if ($result) {
                $this->createVersion($screen, 'save_layout', $layout);
            }

            return $result;
        });
    }

    public function publish(int $id): bool
    {
        return (bool) $this->transaction(function () use ($id) {
            $screen = $this->read($id);
            $layout = $screen->draft_layout ?: $this->defaultLayout((int) $screen->width, (int) $screen->height);
            $layout = $this->normalizeLayout($layout, (int) $screen->width, (int) $screen->height);
            $this->assertLayoutTemplates($layout, (int) ($screen->created_by ?? 0));
            $width = (int) $layout['canvas']['width'];
            $height = (int) $layout['canvas']['height'];
            $bgConfig = $this->normalizeBgConfig($layout['bg_config'] ?? $screen->bg_config);
            $result = (bool) $screen->save([
                'width' => $width,
                'height' => $height,
                'bg_config' => $bgConfig,
                'layout' => $layout,
                'status' => 1,
            ]);
            if ($result) {
                $this->createVersion($screen, 'publish', $layout);
            }

            return $result;
        });
    }

    public function copy(int $id): int
    {
        $screen = $this->read($id);
        $data = $screen->toArray();
        $width = (int) $data['width'];
        $height = (int) $data['height'];
        $draftLayout = $screen->draft_layout ?: $this->defaultLayout($width, $height);
        $publishedLayout = $screen->layout ?: $draftLayout;
        $owner = (int) ($screen->created_by ?? 0);
        $currentOwner = (int) (getCurrentInfo()['id'] ?? 0);
        if ($owner !== $currentOwner) {
            $draftLayout = $this->detachLayoutTemplates($draftLayout);
            $publishedLayout = $this->detachLayoutTemplates($publishedLayout);
        }
        unset($data['id'], $data['create_time'], $data['update_time'], $data['delete_time']);
        $data['name'] = $data['name'] . ' 副本';
        $data['code'] = $this->generateCode();
        $data['access_token'] = '';
        $data['status'] = 2;
        $data['draft_layout'] = $this->normalizeLayout($draftLayout, $width, $height);
        $data['layout'] = $this->normalizeLayout($publishedLayout, $width, $height);

        return (int) parent::add($data);
    }

    public function generateFromTable(array $data): array
    {
        return $this->transaction(function () use ($data) {
            $datasourceId = (int) ($data['datasource_id'] ?? 0);
            $table = $this->normalizeAutoTable($data['table'] ?? '');
            $width = max(320, (int) ($data['width'] ?? 1920));
            $height = max(240, (int) ($data['height'] ?? 1080));
            (new QueryTemplateLogic())->assertDatasourceOwned(
                $datasourceId,
                (int) (getCurrentInfo()['id'] ?? 0)
            );
            $datasource = (new DatasourceLogic())->enabled($datasourceId);
            if ((string) $datasource->type !== 'mysql') {
                throw new ApiException('只支持从 MySQL 数据源生成大屏');
            }

            $schema = (new DataSourceExecutor())->schema($datasource, $table);
            $columns = is_array($schema['columns'] ?? null) ? $schema['columns'] : [];
            if ($columns === []) {
                throw new ApiException('数据表没有可用字段');
            }

            $analysis = $this->analyzeAutoColumns($columns);
            $name = trim((string) ($data['name'] ?? ''));
            $name = $name !== '' ? mb_substr($name, 0, 60) : $this->humanizeAutoName($table) . ' 数据大屏';
            $templates = $this->createAutoQueryTemplates($datasource, $table, $name, $analysis);
            $layout = $this->buildAutoLayout($width, $height, $table, $name, $analysis, $templates);
            $screenId = (int) $this->add([
                'name' => $name,
                'width' => $width,
                'height' => $height,
                'is_public' => 2,
                'status' => 2,
                'bg_config' => [
                    'color' => '#07111f',
                    'theme' => 'midnight',
                    'fit_mode' => 'contain',
                ],
                'draft_layout' => $layout,
                'layout' => $this->defaultLayout($width, $height),
            ]);

            return [
                'id' => $screenId,
                'name' => $name,
                'table' => $table,
                'template_ids' => array_column($templates, 'id'),
                'component_count' => count($layout['components'] ?? []),
            ];
        });
    }

    public function versions(int $screenId): array
    {
        $this->read($screenId);

        return ScreenVersion::where('screen_id', $screenId)
            ->whereNull('delete_time')
            ->order('id', 'desc')
            ->limit(50)
            ->select()
            ->toArray();
    }

    public function restoreVersion(int $screenId, int $versionId): bool
    {
        return (bool) $this->transaction(function () use ($screenId, $versionId) {
            $screen = $this->read($screenId);
            $version = ScreenVersion::where('screen_id', $screenId)
                ->where('id', $versionId)
                ->whereNull('delete_time')
                ->findOrEmpty();
            if ($version->isEmpty()) {
                throw new ApiException('版本不存在');
            }

            $this->createVersion(
                $screen,
                'restore_before',
                $screen->draft_layout ?: $this->defaultLayout((int) $screen->width, (int) $screen->height)
            );
            $width = (int) $version->width;
            $height = (int) $version->height;
            $layout = $this->normalizeLayout($version->layout, $width, $height);
            $layout['bg_config'] = $this->normalizeBgConfig($version->bg_config);
            $this->assertLayoutTemplates($layout, (int) ($screen->created_by ?? 0));

            return (bool) $screen->save([
                'draft_layout' => $layout,
            ]);
        });
    }

    public function deleteVersion(int $screenId, int $versionId): bool
    {
        $this->read($screenId);
        $version = ScreenVersion::where('screen_id', $screenId)
            ->where('id', $versionId)
            ->whereNull('delete_time')
            ->findOrEmpty();
        if ($version->isEmpty()) {
            throw new ApiException('版本不存在');
        }

        return (bool) $version->delete();
    }

    public function tokens(int $screenId): array
    {
        $this->read($screenId);

        $rows = [];
        foreach (ScreenToken::where('screen_id', $screenId)
            ->whereNull('delete_time')
            ->order('id', 'desc')
            ->select() as $token) {
            $rows[] = $this->formatTokenRow($token);
        }

        return $rows;
    }

    public function createToken(int $screenId, array $data): array
    {
        $this->read($screenId);
        $plainToken = $this->generateToken();
        $token = ScreenToken::create([
            'screen_id' => $screenId,
            'name' => $this->normalizeTokenName($data['name'] ?? ''),
            'token_prefix' => substr($plainToken, 0, 8),
            'token_hash' => $this->tokenHash($plainToken),
            'expire_time' => $this->normalizeExpireTime($data['expire_time'] ?? null),
            'status' => 1,
        ]);

        return [
            'token' => $plainToken,
            'row' => $this->formatTokenRow($token),
        ];
    }

    public function resetToken(int $screenId, int $tokenId): array
    {
        $this->read($screenId);
        $token = $this->tokenRow($screenId, $tokenId);
        $plainToken = $this->generateToken();
        $token->save([
            'token_prefix' => substr($plainToken, 0, 8),
            'token_hash' => $this->tokenHash($plainToken),
            'last_used_time' => null,
        ]);

        return [
            'token' => $plainToken,
            'row' => $this->formatTokenRow($token),
        ];
    }

    public function changeTokenStatus(int $screenId, int $tokenId, int $status): bool
    {
        $this->read($screenId);
        if (!in_array($status, [1, 2], true)) {
            throw new ApiException('状态值不正确');
        }

        return (bool) $this->tokenRow($screenId, $tokenId)->save(['status' => $status]);
    }

    public function deleteToken(int $screenId, int $tokenId): bool
    {
        $this->read($screenId);
        return (bool) $this->tokenRow($screenId, $tokenId)->delete();
    }

    public function visibleIds(): array
    {
        $query = Screen::field('id');
        if ($this->scope) {
            $query = $this->userDataScope($query);
        }

        return array_map('intval', $query->column('id'));
    }

    private function normalizeAutoTable(mixed $table): string
    {
        $table = trim((string) $table);
        if ($table === '' || !preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            throw new ApiException('数据表名称不正确');
        }

        return $table;
    }

    private function analyzeAutoColumns(array $columns): array
    {
        $normalized = [];
        foreach ($columns as $column) {
            if (!is_array($column)) {
                continue;
            }
            $name = trim((string) ($column['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $normalized[] = [
                'name' => $name,
                'type' => strtolower((string) ($column['type'] ?? '')),
                'kind' => strtolower((string) ($column['kind'] ?? 'string')),
            ];
        }
        if ($normalized === []) {
            throw new ApiException('数据表没有可用字段');
        }

        $dateColumns = array_values(array_filter(
            $normalized,
            static fn (array $column) => $column['kind'] === 'date'
        ));
        $numberColumns = array_values(array_filter(
            $normalized,
            fn (array $column) => $column['kind'] === 'number' && !$this->isAutoMetricExcludedNumber($column['name'])
        ));
        $stringColumns = array_values(array_filter(
            $normalized,
            static fn (array $column) => $column['kind'] === 'string'
        ));
        $dimensionColumns = array_values(array_filter(
            $normalized,
            fn (array $column) => $column['kind'] === 'string' || $this->isAutoDimensionOnlyNumber($column['name'])
        ));

        $dateField = $this->preferredAutoField($dateColumns, [
            '/create|created|order|pay|paid|time|date|day|month|update/i',
        ]);
        $metricField = $this->preferredAutoField($numberColumns, [
            '/amount|price|money|total|fee|cost|revenue|income|sales|stock|qty|quantity|num|count|view|click|score|value/i',
        ]);
        $labelField = $this->preferredAutoField($stringColumns, [
            '/name|title|subject|label|category|type|method|source|channel|city|province|platform|lang|code/i',
        ]);
        $categoryField = $this->preferredAutoField($dimensionColumns, [
            '/status|type|category|method|source|channel|platform|lang|level|city|province/i',
        ]);
        if ($categoryField === $labelField) {
            $categoryField = $this->preferredAutoField($dimensionColumns, [
                '/status|type|category|method|source|channel|platform|lang|level|city|province/i',
            ], [$labelField]);
        }

        return [
            'columns' => $normalized,
            'date_field' => $dateField,
            'metric_field' => $metricField,
            'label_field' => $labelField,
            'category_field' => $categoryField ?: $labelField,
            'raw_fields' => $this->autoRawFields($normalized),
            'order_field' => $dateField ?: $this->preferredAutoField($normalized, ['/^id$/i', '/_id$/i']),
        ];
    }

    private function createAutoQueryTemplates(Datasource $datasource, string $table, string $screenName, array $analysis): array
    {
        $templateLogic = new QueryTemplateLogic();
        $items = [
            'count' => [
                'name' => "{$screenName} 总记录数",
                'dataset_type' => 'table_count',
                'config' => [
                    'table' => $table,
                    'conditions' => [],
                ],
            ],
            'raw' => [
                'name' => "{$screenName} 最新明细",
                'dataset_type' => 'table_raw',
                'config' => [
                    'table' => $table,
                    'fields' => $analysis['raw_fields'],
                    'field_aliases' => [],
                    'computed_fields' => [],
                    'conditions' => [],
                    'order' => $analysis['order_field']
                        ? [['field' => $analysis['order_field'], 'direction' => 'desc']]
                        : [],
                    'limit' => 8,
                ],
            ],
        ];

        if ($analysis['date_field']) {
            $items['trend'] = [
                'name' => "{$screenName} 趋势",
                'dataset_type' => 'table_aggregate',
                'config' => [
                    'table' => $table,
                    'dimension' => $analysis['date_field'],
                    'dimension_type' => 'day',
                    'metrics' => [$this->autoMetricConfig($analysis['metric_field'])],
                    'conditions' => [],
                    'order_by' => 'label',
                    'order_type' => 'asc',
                    'limit' => 30,
                ],
            ];
        }

        if ($analysis['label_field']) {
            $items['rank'] = [
                'name' => "{$screenName} 排行",
                'dataset_type' => 'table_aggregate',
                'config' => [
                    'table' => $table,
                    'dimension' => $analysis['label_field'],
                    'dimension_type' => 'raw',
                    'metrics' => [$this->autoMetricConfig($analysis['metric_field'])],
                    'conditions' => [],
                    'order_by' => 'value',
                    'order_type' => 'desc',
                    'limit' => 10,
                ],
            ];
        }

        if ($analysis['category_field'] && $analysis['category_field'] !== $analysis['label_field']) {
            $items['distribution'] = [
                'name' => "{$screenName} 分布",
                'dataset_type' => 'table_aggregate',
                'config' => [
                    'table' => $table,
                    'dimension' => $analysis['category_field'],
                    'dimension_type' => 'raw',
                    'metrics' => [$this->autoMetricConfig('')],
                    'conditions' => [],
                    'order_by' => 'value',
                    'order_type' => 'desc',
                    'limit' => 8,
                ],
            ];
        }

        $templates = [];
        foreach ($items as $key => $item) {
            $id = (int) $templateLogic->add([
                'datasource_id' => (int) $datasource->id,
                'name' => mb_substr((string) $item['name'], 0, 60),
                'dataset_type' => $item['dataset_type'],
                'config' => $item['config'],
                'status' => 1,
            ]);
            $templates[$key] = $item + ['id' => $id];
        }

        return $templates;
    }

    private function buildAutoLayout(
        int $width,
        int $height,
        string $table,
        string $name,
        array $analysis,
        array $templates
    ): array {
        $components = [
            $this->autoDecorTitle($name, $table),
            $this->autoDataComponent(
                'auto_total',
                'stat-number',
                '记录总数',
                ['x' => 40, 'y' => 112, 'w' => 360, 'h' => 150, 'z' => 2],
                $templates['count']['id'],
                ['valueField' => 'total'],
                ['unit' => '条', 'decimals' => 0]
            ),
        ];

        if (isset($templates['trend'])) {
            $components[] = $this->autoDataComponent(
                'auto_trend',
                'art-line-chart',
                $analysis['metric_field'] ? '趋势汇总' : '数量趋势',
                ['x' => 440, 'y' => 112, 'w' => 860, 'h' => 360, 'z' => 2],
                $templates['trend']['id'],
                ['labelField' => 'label', 'valueField' => 'value'],
                ['showAreaColor' => true, 'showLegend' => false]
            );
        }

        if (isset($templates['distribution'])) {
            $components[] = $this->autoDataComponent(
                'auto_distribution',
                'art-ring-chart',
                '分类分布',
                ['x' => 1340, 'y' => 112, 'w' => 540, 'h' => 360, 'z' => 2],
                $templates['distribution']['id'],
                ['labelField' => 'label', 'valueField' => 'value'],
                ['showLegend' => true, 'legendPosition' => 'right']
            );
        }

        if (isset($templates['rank'])) {
            $components[] = $this->autoDataComponent(
                'auto_rank',
                'art-h-bar-chart',
                '数据排行',
                ['x' => 40, 'y' => 512, 'w' => 720, 'h' => 500, 'z' => 2],
                $templates['rank']['id'],
                ['labelField' => 'label', 'valueField' => 'value'],
                ['showLegend' => false]
            );
        }

        $components[] = $this->autoDataComponent(
            'auto_table',
            'data-table',
            '最新明细',
            ['x' => isset($templates['rank']) ? 800 : 40, 'y' => 512, 'w' => isset($templates['rank']) ? 1080 : 1840, 'h' => 500, 'z' => 2],
            $templates['raw']['id'],
            [
                'tableFields' => $analysis['raw_fields'],
                'tableColumns' => array_map(
                    fn (string $field) => ['field' => $field, 'label' => $this->humanizeAutoName($field), 'align' => 'left'],
                    $analysis['raw_fields']
                ),
            ],
            ['showIndex' => true, 'rowStripe' => true, 'maxRows' => 8]
        );

        return [
            'canvas' => ['width' => $width, 'height' => $height],
            'bg_config' => [
                'color' => '#07111f',
                'theme' => 'midnight',
                'fit_mode' => 'contain',
            ],
            'components' => $components,
        ];
    }

    private function autoDecorTitle(string $name, string $table): array
    {
        return [
            'id' => 'auto_title',
            'type' => 'decor-title',
            'title' => '',
            'rect' => ['x' => 40, 'y' => 24, 'w' => 1840, 'h' => 72, 'z' => 1],
            'dataset' => [],
            'option' => [
                'text' => $name,
                'subtitle' => "由 {$table} 自动生成",
                'variant' => 'bar',
                'align' => 'center',
                'accent' => '#69b7ff',
            ],
        ];
    }

    private function autoDataComponent(
        string $id,
        string $type,
        string $title,
        array $rect,
        int $templateId,
        array $mapping,
        array $option = []
    ): array {
        return [
            'id' => $id,
            'type' => $type,
            'title' => $title,
            'rect' => $rect,
            'dataset' => [
                'queryTemplateId' => $templateId,
                'refresh' => 30,
                'mapping' => $mapping,
            ],
            'option' => $option,
        ];
    }

    private function autoMetricConfig(string $metricField): array
    {
        if ($metricField === '') {
            return ['aggregate' => 'count', 'field' => '', 'alias' => 'value'];
        }

        return ['aggregate' => 'sum', 'field' => $metricField, 'alias' => 'value'];
    }

    private function preferredAutoField(array $columns, array $patterns, array $excluded = []): string
    {
        foreach ($patterns as $pattern) {
            foreach ($columns as $column) {
                $name = (string) ($column['name'] ?? '');
                if ($name !== '' && !in_array($name, $excluded, true) && preg_match($pattern, $name)) {
                    return $name;
                }
            }
        }

        foreach ($columns as $column) {
            $name = (string) ($column['name'] ?? '');
            if ($name !== '' && !in_array($name, $excluded, true)) {
                return $name;
            }
        }

        return '';
    }

    private function autoRawFields(array $columns): array
    {
        $fields = [];
        foreach (['id', 'order_no', 'code', 'title', 'name', 'category', 'type', 'status', 'price', 'amount', 'total', 'create_time', 'created_at', 'update_time'] as $preferred) {
            foreach ($columns as $column) {
                $name = (string) $column['name'];
                if ($name === $preferred && !in_array($name, $fields, true)) {
                    $fields[] = $name;
                }
            }
        }
        foreach ($columns as $column) {
            $name = (string) $column['name'];
            if (!in_array($name, $fields, true)) {
                $fields[] = $name;
            }
            if (count($fields) >= 8) {
                break;
            }
        }

        return array_slice($fields, 0, 8);
    }

    private function isAutoDimensionOnlyNumber(string $name): bool
    {
        return $name === 'status'
            || str_starts_with($name, 'is_')
            || str_ends_with($name, '_status')
            || str_ends_with($name, '_type')
            || str_ends_with($name, '_level');
    }

    private function isAutoMetricExcludedNumber(string $name): bool
    {
        return $name === 'id'
            || $name === 'created_by'
            || $name === 'updated_by'
            || $name === 'sort'
            || str_ends_with($name, '_id')
            || $this->isAutoDimensionOnlyNumber($name);
    }

    private function humanizeAutoName(string $name): string
    {
        return trim(str_replace('_', ' ', $name)) ?: $name;
    }

    private function normalizePayload(array $data, int $ignoreId = 0): array
    {
        unset($data['created_by'], $data['updated_by'], $data['create_time'], $data['update_time'], $data['delete_time']);
        if ($ignoreId > 0) {
            unset($data['layout'], $data['draft_layout']);
        }

        $width = max(320, (int) ($data['width'] ?? 1920));
        $height = max(240, (int) ($data['height'] ?? 1080));
        $data['width'] = $width;
        $data['height'] = $height;
        $data['code'] = trim((string) ($data['code'] ?? '')) ?: $this->generateCode();
        $data['is_public'] = (int) ($data['is_public'] ?? 1);
        $data['status'] = (int) ($data['status'] ?? 2);
        $data['bg_config'] = $this->normalizeBgConfig($data['bg_config'] ?? []);
        if ($ignoreId <= 0) {
            $owner = (int) (getCurrentInfo()['id'] ?? 0);
            $data['draft_layout'] = $this->normalizeLayout(
                is_array($data['draft_layout'] ?? null) ? $data['draft_layout'] : $this->defaultLayout($width, $height),
                $width,
                $height
            );
            $data['layout'] = $this->normalizeLayout(
                is_array($data['layout'] ?? null) ? $data['layout'] : $this->defaultLayout($width, $height),
                $width,
                $height
            );
            $this->assertLayoutTemplates($data['draft_layout'], $owner);
            $this->assertLayoutTemplates($data['layout'], $owner);
        }

        $query = Screen::where('code', $data['code']);
        if ($ignoreId > 0) {
            $query->where('id', '<>', $ignoreId);
        }
        if ($query->whereNull('delete_time')->value('id')) {
            throw new ApiException('访问编码已存在');
        }

        return $data;
    }

    private function normalizeLayout(array $layout, int $width, int $height): array
    {
        $layout['canvas'] = [
            'width' => max(320, (int) ($layout['canvas']['width'] ?? $width)),
            'height' => max(240, (int) ($layout['canvas']['height'] ?? $height)),
        ];
        if (isset($layout['bg_config'])) {
            $layout['bg_config'] = $this->normalizeBgConfig($layout['bg_config']);
        }
        $components = is_array($layout['components'] ?? null) ? $layout['components'] : [];
        $layout['components'] = array_values(array_filter($components, static fn ($item) => is_array($item)));
        return $layout;
    }

    private function defaultLayout(int $width, int $height): array
    {
        return [
            'canvas' => ['width' => $width, 'height' => $height],
            'components' => [],
        ];
    }

    private function assertLayoutTemplates(array $layout, int $owner): void
    {
        (new QueryTemplateLogic())->assertOwnedIds($this->layoutTemplateIds($layout), $owner);
    }

    private function layoutTemplateIds(array $layout): array
    {
        $ids = [];
        $components = is_array($layout['components'] ?? null) ? $layout['components'] : [];
        foreach ($components as $component) {
            if (!is_array($component)) {
                continue;
            }
            $dataset = is_array($component['dataset'] ?? null) ? $component['dataset'] : [];
            $ids[] = $dataset['queryTemplateId'] ?? $dataset['query_template_id'] ?? 0;
        }

        return $ids;
    }

    private function detachLayoutTemplates(array $layout): array
    {
        $components = is_array($layout['components'] ?? null) ? $layout['components'] : [];
        foreach ($components as $index => $component) {
            if (!is_array($component)) {
                continue;
            }
            $dataset = is_array($component['dataset'] ?? null) ? $component['dataset'] : [];
            unset($dataset['queryTemplateId'], $dataset['query_template_id']);
            $component['dataset'] = $dataset;
            $components[$index] = $component;
        }
        $layout['components'] = $components;

        return $layout;
    }

    private function createVersion(Screen $screen, string $source, array $layout): void
    {
        $screenId = (int) $screen->id;
        Screen::where('id', $screenId)->lock(true)->value('id');
        $width = max(320, (int) ($layout['canvas']['width'] ?? $screen->width));
        $height = max(240, (int) ($layout['canvas']['height'] ?? $screen->height));
        $versionNo = ((int) ScreenVersion::where('screen_id', $screenId)->max('version_no')) + 1;

        ScreenVersion::create([
            'screen_id' => $screenId,
            'version_no' => $versionNo,
            'source' => $source,
            'title' => $this->versionTitle($source, $versionNo),
            'width' => $width,
            'height' => $height,
            'bg_config' => $this->normalizeBgConfig($layout['bg_config'] ?? $screen->bg_config),
            'layout' => $this->normalizeLayout($layout, $width, $height),
        ]);
        $this->trimVersions($screenId);
    }

    private function trimVersions(int $screenId): void
    {
        $keepIds = ScreenVersion::where('screen_id', $screenId)
            ->whereNull('delete_time')
            ->order('id', 'desc')
            ->limit(50)
            ->column('id');
        if (!$keepIds) {
            return;
        }

        ScreenVersion::where('screen_id', $screenId)
            ->whereNull('delete_time')
            ->whereNotIn('id', $keepIds)
            ->delete();
    }

    private function versionTitle(string $source, int $versionNo): string
    {
        $label = match ($source) {
            'publish' => '发布快照',
            'restore_before' => '恢复前快照',
            default => '保存快照',
        };

        return $label . ' #' . $versionNo;
    }

    private function normalizeBgConfig(mixed $config): array
    {
        $config = is_array($config) ? $config : [];
        $theme = (string) ($config['theme'] ?? 'midnight');
        $fitMode = (string) ($config['fit_mode'] ?? 'contain');
        $imageFit = (string) ($config['image_fit'] ?? 'cover');

        return array_merge($config, [
            'color' => trim((string) ($config['color'] ?? '')) ?: '#07111f',
            'theme' => in_array($theme, ['midnight', 'teal', 'amber'], true) ? $theme : 'midnight',
            'fit_mode' => in_array($fitMode, ['contain', 'cover', 'stretch'], true) ? $fitMode : 'contain',
            'image' => trim((string) ($config['image'] ?? '')),
            'image_fit' => in_array($imageFit, ['cover', 'contain', 'stretch', 'repeat'], true)
                ? $imageFit
                : 'cover',
        ]);
    }

    private function generateCode(): string
    {
        do {
            $code = 'sb_' . bin2hex(random_bytes(8));
        } while (Screen::where('code', $code)->whereNull('delete_time')->value('id'));

        return $code;
    }

    private function generateToken(): string
    {
        return bin2hex(random_bytes(24));
    }

    private function tokenRow(int $screenId, int $tokenId): ScreenToken
    {
        $token = ScreenToken::where('screen_id', $screenId)
            ->where('id', $tokenId)
            ->whereNull('delete_time')
            ->findOrEmpty();
        if ($token->isEmpty()) {
            throw new ApiException('访问令牌不存在');
        }

        return $token;
    }

    private function normalizeTokenName(mixed $name): string
    {
        $name = trim((string) $name);
        if ($name === '') {
            throw new ApiException('请填写令牌名称');
        }

        return mb_substr($name, 0, 80);
    }

    private function normalizeExpireTime(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);
        if (!$timestamp) {
            throw new ApiException('过期时间不正确');
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function tokenHash(string $token): string
    {
        return hash('sha256', $token);
    }

    private function formatTokenRow(ScreenToken $token): array
    {
        $row = $token->toArray();
        unset($row['token_hash']);
        $row['is_expired'] = $this->isTokenExpired($row['expire_time'] ?? null);

        return $row;
    }

    private function isTokenExpired(mixed $expireTime): bool
    {
        $expireTime = trim((string) $expireTime);
        return $expireTime !== '' && strtotime($expireTime) <= time();
    }
}
