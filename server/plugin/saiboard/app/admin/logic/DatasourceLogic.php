<?php

namespace plugin\saiboard\app\admin\logic;

use InvalidArgumentException;
use plugin\saiadmin\basic\think\BaseLogic;
use plugin\saiadmin\exception\ApiException;
use plugin\saiboard\app\model\Datasource;
use plugin\saiboard\app\service\DataSourceExecutor;

class DatasourceLogic extends BaseLogic
{
    protected bool $scope = true;

    public function __construct()
    {
        $this->model = new Datasource();
        $this->orderField = 'id';
        $this->orderType = 'DESC';
    }

    public function add(array $data): mixed
    {
        return parent::add($this->sanitizePayload($data));
    }

    public function edit($id, array $data): mixed
    {
        return parent::edit($id, $this->sanitizePayload($data));
    }

    public function enabledOptions(): array
    {
        $query = Datasource::where('status', 1);
        if ($this->scope) {
            $query = $this->userDataScope($query);
        }

        return $query
            ->field('id,name,type')
            ->order('id', 'desc')
            ->select()
            ->toArray();
    }

    public function enabled(int $id): Datasource
    {
        $datasource = $this->read($id);
        if ((int) $datasource->status !== 1) {
            throw new ApiException('数据源不存在或已停用');
        }

        return $datasource;
    }

    private function sanitizePayload(array $data): array
    {
        unset($data['created_by'], $data['updated_by'], $data['create_time'], $data['update_time'], $data['delete_time'], $data['last_error']);

        $data['type'] = (string) ($data['type'] ?? '');
        try {
            $data['config'] = (new DataSourceExecutor())->normalizeDatasourceConfig(
                $data['type'],
                $data['config'] ?? []
            );
        } catch (InvalidArgumentException $exception) {
            throw new ApiException($exception->getMessage());
        }

        $data['cache_ttl'] = min(86400, max(0, (int) ($data['cache_ttl'] ?? 0)));
        return $data;
    }
}
