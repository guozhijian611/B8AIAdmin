<?php

namespace plugin\b8cms\app\admin\controller;

use plugin\b8cms\app\admin\logic\CommentLogic;
use plugin\b8cms\app\validate\CommentValidate;
use plugin\saiadmin\service\Permission;
use support\Request;
use support\Response;

class CommentController extends AbstractCrudController
{
    public function __construct()
    {
        $this->logic = new CommentLogic();
        $this->validate = new CommentValidate();
        parent::__construct();
    }

    protected function searchFields(): array
    {
        return [
            ['keyword', ''],
            ['content_id', ''],
            ['status', ''],
            ['email', ''],
            ['ip', ''],
        ];
    }

    #[Permission('评论列表', 'b8cms:comment:index')]
    public function index(Request $request): Response
    {
        return parent::index($request);
    }

    #[Permission('评论读取', 'b8cms:comment:read')]
    public function read(Request $request): Response
    {
        return parent::read($request);
    }

    #[Permission('评论修改', 'b8cms:comment:update')]
    public function update(Request $request): Response
    {
        return parent::update($request);
    }

    #[Permission('评论删除', 'b8cms:comment:destroy')]
    public function destroy(Request $request): Response
    {
        return parent::destroy($request);
    }

    #[Permission('评论处理', 'b8cms:comment:handle')]
    public function handle(Request $request): Response
    {
        $data = [
            'id' => (int) $request->post('id', 0),
            'status' => (int) $request->post('status', 1),
            'block_reason' => trim((string) $request->post('block_reason', '')),
            'reviewed_by' => (int) (getCurrentInfo()['id'] ?? 0) ?: null,
            'reviewed_at' => date('Y-m-d H:i:s'),
        ];

        $this->validate('handle', $data);
        if ($data['status'] !== 3) {
            $data['block_reason'] = '';
        }

        $result = $this->logic->edit($data['id'], $data);
        return $result ? $this->success('处理成功') : $this->fail('处理失败');
    }
}
