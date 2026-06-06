<?php

namespace plugin\b8cms\app\admin\controller;

use plugin\b8cms\app\admin\logic\ContentLogic;
use plugin\b8cms\app\validate\ContentValidate;
use plugin\saiadmin\service\Permission;
use support\Request;
use support\Response;

class ContentController extends AbstractCrudController
{
    public function __construct()
    {
        $this->logic = new ContentLogic();
        $this->validate = new ContentValidate();
        parent::__construct();
    }

    protected function searchFields(): array
    {
        return [
            ['content_type', ''],
            ['lang_code', ''],
            ['title', ''],
            ['slug', ''],
            ['category', ''],
            ['status', ''],
        ];
    }

    #[Permission('内容列表', 'b8cms:content:index')]
    public function index(Request $request): Response
    {
        return parent::index($request);
    }

    #[Permission('内容读取', 'b8cms:content:read')]
    public function read(Request $request): Response
    {
        return parent::read($request);
    }

    #[Permission('内容添加', 'b8cms:content:save')]
    public function save(Request $request): Response
    {
        return parent::save($request);
    }

    #[Permission('内容修改', 'b8cms:content:update')]
    public function update(Request $request): Response
    {
        return parent::update($request);
    }

    #[Permission('内容删除', 'b8cms:content:destroy')]
    public function destroy(Request $request): Response
    {
        return parent::destroy($request);
    }

    #[Permission('内容状态', 'b8cms:content:changeStatus')]
    public function changeStatus(Request $request): Response
    {
        return parent::changeStatus($request);
    }
}
