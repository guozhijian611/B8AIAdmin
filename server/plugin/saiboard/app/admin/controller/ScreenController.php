<?php

namespace plugin\saiboard\app\admin\controller;

use hg\apidoc\annotation as Apidoc;
use plugin\saiadmin\service\Permission;
use plugin\saiboard\app\admin\logic\ScreenLogic;
use plugin\saiboard\app\service\RuntimeMetrics;
use plugin\saiboard\app\validate\ScreenValidate;
use support\Request;
use support\Response;

#[Apidoc\Group('SAI Board')]
#[Apidoc\Title('大屏管理')]
class ScreenController extends AbstractCrudController
{
    public function __construct(private readonly RuntimeMetrics $runtimeMetrics = new RuntimeMetrics())
    {
        $this->logic = new ScreenLogic();
        $this->validate = new ScreenValidate();
        parent::__construct();
    }

    protected function searchFields(): array
    {
        return [
            ['name', ''],
            ['code', ''],
            ['is_public', ''],
            ['status', ''],
        ];
    }

    #[Apidoc\Title('大屏列表')]
    #[Apidoc\Url('/app/saiboard/admin/Screen/index')]
    #[Apidoc\Method('GET')]
    #[Apidoc\Query('name', type: 'string', require: false, desc: '大屏名称')]
    #[Apidoc\Returned('total', type: 'int', desc: '总记录数')]
    #[Permission('大屏列表', 'saiboard:screen:index')]
    public function index(Request $request): Response
    {
        return parent::index($request);
    }

    #[Apidoc\Title('大屏读取')]
    #[Apidoc\Url('/app/saiboard/admin/Screen/read')]
    #[Apidoc\Method('GET')]
    #[Apidoc\Query('id', type: 'int', require: true, desc: '大屏ID')]
    #[Apidoc\Returned('id', type: 'int', desc: '大屏ID')]
    #[Apidoc\Returned('name', type: 'string', desc: '大屏名称')]
    #[Apidoc\Returned('draft_layout', type: 'object', desc: '草稿布局')]
    #[Apidoc\Returned('layout', type: 'object', desc: '已发布布局')]
    #[Apidoc\Returned('bg_config', type: 'object', desc: '背景和适配配置')]
    #[Permission('大屏读取', 'saiboard:screen:read')]
    public function read(Request $request): Response
    {
        return parent::read($request);
    }

    #[Apidoc\Title('大屏添加')]
    #[Apidoc\Url('/app/saiboard/admin/Screen/save')]
    #[Apidoc\Method('POST')]
    #[Apidoc\Param('name', type: 'string', require: true, desc: '大屏名称')]
    #[Apidoc\Param('bg_config', type: 'object', require: false, desc: '背景配置：color/theme/fit_mode/fit_align/image/image_fit')]
    #[Permission('大屏添加', 'saiboard:screen:save')]
    public function save(Request $request): Response
    {
        return parent::save($request);
    }

    #[Apidoc\Title('大屏修改')]
    #[Apidoc\Url('/app/saiboard/admin/Screen/update')]
    #[Apidoc\Method('PUT')]
    #[Apidoc\Param('id', type: 'int', require: true, desc: '大屏ID')]
    #[Apidoc\Param('bg_config', type: 'object', require: false, desc: '背景配置：color/theme/fit_mode/fit_align/image/image_fit')]
    #[Permission('大屏修改', 'saiboard:screen:update')]
    public function update(Request $request): Response
    {
        return parent::update($request);
    }

    #[Apidoc\Title('大屏删除')]
    #[Apidoc\Url('/app/saiboard/admin/Screen/destroy')]
    #[Apidoc\Method('DELETE')]
    #[Apidoc\Param('ids', type: 'array', require: true, desc: '大屏ID列表')]
    #[Permission('大屏删除', 'saiboard:screen:destroy')]
    public function destroy(Request $request): Response
    {
        return parent::destroy($request);
    }

