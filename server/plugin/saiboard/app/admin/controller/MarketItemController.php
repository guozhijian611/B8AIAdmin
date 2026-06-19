<?php

namespace plugin\saiboard\app\admin\controller;

use hg\apidoc\annotation as Apidoc;
use plugin\saiadmin\service\Permission;
use plugin\saiboard\app\admin\logic\MarketItemLogic;
use plugin\saiboard\app\validate\MarketItemValidate;
use support\Request;
use support\Response;

#[Apidoc\Group('SAI Board')]
#[Apidoc\Title('模板市场')]
class MarketItemController extends AbstractCrudController
{
    public function __construct()
    {
        $this->logic = new MarketItemLogic();
        $this->validate = new MarketItemValidate();
        parent::__construct();
    }

    protected function searchFields(): array
    {
        return [
            ['type', ''],
            ['name', ''],
            ['category', ''],
            ['is_public', ''],
            ['status', ''],
        ];
    }

    #[Apidoc\Title('模板市场列表')]
    #[Apidoc\Url('/app/saiboard/admin/MarketItem/index')]
    #[Apidoc\Method('GET')]
    #[Apidoc\Query('type', type: 'string', require: false, desc: '模板类型：screen/component')]
    #[Apidoc\Query('name', type: 'string', require: false, desc: '模板名称')]
    #[Apidoc\Query('category', type: 'string', require: false, desc: '模板分类')]
    #[Apidoc\Query('is_public', type: 'int', require: false, desc: '是否公开：1公开 2私有')]
    #[Apidoc\Query('status', type: 'int', require: false, desc: '状态：1启用 2停用')]
    #[Apidoc\Returned('list', type: 'array', desc: '模板摘要列表，不包含 content')]
    #[Permission('模板市场列表', 'saiboard:market_item:index')]
    public function index(Request $request): Response
    {
        return parent::index($request);
    }

    #[Apidoc\Title('模板市场读取')]
    #[Apidoc\Url('/app/saiboard/admin/MarketItem/read')]
    #[Apidoc\Method('GET')]
    #[Apidoc\Query('id', type: 'int', require: true, desc: '模板ID')]
    #[Apidoc\Returned('content', type: 'object', desc: '完整模板内容')]
    #[Permission('模板市场读取', 'saiboard:market_item:read')]
    public function read(Request $request): Response
    {
        return parent::read($request);
    }

    #[Apidoc\Title('模板市场添加')]
    #[Apidoc\Url('/app/saiboard/admin/MarketItem/save')]
    #[Apidoc\Method('POST')]
    #[Apidoc\Param('type', type: 'string', require: true, desc: '模板类型：screen/component')]
    #[Apidoc\Param('name', type: 'string', require: true, desc: '模板名称')]
    #[Apidoc\Param('category', type: 'string', require: false, desc: '模板分类')]
    #[Apidoc\Param('description', type: 'string', require: false, desc: '模板说明')]
    #[Apidoc\Param('content', type: 'object', require: true, desc: '模板内容，大屏模板为 layout，组件模板为 components')]
    #[Apidoc\Param('is_public', type: 'int', require: false, desc: '是否公开：1公开 2私有')]
    #[Apidoc\Param('status', type: 'int', require: false, desc: '状态：1启用 2停用')]
    #[Permission('模板市场添加', 'saiboard:market_item:save')]
    public function save(Request $request): Response
    {
        return parent::save($request);
    }

    #[Apidoc\Title('模板市场修改')]
    #[Apidoc\Url('/app/saiboard/admin/MarketItem/update')]
    #[Apidoc\Method('PUT')]
    #[Apidoc\Param('id', type: 'int', require: true, desc: '模板ID')]
    #[Apidoc\Param('type', type: 'string', require: true, desc: '模板类型：screen/component')]
    #[Apidoc\Param('name', type: 'string', require: true, desc: '模板名称')]
    #[Apidoc\Param('content', type: 'object', require: true, desc: '模板内容')]
    #[Apidoc\Param('is_public', type: 'int', require: true, desc: '是否公开：1公开 2私有')]
    #[Apidoc\Param('status', type: 'int', require: true, desc: '状态：1启用 2停用')]
    #[Permission('模板市场修改', 'saiboard:market_item:update')]
    public function update(Request $request): Response
    {
        return parent::update($request);
    }

    #[Apidoc\Title('模板市场删除')]
    #[Apidoc\Url('/app/saiboard/admin/MarketItem/destroy')]
    #[Apidoc\Method('DELETE')]
    #[Apidoc\Param('ids', type: 'array', require: true, desc: '模板ID列表')]
    #[Permission('模板市场删除', 'saiboard:market_item:destroy')]
    public function destroy(Request $request): Response
    {
        return parent::destroy($request);
    }

    #[Apidoc\Title('模板市场状态')]
    #[Apidoc\Url('/app/saiboard/admin/MarketItem/changeStatus')]
    #[Apidoc\Method('POST')]
    #[Apidoc\Param('id', type: 'int', require: true, desc: '模板ID')]
    #[Apidoc\Param('status', type: 'int', require: true, desc: '状态：1启用 2停用')]
    #[Permission('模板市场状态', 'saiboard:market_item:changeStatus')]
    public function changeStatus(Request $request): Response
    {
        $id = (int) $request->post('id', 0);
        $status = (int) $request->post('status', 1);
        if ($id <= 0) {
            return $this->fail('未查找到信息');
        }

        return $this->logic->changeStatus($id, $status)
            ? $this->success('操作成功')
            : $this->fail('操作失败');
    }

    #[Apidoc\Title('模板市场可用项')]
    #[Apidoc\Url('/app/saiboard/admin/MarketItem/options')]
    #[Apidoc\Method('GET')]
    #[Apidoc\Query('type', type: 'string', require: false, desc: '模板类型：screen/component')]
    #[Apidoc\Query('name', type: 'string', require: false, desc: '模板名称')]
    #[Apidoc\Query('category', type: 'string', require: false, desc: '模板分类')]
    #[Apidoc\Returned('items', type: 'array', desc: '启用状态的模板摘要列表，不包含 content')]
    #[Permission('模板市场列表', 'saiboard:market_item:index')]
    public function options(Request $request): Response
    {
        return $this->success($this->logic->options($request->all()));
    }
}
