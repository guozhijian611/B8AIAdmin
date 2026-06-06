<?php

namespace plugin\b8cms\app\admin\controller;

use plugin\b8cms\app\admin\logic\CommentFilterLogic;
use plugin\b8cms\app\validate\CommentFilterValidate;
use plugin\saiadmin\service\Permission;
use support\Request;
use support\Response;

class CommentFilterController extends AbstractCrudController
{
    public function __construct()
    {
        $this->logic = new CommentFilterLogic();
        $this->validate = new CommentFilterValidate();
        parent::__construct();
    }

    protected function searchFields(): array
    {
        return [
            ['rule_type', ''],
            ['match_type', ''],
            ['value', ''],
            ['status', ''],
        ];
    }

    #[Permission('屏蔽规则列表', 'b8cms:comment-filter:index')]
    public function index(Request $request): Response
    {
        return parent::index($request);
    }

    #[Permission('屏蔽规则读取', 'b8cms:comment-filter:read')]
    public function read(Request $request): Response
    {
        return parent::read($request);
    }

    #[Permission('屏蔽规则添加', 'b8cms:comment-filter:save')]
    public function save(Request $request): Response
    {
        return parent::save($request);
    }

    #[Permission('屏蔽规则修改', 'b8cms:comment-filter:update')]
    public function update(Request $request): Response
    {
        return parent::update($request);
    }

    #[Permission('屏蔽规则删除', 'b8cms:comment-filter:destroy')]
    public function destroy(Request $request): Response
    {
        return parent::destroy($request);
    }

    #[Permission('屏蔽规则状态', 'b8cms:comment-filter:changeStatus')]
    public function changeStatus(Request $request): Response
    {
        return parent::changeStatus($request);
    }
}
