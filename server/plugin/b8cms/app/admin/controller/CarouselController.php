<?php

namespace plugin\b8cms\app\admin\controller;

use plugin\b8cms\app\admin\logic\CarouselLogic;
use plugin\b8cms\app\validate\CarouselValidate;
use plugin\saiadmin\service\Permission;
use support\Request;
use support\Response;

class CarouselController extends AbstractCrudController
{
    public function __construct()
    {
        $this->logic = new CarouselLogic();
        $this->validate = new CarouselValidate();
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

    #[Permission('轮播图列表', 'b8cms:carousel:index')]
    public function index(Request $request): Response
    {
        return parent::index($request);
    }

    #[Permission('轮播图读取', 'b8cms:carousel:read')]
    public function read(Request $request): Response
    {
        return parent::read($request);
    }

    #[Permission('轮播图添加', 'b8cms:carousel:save')]
    public function save(Request $request): Response
    {
        return parent::save($request);
    }

    #[Permission('轮播图修改', 'b8cms:carousel:update')]
    public function update(Request $request): Response
    {
        return parent::update($request);
    }

    #[Permission('轮播图删除', 'b8cms:carousel:destroy')]
    public function destroy(Request $request): Response
    {
        return parent::destroy($request);
    }

    #[Permission('轮播图状态', 'b8cms:carousel:changeStatus')]
    public function changeStatus(Request $request): Response
    {
        return parent::changeStatus($request);
    }
}
