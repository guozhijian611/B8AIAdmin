<?php

namespace plugin\b8cms\app\admin\controller;

use plugin\b8cms\app\admin\logic\SiteSettingLogic;
use plugin\b8cms\app\validate\SiteSettingValidate;
use plugin\saiadmin\service\Permission;
use support\Request;
use support\Response;

class SiteSettingController extends AbstractCrudController
{
    public function __construct()
    {
        $this->logic = new SiteSettingLogic();
        $this->validate = new SiteSettingValidate();
        parent::__construct();
    }

    protected function searchFields(): array
    {
        return [
            ['setting_key', ''],
            ['group_key', ''],
            ['lang_code', ''],
            ['status', ''],
        ];
    }

    #[Permission('站点配置列表', 'b8cms:setting:index')]
    public function index(Request $request): Response
    {
        return parent::index($request);
    }

    #[Permission('站点配置读取', 'b8cms:setting:read')]
    public function read(Request $request): Response
    {
        return parent::read($request);
    }

    #[Permission('站点配置添加', 'b8cms:setting:save')]
    public function save(Request $request): Response
    {
        return parent::save($request);
    }

    #[Permission('站点配置修改', 'b8cms:setting:update')]
    public function update(Request $request): Response
    {
        return parent::update($request);
    }

    #[Permission('站点配置删除', 'b8cms:setting:destroy')]
    public function destroy(Request $request): Response
    {
        return parent::destroy($request);
    }

    #[Permission('站点配置状态', 'b8cms:setting:changeStatus')]
    public function changeStatus(Request $request): Response
    {
        return parent::changeStatus($request);
    }
}
