<?php

namespace plugin\b8cms\app\admin\controller;

use plugin\b8cms\app\admin\logic\NavigationLogic;
use plugin\b8cms\app\validate\NavigationValidate;
use plugin\saiadmin\service\Permission;
use support\Request;
use support\Response;

class NavigationController extends AbstractCrudController
{
    public function __construct()
    {
        $this->logic = new NavigationLogic();
        $this->validate = new NavigationValidate();
        parent::__construct();
    }

    protected function searchFields(): array
    {
        return [
            ['lang_code', ''],
            ['position', ''],
            ['title', ''],
            ['status', ''],
        ];
    }

    #[Permission('导航列表', 'b8cms:navigation:index')]
    public function index(Request $request): Response
    {
        return parent::index($request);
    }

    #[Permission('导航读取', 'b8cms:navigation:read')]
    public function read(Request $request): Response
    {
        return parent::read($request);
    }

    #[Permission('导航添加', 'b8cms:navigation:save')]
    public function save(Request $request): Response
    {
        return parent::save($request);
    }

    #[Permission('导航修改', 'b8cms:navigation:update')]
    public function update(Request $request): Response
    {
        return parent::update($request);
    }

    #[Permission('导航删除', 'b8cms:navigation:destroy')]
    public function destroy(Request $request): Response
    {
        return parent::destroy($request);
    }

    #[Permission('导航状态', 'b8cms:navigation:changeStatus')]
    public function changeStatus(Request $request): Response
    {
        return parent::changeStatus($request);
    }
}
