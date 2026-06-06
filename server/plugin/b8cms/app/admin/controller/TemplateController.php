<?php

namespace plugin\b8cms\app\admin\controller;

use plugin\b8cms\app\admin\logic\TemplateLogic;
use plugin\b8cms\app\validate\TemplateValidate;
use plugin\saiadmin\service\Permission;
use support\Request;
use support\Response;

class TemplateController extends AbstractCrudController
{
    public function __construct()
    {
        $this->logic = new TemplateLogic();
        $this->validate = new TemplateValidate();
        parent::__construct();
    }

    protected function searchFields(): array
    {
        return [
            ['template_key', ''],
            ['name', ''],
            ['status', ''],
        ];
    }

    #[Permission('模板列表', 'b8cms:template:index')]
    public function index(Request $request): Response
    {
        return parent::index($request);
    }

    #[Permission('模板读取', 'b8cms:template:read')]
    public function read(Request $request): Response
    {
        return parent::read($request);
    }

    #[Permission('模板添加', 'b8cms:template:save')]
    public function save(Request $request): Response
    {
        return parent::save($request);
    }

    #[Permission('模板修改', 'b8cms:template:update')]
    public function update(Request $request): Response
    {
        return parent::update($request);
    }

    #[Permission('模板删除', 'b8cms:template:destroy')]
    public function destroy(Request $request): Response
    {
        return parent::destroy($request);
    }

    #[Permission('模板状态', 'b8cms:template:changeStatus')]
    public function changeStatus(Request $request): Response
    {
        return parent::changeStatus($request);
    }

    #[Permission('启用模板', 'b8cms:template:activate')]
    public function activate(Request $request): Response
    {
        $id = (int) $request->post('id', 0);
        return $this->logic->activate($id) ? $this->success('启用成功') : $this->fail('启用失败');
    }
}
