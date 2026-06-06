<?php

namespace plugin\b8cms\app\admin\controller;

use plugin\saiadmin\basic\BaseController;
use support\Request;
use support\Response;

abstract class AbstractCrudController extends BaseController
{
    protected function searchFields(): array
    {
        return [];
    }

    public function index(Request $request): Response
    {
        $where = $request->more($this->searchFields());
        $query = $this->logic->search($where);
        $data = $this->logic->getList($query);
        return $this->success($data);
    }

    public function read(Request $request): Response
    {
        $id = (int) $request->input('id', 0);
        $model = $this->logic->read($id);
        $data = is_array($model) ? $model : $model->toArray();
        return $this->success($data);
    }

    public function save(Request $request): Response
    {
        $data = $request->post();
        $this->validate('save', $data);
        $result = $this->logic->add($data);
        return $result ? $this->success('添加成功') : $this->fail('添加失败');
    }

    public function update(Request $request): Response
    {
        $data = $request->post();
        $this->validate('update', $data);
        $result = $this->logic->edit((int) $data['id'], $data);
        return $result ? $this->success('修改成功') : $this->fail('修改失败');
    }

    public function destroy(Request $request): Response
    {
        $ids = $request->post('ids', []);
        if (empty($ids)) {
            return $this->fail('请选择要删除的数据');
        }

        $result = $this->logic->destroy($ids);
        return $result ? $this->success('删除成功') : $this->fail('删除失败');
    }

    public function changeStatus(Request $request): Response
    {
        $id = (int) $request->post('id', 0);
        $status = (int) $request->post('status', 1);
        $model = $this->logic->findOrEmpty($id);
        if ($model->isEmpty()) {
            return $this->fail('未查找到信息');
        }

        $result = $model->save(['status' => $status]);
        return $result ? $this->success('操作成功') : $this->fail('操作失败');
    }
}
