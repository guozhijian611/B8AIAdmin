<?php

namespace plugin\saiboard\app\admin\controller;

use hg\apidoc\annotation as Apidoc;
use plugin\saiadmin\service\Permission;
use plugin\saiboard\app\admin\logic\QueryTemplateLogic;
use plugin\saiboard\app\model\QueryTemplate;
use plugin\saiboard\app\service\DataSourceExecutor;
use plugin\saiboard\app\validate\QueryTemplateValidate;
use support\Request;
use support\Response;

#[Apidoc\Group('SAI Board')]
#[Apidoc\Title('查询模板管理')]
class QueryTemplateController extends AbstractCrudController
{
    public function __construct(private readonly DataSourceExecutor $executor = new DataSourceExecutor())
    {
        $this->logic = new QueryTemplateLogic();
        $this->validate = new QueryTemplateValidate();
        parent::__construct();
    }

    protected function searchFields(): array
    {
        return [
            ['datasource_id', ''],
            ['name', ''],
            ['dataset_type', ''],
            ['status', ''],
        ];
    }

    #[Apidoc\Title('查询模板列表')]
    #[Apidoc\Url('/app/saiboard/admin/QueryTemplate/index')]
    #[Apidoc\Method('GET')]
    #[Apidoc\Returned('total', type: 'int', desc: '总记录数')]
    #[Permission('查询模板列表', 'saiboard:query_template:index')]
    public function index(Request $request): Response
    {
        return parent::index($request);
    }

    #[Apidoc\Title('查询模板读取')]
    #[Apidoc\Url('/app/saiboard/admin/QueryTemplate/read')]
    #[Apidoc\Method('GET')]
    #[Apidoc\Query('id', type: 'int', require: true, desc: '查询模板ID')]
    #[Apidoc\Returned('id', type: 'int', desc: '查询模板ID')]
    #[Apidoc\Returned('name', type: 'string', desc: '查询模板名称')]
    #[Apidoc\Returned('datasource_id', type: 'int', desc: '数据源ID')]
    #[Apidoc\Returned('dataset_type', type: 'string', desc: '取值类型')]
    #[Apidoc\Returned('config', type: 'object', desc: '模板配置')]
    #[Permission('查询模板读取', 'saiboard:query_template:read')]
    public function read(Request $request): Response
    {
        return parent::read($request);
    }

    #[Apidoc\Title('查询模板添加')]
    #[Apidoc\Url('/app/saiboard/admin/QueryTemplate/save')]
    #[Apidoc\Method('POST')]
    #[Apidoc\Param('datasource_id', type: 'int', require: true, desc: '数据源ID')]
    #[Apidoc\Param('name', type: 'string', require: true, desc: '查询模板名称')]
    #[Apidoc\Param('dataset_type', type: 'string', require: true, desc: '取值类型')]
    #[Apidoc\Param('config', type: 'object', require: true, desc: '模板配置')]
    #[Permission('查询模板添加', 'saiboard:query_template:save')]
    public function save(Request $request): Response
    {
        return parent::save($request);
    }

    #[Apidoc\Title('查询模板修改')]
    #[Apidoc\Url('/app/saiboard/admin/QueryTemplate/update')]
    #[Apidoc\Method('PUT')]
    #[Apidoc\Param('id', type: 'int', require: true, desc: '查询模板ID')]
    #[Apidoc\Param('datasource_id', type: 'int', require: true, desc: '数据源ID')]
    #[Apidoc\Param('name', type: 'string', require: true, desc: '查询模板名称')]
    #[Apidoc\Param('dataset_type', type: 'string', require: true, desc: '取值类型')]
    #[Apidoc\Param('config', type: 'object', require: true, desc: '模板配置')]
    #[Permission('查询模板修改', 'saiboard:query_template:update')]
    public function update(Request $request): Response
    {
        return parent::update($request);
    }

    #[Apidoc\Title('查询模板删除')]
    #[Apidoc\Url('/app/saiboard/admin/QueryTemplate/destroy')]
    #[Apidoc\Method('DELETE')]
    #[Apidoc\Param('ids', type: 'array', require: true, desc: '查询模板ID列表')]
    #[Permission('查询模板删除', 'saiboard:query_template:destroy')]
    public function destroy(Request $request): Response
    {
        return parent::destroy($request);
    }

    #[Apidoc\Title('查询模板状态')]
    #[Apidoc\Url('/app/saiboard/admin/QueryTemplate/changeStatus')]
    #[Apidoc\Method('POST')]
    #[Apidoc\Param('id', type: 'int', require: true, desc: '查询模板ID')]
    #[Apidoc\Param('status', type: 'int', require: true, desc: '状态 1启用 2停用')]
    #[Permission('查询模板状态', 'saiboard:query_template:changeStatus')]
    public function changeStatus(Request $request): Response
    {
        return parent::changeStatus($request);
    }

    #[Apidoc\Title('预览查询模板')]
    #[Apidoc\Url('/app/saiboard/admin/QueryTemplate/preview')]
    #[Apidoc\Method('POST')]
    #[Apidoc\Param('id', type: 'int', require: false, desc: '已有查询模板ID；为空时按当前表单配置预览')]
    #[Apidoc\Param('datasource_id', type: 'int', require: false, desc: '新增态预览使用的数据源ID')]
    #[Apidoc\Param('dataset_type', type: 'string', require: false, desc: '新增态预览使用的取值类型')]
    #[Apidoc\Param('config', type: 'object', require: false, desc: '新增态预览使用的模板配置')]
    #[Apidoc\Param('params', type: 'object', require: false, desc: '预览查询参数')]
    #[Apidoc\Returned('rows', type: 'array', desc: '预览数据行')]
    #[Apidoc\Returned('total', type: 'int', desc: '预览总数')]
    #[Apidoc\Returned('diagnostics', type: 'object', desc: '脱敏诊断信息')]
    #[Permission('预览查询模板', 'saiboard:query_template:preview')]
    public function preview(Request $request): Response
    {
        $id = (int) $request->post('id', 0);
        if ($id > 0) {
            $template = $this->logic->read($id);
            $this->logic->assertDatasourceOwned((int) $template->datasource_id, (int) ($template->created_by ?? 0));
        } else {
            $data = $request->post();
            $this->logic->assertDatasourceOwned((int) ($data['datasource_id'] ?? 0), (int) (getCurrentInfo()['id'] ?? 0));
            $template = new QueryTemplate($data);
        }

        $runtimeParams = $request->post('params', []);
        return $this->success($this->executor->preview($template, is_array($runtimeParams) ? $runtimeParams : []));
    }

    #[Apidoc\Title('查询模板选项')]
    #[Apidoc\Url('/app/saiboard/admin/QueryTemplate/options')]
    #[Apidoc\Method('GET')]
    #[Apidoc\Query('datasource_id', type: 'int', require: false, desc: '数据源ID')]
    #[Permission('查询模板选项', 'saiboard:query_template:index')]
    public function options(Request $request): Response
    {
        return $this->success($this->logic->enabledOptions((int) $request->input('datasource_id', 0)));
    }
}