    #[Apidoc\Title('大屏状态')]
    #[Apidoc\Url('/app/saiboard/admin/Screen/changeStatus')]
    #[Apidoc\Method('POST')]
    #[Apidoc\Param('id', type: 'int', require: true, desc: '大屏ID')]
    #[Apidoc\Param('status', type: 'int', require: true, desc: '状态，仅允许改为草稿2；发布请使用 publish')]
    #[Permission('大屏状态', 'saiboard:screen:changeStatus')]
    public function changeStatus(Request $request): Response
    {
        $status = (int) $request->post('status', 2);
        if ($status === 1) {
            return $this->fail('发布大屏请使用发布接口');
        }
        if ($status !== 2) {
            return $this->fail('状态值不正确');
        }

        return parent::changeStatus($request);
    }

    #[Apidoc\Title('保存大屏布局')]
    #[Apidoc\Url('/app/saiboard/admin/Screen/saveLayout')]
    #[Apidoc\Method('POST')]
    #[Apidoc\Param('id', type: 'int', require: true, desc: '大屏ID')]
    #[Apidoc\Param('layout', type: 'object', require: true, desc: '布局JSON')]
    #[Permission('保存大屏布局', 'saiboard:screen:saveLayout')]
    public function saveLayout(Request $request): Response
    {
        $id = (int) $request->post('id', 0);
        $layout = $request->post('layout', []);
        if ($id <= 0 || !is_array($layout)) {
            return $this->fail('参数错误');
        }

        return $this->logic->saveLayout($id, $layout)
            ? $this->success('保存成功')
            : $this->fail('保存失败');
    }

    #[Apidoc\Title('发布大屏')]
    #[Apidoc\Url('/app/saiboard/admin/Screen/publish')]
    #[Apidoc\Method('POST')]
    #[Apidoc\Param('id', type: 'int', require: true, desc: '大屏ID')]
    #[Permission('发布大屏', 'saiboard:screen:publish')]
    public function publish(Request $request): Response
    {
        $id = (int) $request->post('id', 0);
        if ($id <= 0) {
            return $this->fail('请选择大屏');
        }

        return $this->logic->publish($id) ? $this->success('发布成功') : $this->fail('发布失败');
    }

    #[Apidoc\Title('复制大屏')]
    #[Apidoc\Url('/app/saiboard/admin/Screen/copy')]
    #[Apidoc\Method('POST')]
    #[Apidoc\Param('id', type: 'int', require: true, desc: '大屏ID')]
    #[Apidoc\Returned('id', type: 'int', desc: '复制后的新大屏ID')]
    #[Permission('复制大屏', 'saiboard:screen:copy')]
    public function copy(Request $request): Response
    {
        $id = (int) $request->post('id', 0);
        if ($id <= 0) {
            return $this->fail('请选择大屏');
        }

        return $this->success(['id' => $this->logic->copy($id)], '复制成功');
    }

    #[Apidoc\Title('从数据表生成大屏')]
    #[Apidoc\Url('/app/saiboard/admin/Screen/generateFromTable')]
    #[Apidoc\Method('POST')]
    #[Apidoc\Param('datasource_id', type: 'int', require: true, desc: 'MySQL 数据源ID')]
    #[Apidoc\Param('table', type: 'string', require: true, desc: '数据表名')]
    #[Apidoc\Param('name', type: 'string', require: false, desc: '大屏名称')]
    #[Apidoc\Param('width', type: 'int', require: false, desc: '设计宽度')]
    #[Apidoc\Param('height', type: 'int', require: false, desc: '设计高度')]
    #[Apidoc\Param('chart_types', type: 'array', require: false, desc: '生成模块：count/trend/rank/distribution/status/raw')]
    #[Apidoc\Param('date_field', type: 'string', require: false, desc: '趋势时间字段')]
    #[Apidoc\Param('metric_field', type: 'string', require: false, desc: '聚合指标字段，留空为计数')]
    #[Apidoc\Param('label_field', type: 'string', require: false, desc: '排行维度字段，也作为状态矩阵名称字段')]
    #[Apidoc\Param('category_field', type: 'string', require: false, desc: '分布维度字段')]
    #[Apidoc\Param('status_field', type: 'string', require: false, desc: '状态矩阵状态字段，需与名称字段不同')]
    #[Apidoc\Param('order_field', type: 'string', require: false, desc: '明细排序字段')]
    #[Apidoc\Param('raw_fields', type: 'array', require: false, desc: '明细表字段，最多8个')]
    #[Apidoc\Returned('id', type: 'int', desc: '生成的大屏ID')]
    #[Apidoc\Returned('name', type: 'string', desc: '生成的大屏名称')]
    #[Apidoc\Returned('table', type: 'string', desc: '来源数据表')]
    #[Apidoc\Returned('chart_types', type: 'array', desc: '实际生成的模块类型')]
    #[Apidoc\Returned('template_ids', type: 'array', desc: '自动生成的查询模板ID列表')]
    #[Apidoc\Returned('component_count', type: 'int', desc: '自动生成的组件数量')]
    #[Permission('从数据表生成大屏', 'saiboard:screen:generateFromTable')]
    public function generateFromTable(Request $request): Response
    {
        return $this->success($this->logic->generateFromTable($request->post()), '生成成功');
    }

