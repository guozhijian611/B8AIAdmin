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
    #[Permission('大屏列表', 'saiboard:screen:index')]
    public function index(Request $request): Response
    {
        return parent::index($request);
    }

    #[Apidoc\Title('大屏读取')]
    #[Apidoc\Url('/app/saiboard/admin/Screen/read')]
    #[Apidoc\Method('GET')]
    #[Apidoc\Query('id', type: 'int', require: true, desc: '大屏ID')]
    #[Permission('大屏读取', 'saiboard:screen:read')]
    public function read(Request $request): Response
    {
        return parent::read($request);
    }

    #[Apidoc\Title('大屏添加')]
    #[Apidoc\Url('/app/saiboard/admin/Screen/save')]
    #[Apidoc\Method('POST')]
    #[Apidoc\Param('name', type: 'string', require: true, desc: '大屏名称')]
    #[Apidoc\Param('bg_config', type: 'object', require: false, desc: '背景配置：color/theme/fit_mode/image/image_fit')]
    #[Permission('大屏添加', 'saiboard:screen:save')]
    public function save(Request $request): Response
    {
        return parent::save($request);
    }

    #[Apidoc\Title('大屏修改')]
    #[Apidoc\Url('/app/saiboard/admin/Screen/update')]
    #[Apidoc\Method('PUT')]
    #[Apidoc\Param('id', type: 'int', require: true, desc: '大屏ID')]
    #[Apidoc\Param('bg_config', type: 'object', require: false, desc: '背景配置：color/theme/fit_mode/image/image_fit')]
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
    #[Permission('大屏状态', 'saiboard:screen:changeStatus')]
    public function changeStatus(Request $request): Response
    {
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
    #[Permission('复制大屏', 'saiboard:screen:copy')]
    public function copy(Request $request): Response
    {
        $id = (int) $request->post('id', 0);
        if ($id <= 0) {
            return $this->fail('请选择大屏');
        }

        return $this->success(['id' => $this->logic->copy($id)], '复制成功');
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

    #[Apidoc\Title('大屏运行统计')]
    #[Apidoc\Url('/app/saiboard/admin/Screen/runtimeMetrics')]
    #[Apidoc\Method('GET')]
    #[Apidoc\Query('id', type: 'int', require: false, desc: '大屏ID，不传返回全局运行统计')]
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
