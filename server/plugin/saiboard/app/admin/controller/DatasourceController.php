<?php

namespace plugin\saiboard\app\admin\controller;

use hg\apidoc\annotation as Apidoc;
use plugin\saiadmin\service\Permission;
use plugin\saiboard\app\admin\logic\DatasourceLogic;
use plugin\saiboard\app\model\Datasource;
use plugin\saiboard\app\service\DataSourceExecutor;
use plugin\saiboard\app\validate\DatasourceValidate;
use support\Request;
use support\Response;

#[Apidoc\Group('SAI Board')]
#[Apidoc\Title('数据源管理')]
class DatasourceController extends AbstractCrudController
{
    public function __construct(private readonly DataSourceExecutor $executor = new DataSourceExecutor())
    {
        $this->logic = new DatasourceLogic();
        $this->validate = new DatasourceValidate();
        parent::__construct();
    }

    protected function searchFields(): array
    {
        return [
            ['name', ''],
            ['type', ''],
            ['status', ''],
        ];
    }

    #[Apidoc\Title('数据源列表')]
    #[Apidoc\Url('/app/saiboard/admin/Datasource/index')]
    #[Apidoc\Method('GET')]
    #[Permission('数据源列表', 'saiboard:datasource:index')]
    public function index(Request $request): Response
    {
        return parent::index($request);
    }

    #[Apidoc\Title('数据源读取')]
    #[Apidoc\Url('/app/saiboard/admin/Datasource/read')]
    #[Apidoc\Method('GET')]
    #[Permission('数据源读取', 'saiboard:datasource:read')]
    public function read(Request $request): Response
    {
        return parent::read($request);
    }

    #[Apidoc\Title('数据源添加')]
    #[Apidoc\Url('/app/saiboard/admin/Datasource/save')]
    #[Apidoc\Method('POST')]
    #[Permission('数据源添加', 'saiboard:datasource:save')]
    public function save(Request $request): Response
    {
        return parent::save($request);
    }

    #[Apidoc\Title('数据源修改')]
    #[Apidoc\Url('/app/saiboard/admin/Datasource/update')]
    #[Apidoc\Method('PUT')]
    #[Permission('数据源修改', 'saiboard:datasource:update')]
    public function update(Request $request): Response
    {
        return parent::update($request);
    }

    #[Apidoc\Title('数据源删除')]
    #[Apidoc\Url('/app/saiboard/admin/Datasource/destroy')]
    #[Apidoc\Method('DELETE')]
    #[Permission('数据源删除', 'saiboard:datasource:destroy')]
    public function destroy(Request $request): Response
    {
        return parent::destroy($request);
    }

    #[Apidoc\Title('数据源状态')]
    #[Apidoc\Url('/app/saiboard/admin/Datasource/changeStatus')]
    #[Apidoc\Method('POST')]
    #[Permission('数据源状态', 'saiboard:datasource:changeStatus')]
    public function changeStatus(Request $request): Response
    {
        return parent::changeStatus($request);
    }

    #[Apidoc\Title('测试数据源')]
    #[Apidoc\Url('/app/saiboard/admin/Datasource/test')]
    #[Apidoc\Method('POST')]
    #[Permission('测试数据源', 'saiboard:datasource:test')]
    public function test(Request $request): Response
    {
        $id = (int) $request->post('id', 0);
        $datasource = $id > 0 ? Datasource::findOrEmpty($id) : new Datasource($request->post());
        if ($id > 0 && $datasource->isEmpty()) {
            return $this->fail('数据源不存在');
        }

        return $this->success($this->executor->testDatasource($datasource), '连接成功');
    }

    #[Apidoc\Title('数据源选项')]
    #[Apidoc\Url('/app/saiboard/admin/Datasource/options')]
    #[Apidoc\Method('GET')]
    #[Permission('数据源选项', 'saiboard:datasource:index')]
    public function options(Request $request): Response
    {
        return $this->success($this->logic->enabledOptions());
    }
}