    #[Apidoc\Title('大屏版本列表')]
    #[Apidoc\Url('/app/saiboard/admin/Screen/versions')]
    #[Apidoc\Method('GET')]
    #[Apidoc\Query('id', type: 'int', require: true, desc: '大屏ID')]
    #[Permission('大屏版本列表', 'saiboard:screen:versions')]
    public function versions(Request $request): Response
    {
        $id = (int) $request->input('id', 0);
        if ($id <= 0) {
            return $this->fail('请选择大屏');
        }

        return $this->success($this->logic->versions($id));
    }

    #[Apidoc\Title('恢复大屏版本')]
    #[Apidoc\Url('/app/saiboard/admin/Screen/restoreVersion')]
    #[Apidoc\Method('POST')]
    #[Apidoc\Param('id', type: 'int', require: true, desc: '大屏ID')]
    #[Apidoc\Param('version_id', type: 'int', require: true, desc: '版本ID')]
    #[Permission('恢复大屏版本', 'saiboard:screen:restoreVersion')]
    public function restoreVersion(Request $request): Response
    {
        $id = (int) $request->post('id', 0);
        $versionId = (int) $request->post('version_id', 0);
        if ($id <= 0 || $versionId <= 0) {
            return $this->fail('参数错误');
        }

        return $this->logic->restoreVersion($id, $versionId)
            ? $this->success('已恢复为草稿')
            : $this->fail('恢复失败');
    }

    #[Apidoc\Title('删除大屏版本')]
    #[Apidoc\Url('/app/saiboard/admin/Screen/deleteVersion')]
    #[Apidoc\Method('DELETE')]
    #[Apidoc\Param('id', type: 'int', require: true, desc: '大屏ID')]
    #[Apidoc\Param('version_id', type: 'int', require: true, desc: '版本ID')]
    #[Permission('删除大屏版本', 'saiboard:screen:deleteVersion')]
    public function deleteVersion(Request $request): Response
    {
        $id = (int) $request->post('id', 0);
        $versionId = (int) $request->post('version_id', 0);
        if ($id <= 0 || $versionId <= 0) {
            return $this->fail('参数错误');
        }

        return $this->logic->deleteVersion($id, $versionId)
            ? $this->success('删除成功')
            : $this->fail('删除失败');
    }

    #[Apidoc\Title('大屏访问令牌列表')]
    #[Apidoc\Url('/app/saiboard/admin/Screen/tokens')]
    #[Apidoc\Method('GET')]
    #[Apidoc\Query('id', type: 'int', require: true, desc: '大屏ID')]
    #[Permission('大屏访问令牌列表', 'saiboard:screen:tokens')]
    public function tokens(Request $request): Response
    {
        $id = (int) $request->input('id', 0);
        if ($id <= 0) {
            return $this->fail('请选择大屏');
        }

        return $this->success($this->logic->tokens($id));
    }

    #[Apidoc\Title('创建大屏访问令牌')]
    #[Apidoc\Url('/app/saiboard/admin/Screen/createToken')]
    #[Apidoc\Method('POST')]
    #[Apidoc\Param('id', type: 'int', require: true, desc: '大屏ID')]
    #[Apidoc\Param('name', type: 'string', require: true, desc: '令牌名称')]
    #[Apidoc\Param('expire_time', type: 'string', require: false, desc: '过期时间')]
    #[Apidoc\Returned('token', type: 'string', desc: '明文令牌，仅本次返回')]
    #[Apidoc\Returned('row', type: 'object', desc: '令牌记录，不包含 token_hash')]
    #[Permission('创建大屏访问令牌', 'saiboard:screen:createToken')]
    public function createToken(Request $request): Response
    {
        $id = (int) $request->post('id', 0);
        if ($id <= 0) {
            return $this->fail('请选择大屏');
        }

        return $this->success($this->logic->createToken($id, $request->post()), '创建成功');
    }

