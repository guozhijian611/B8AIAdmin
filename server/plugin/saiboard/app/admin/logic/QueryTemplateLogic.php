<?php

namespace plugin\saiboard\app\admin\logic;

use InvalidArgumentException;
use plugin\saiadmin\basic\think\BaseLogic;
use plugin\saiadmin\exception\ApiException;
use plugin\saiboard\app\model\Datasource;
use plugin\saiboard\app\model\QueryTemplate;
use plugin\saiboard\app\service\DataSourceExecutor;

class QueryTemplateLogic extends BaseLogic
{
    protected bool $scope = true;

    public function __construct()
    {
        $this->model = new QueryTemplate();
        $this->orderField = 'id';
        $this->orderType = 'DESC';
    }

    public function add(array $data): mixed
    {
        $data = $this->sanitizePayload($data);
        $datasource = $this->ownedDatasource((int) ($data['datasource_id'] ?? 0), (int) (getCurrentInfo()['id'] ?? 0));
        $data['config'] = $this->normalizeTemplateConfig($datasource, $data);
        return parent::add($data);
    }

    public function edit($id, array $data): mixed
    {
        $data = $this->sanitizePayload($data);
        $template = $this->read($id);
        $datasource = $this->ownedDatasource((int) ($data['datasource_id'] ?? 0), (int) ($template->created_by ?? 0));
        $data['config'] = $this->normalizeTemplateConfig($datasource, $data);
        return parent::edit($id, $data);
    }

    public function enabledOptions(int $datasourceId = 0): array
    {
        if ($datasourceId > 0) {
            $this->assertDatasourceAccessible($datasourceId);
        }

        $query = QueryTemplate::where('status', 1)->field('id,datasource_id,name,dataset_type');
        if ($this->scope) {
            $query = $this->userDataScope($query);
        }
        if ($datasourceId > 0) {
            $query->where('datasource_id', $datasourceId);
        }

        return $query->order('id', 'desc')->select()->toArray();
    }

    public function assertDatasourceAccessible(int $datasourceId): void
    {
        if ($datasourceId <= 0) {
            throw new ApiException('数据源不存在');
        }

        (new DatasourceLogic())->read($datasourceId);
    }

    public function assertDatasourceOwned(int $datasourceId, int $owner): void
    {
        $this->ownedDatasource($datasourceId, $owner);
    }

    private function ownedDatasource(int $datasourceId, int $owner): Datasource
    {
        if ($datasourceId <= 0 || $owner <= 0) {
            throw new ApiException('数据源不存在或已停用');
        }

        $datasource = (new DatasourceLogic())->enabled($datasourceId);
        if ((int) ($datasource->created_by ?? 0) !== $owner) {
            throw new ApiException('数据源必须与查询模板归属一致');
        }

        return $datasource;
    }

    private function normalizeTemplateConfig(Datasource $datasource, array $data): array
    {
        try {
            return (new DataSourceExecutor())->normalizeQueryTemplateConfig(
                $datasource,
                (string) ($data['dataset_type'] ?? ''),
                $data['config'] ?? []
            );
        } catch (InvalidArgumentException $exception) {
            throw new ApiException($exception->getMessage());
        }
    }

    public function assertAccessibleIds(array $ids): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id) => $id > 0)));
        if ($ids === []) {
            return;
        }

        $query = QueryTemplate::whereIn('id', $ids);
        if ($this->scope) {
            $query = $this->userDataScope($query);
        }

        $found = array_map('intval', $query->column('id'));
        if (count($found) !== count($ids)) {
            throw new ApiException('查询模板不存在或无权使用');
        }
    }

    public function assertOwnedIds(array $ids, int $owner): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id) => $id > 0)));
        if ($ids === []) {
            return;
        }
        if ($owner <= 0) {
            throw new ApiException('查询模板不存在或无权使用');
        }

        $accessibleQuery = QueryTemplate::whereIn('id', $ids);
        if ($this->scope) {
            $accessibleQuery = $this->userDataScope($accessibleQuery);
        }
        $accessibleIds = array_map('intval', $accessibleQuery->column('id'));
        if (count($accessibleIds) !== count($ids)) {
            throw new ApiException('查询模板不存在或无权使用');
        }

        $templates = QueryTemplate::whereIn('id', $ids)
            ->where('created_by', $owner)
            ->where('status', 1)
            ->whereNull('delete_time')
            ->select()
            ->toArray();
        if (count($templates) !== count($ids)) {
            throw new ApiException('查询模板必须与大屏归属一致');
        }

        $datasourceIds = array_values(array_unique(array_map(
            static fn (array $template) => (int) ($template['datasource_id'] ?? 0),
            $templates
        )));
        $ownedDatasourceIds = Datasource::whereIn('id', $datasourceIds)
            ->where('created_by', $owner)
            ->where('status', 1)
            ->whereNull('delete_time')
            ->column('id');
        if (count(array_map('intval', $ownedDatasourceIds)) !== count($datasourceIds)) {
            throw new ApiException('查询模板必须与大屏归属一致');
        }
    }

    private function sanitizePayload(array $data): array
    {
        unset($data['created_by'], $data['updated_by'], $data['create_time'], $data['update_time'], $data['delete_time']);
        return $data;
    }
}
