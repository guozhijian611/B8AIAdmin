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
    #[Permission('查询模板列表', 'saiboard:query_template:index')]
    public function index(Request $request): Response
    {
        return parent::index($request);
    }

    #[Apidoc\Title('查询模板读取')]
    #[Apidoc\Url('/app/saiboard/admin/QueryTemplate/read')]
    #[Apidoc\Method('GET')]
    #[Permission('查询模板读取', 'saiboard:query_template:read')]
    public function read(Request $request): Response
    {
        return parent::read($request);
    }

    #[Apidoc\Title('查询模板添加')]
    #[Apidoc\Url('/app/saiboard/admin/QueryTemplate/save')]
    #[Apidoc\Method('POST')]
    #[Permission('查询模板添加', 'saiboard:query_template:save')]
    public function save(Request $request): Response
    {
        return parent::save($request);
    }

    #[Apidoc\Title('查询模板修改')]
    #[Apidoc\Url('/app/saiboard/admin/QueryTemplate/update')]
    #[Apidoc\Method('PUT')]
    #[Permission('查询模板修改', 'saiboard:query_template:update')]
    public function update(Request $request): Response
    {
        return parent::update($request);
    }

    #[Apidoc\Title('查询模板删除')]
    #[Apidoc\Url('/app/saiboard/admin/QueryTemplate/destroy')]
    #[Apidoc\Method('DELETE')]
    #[Permission('查询模板删除', 'saiboard:query_template:destroy')]
    public function destroy(Request $request): Response
    {
        return parent::destroy($request);
    }

    #[Apidoc\Title('查询模板状态')]
    #[Apidoc\Url('/app/saiboard/admin/QueryTemplate/changeStatus')]
    #[Apidoc\Method('POST')]
    #[Permission('查询模板状态', 'saiboard:query_template:changeStatus')]
    public function changeStatus(Request $request): Response
    {
        return parent::changeStatus($request);
    }

    #[Apidoc\Title('预览查询模板')]
    #[Apidoc\Url('/app/saiboard/admin/QueryTemplate/preview')]
    #[Apidoc\Method('POST')]
    #[Apidoc\Param('params', type: 'object', require: false, desc: '预览查询参数')]
    #[Permission('预览查询模板', 'saiboard:query_template:preview')]
    public function preview(Request $request): Response
    {
        $id = (int) $request->post('id', 0);
        $template = $id > 0 ? QueryTemplate::findOrEmpty($id) : new QueryTemplate($request->post());
        if ($id > 0 && $template->isEmpty()) {
            return $this->fail('查询模板不存在');
        }

        $runtimeParams = $request->post('params', []);
        return $this->success($this->executor->preview($template, is_array($runtimeParams) ? $runtimeParams : []));
    }

    #[Apidoc\Title('查询模板选项')]
    #[Apidoc\Url('/app/saiboard/admin/QueryTemplate/options')]
    #[Apidoc\Method('GET')]
    #[Permission('查询模板选项', 'saiboard:query_template:index')]
    public function options(Request $request): Response
    {
        return $this->success($this->logic->enabledOptions((int) $request->input('datasource_id', 0)));
    }
}