    #[Apidoc\Title('重置大屏访问令牌')]
    #[Apidoc\Url('/app/saiboard/admin/Screen/resetToken')]
    #[Apidoc\Method('POST')]
    #[Apidoc\Param('id', type: 'int', require: true, desc: '大屏ID')]
    #[Apidoc\Param('token_id', type: 'int', require: true, desc: '令牌ID')]
    #[Apidoc\Returned('token', type: 'string', desc: '重置后的明文令牌，仅本次返回')]
    #[Apidoc\Returned('row', type: 'object', desc: '令牌记录，不包含 token_hash')]
    #[Permission('重置大屏访问令牌', 'saiboard:screen:resetToken')]
    public function resetToken(Request $request): Response
    {
        $id = (int) $request->post('id', 0);
        $tokenId = (int) $request->post('token_id', 0);
        if ($id <= 0 || $tokenId <= 0) {
            return $this->fail('参数错误');
        }

        return $this->success($this->logic->resetToken($id, $tokenId), '重置成功');
    }

    #[Apidoc\Title('大屏访问令牌状态')]
    #[Apidoc\Url('/app/saiboard/admin/Screen/changeTokenStatus')]
    #[Apidoc\Method('POST')]
    #[Apidoc\Param('id', type: 'int', require: true, desc: '大屏ID')]
    #[Apidoc\Param('token_id', type: 'int', require: true, desc: '令牌ID')]
    #[Apidoc\Param('status', type: 'int', require: true, desc: '状态 1启用 2停用')]
    #[Permission('大屏访问令牌状态', 'saiboard:screen:changeTokenStatus')]
    public function changeTokenStatus(Request $request): Response
    {
        $id = (int) $request->post('id', 0);
        $tokenId = (int) $request->post('token_id', 0);
        $status = (int) $request->post('status', 1);
        if ($id <= 0 || $tokenId <= 0) {
            return $this->fail('参数错误');
        }

        return $this->logic->changeTokenStatus($id, $tokenId, $status)
            ? $this->success('操作成功')
            : $this->fail('操作失败');
    }

    #[Apidoc\Title('删除大屏访问令牌')]
    #[Apidoc\Url('/app/saiboard/admin/Screen/deleteToken')]
    #[Apidoc\Method('DELETE')]
    #[Apidoc\Param('id', type: 'int', require: true, desc: '大屏ID')]
    #[Apidoc\Param('token_id', type: 'int', require: true, desc: '令牌ID')]
    #[Permission('删除大屏访问令牌', 'saiboard:screen:deleteToken')]
    public function deleteToken(Request $request): Response
    {
        $id = (int) $request->post('id', 0);
        $tokenId = (int) $request->post('token_id', 0);
        if ($id <= 0 || $tokenId <= 0) {
            return $this->fail('参数错误');
        }

        return $this->logic->deleteToken($id, $tokenId)
            ? $this->success('删除成功')
            : $this->fail('删除失败');
    }

    #[Apidoc\Title('大屏运行统计')]
    #[Apidoc\Url('/app/saiboard/admin/Screen/runtimeMetrics')]
    #[Apidoc\Method('GET')]
    #[Apidoc\Query('id', type: 'int', require: false, desc: '大屏ID，不传返回全局运行统计')]
    #[Apidoc\Returned('window', type: 'int', desc: '统计窗口秒数')]
    #[Apidoc\Returned('totals', type: 'object', desc: '全局运行统计')]
    #[Apidoc\Returned('screen', type: 'object', desc: '指定大屏运行统计')]
    #[Permission('大屏运行统计', 'saiboard:screen:index')]
    public function runtimeMetrics(Request $request): Response
    {
        $id = (int) $request->input('id', 0);
        if ($id > 0) {
            $this->logic->read($id);
        }

        return $this->success($this->runtimeMetrics->snapshot($id, $id > 0 ? null : $this->logic->visibleIds()));
    }
}
