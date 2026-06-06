<?php

namespace plugin\b8cms\app\admin\controller;

use plugin\b8cms\app\admin\logic\ContactMessageLogic;
use plugin\b8cms\app\validate\ContactMessageValidate;
use plugin\saiadmin\service\Permission;
use support\Request;
use support\Response;

class ContactMessageController extends AbstractCrudController
{
    public function __construct()
    {
        $this->logic = new ContactMessageLogic();
        $this->validate = new ContactMessageValidate();
        parent::__construct();
    }

    protected function searchFields(): array
    {
        return [
            ['keyword', ''],
            ['lang_code', ''],
            ['status', ''],
        ];
    }

    #[Permission('联系留言列表', 'b8cms:contact:index')]
    public function index(Request $request): Response
    {
        return parent::index($request);
    }

    #[Permission('联系留言读取', 'b8cms:contact:read')]
    public function read(Request $request): Response
    {
        return parent::read($request);
    }

    #[Permission('联系留言删除', 'b8cms:contact:destroy')]
    public function destroy(Request $request): Response
    {
        return parent::destroy($request);
    }

    #[Permission('联系留言处理', 'b8cms:contact:handle')]
    public function handle(Request $request): Response
    {
        $id = (int) $request->post('id', 0);
        $data = [
            'status' => (int) $request->post('status', 2),
            'reply_content' => (string) $request->post('reply_content', ''),
            'processed_at' => date('Y-m-d H:i:s'),
        ];
        $result = $this->logic->edit($id, $data);
        return $result ? $this->success('处理成功') : $this->fail('处理失败');
    }
}
