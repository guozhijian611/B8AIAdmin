<?php

namespace plugin\b8cms\app\admin\controller;

use plugin\b8cms\app\admin\logic\LanguageLogic;
use plugin\b8cms\app\validate\LanguageValidate;
use plugin\saiadmin\service\Permission;
use support\Request;
use support\Response;

class LanguageController extends AbstractCrudController
{
    public function __construct()
    {
        $this->logic = new LanguageLogic();
        $this->validate = new LanguageValidate();
        parent::__construct();
    }

    protected function searchFields(): array
    {
        return [
            ['code', ''],
            ['name', ''],
            ['status', ''],
        ];
    }

    #[Permission('语言列表', 'b8cms:language:index')]
    public function index(Request $request): Response
    {
        return parent::index($request);
    }

    #[Permission('语言读取', 'b8cms:language:read')]
    public function read(Request $request): Response
    {
        return parent::read($request);
    }

    #[Permission('语言添加', 'b8cms:language:save')]
    public function save(Request $request): Response
    {
        return parent::save($request);
    }

    #[Permission('语言修改', 'b8cms:language:update')]
    public function update(Request $request): Response
    {
        return parent::update($request);
    }

    #[Permission('语言删除', 'b8cms:language:destroy')]
    public function destroy(Request $request): Response
    {
        return parent::destroy($request);
    }

    #[Permission('语言状态', 'b8cms:language:changeStatus')]
    public function changeStatus(Request $request): Response
    {
        return parent::changeStatus($request);
    }

    #[Permission('设为默认语言', 'b8cms:language:setDefault')]
    public function setDefault(Request $request): Response
    {
        $id = (int) $request->post('id', 0);
        return $this->logic->setDefault($id) ? $this->success('设置成功') : $this->fail('设置失败');
    }
}
