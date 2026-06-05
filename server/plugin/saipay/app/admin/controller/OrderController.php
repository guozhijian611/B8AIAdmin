<?php

namespace plugin\saipay\app\admin\controller;

use support\Request;
use support\Response;
use plugin\saiadmin\service\Permission;
use plugin\saiadmin\basic\BaseController;
use plugin\saipay\app\admin\logic\OrderLogic;

/**
 * 订单模块控制器
 */
class OrderController extends BaseController
{
    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->logic = new OrderLogic();
        parent::__construct();
    }

    /**
     * 订单列表
     * @param Request $request
     * @return Response
     */
    #[Permission('订单列表', 'saipay:order:index')]
    public function index(Request $request): Response
    {
        $where = $request->more([
            ['plugin', ''],
            ['order_no', ''],
            ['order_name', ''],
            ['pay_status', ''],
            ['pay_method', ''],
            ['create_time', ''],
        ]);
        $query = $this->logic->search($where);
        $query->order('create_time', 'desc');
        $data = $this->logic->getList($query);
        return $this->success($data);
    }

    /**
     * 读取数据
     * @param Request $request
     * @return Response
     */
    #[Permission('订单读取', 'saipay:order:read')]
    public function read(Request $request): Response
    {
        $id = $request->input('id', '');
        $model = $this->logic->read($id);
        if ($model) {
            $data = is_array($model) ? $model : $model->toArray();
            return $this->success($data);
        } else {
            return $this->fail('未查找到信息');
        }
    }

    /**
     * 刷新订单状态
     * @param Request $request
     * @return Response
     */
    #[Permission('订单刷新', 'saipay:order:refresh')]
    public function refresh(Request $request): Response
    {
        $order_no = $request->input('order_no', '');
        $result = $this->logic->refresh($order_no);
        if ($result) {
            return $this->success('刷新成功');
        } else {
            return $this->fail('刷新失败');
        }
    }

    /**
     * 管理员确认扫码支付到账
     * @param Request $request
     * @return Response
     */
    #[Permission('确认扫码支付到账', 'saipay:order:confirmManualPaid')]
    public function confirmManualPaid(Request $request): Response
    {
        $order_no = $request->post('order_no', '');
        if (empty($order_no)) {
            return $this->fail('请输入订单号');
        }

        $this->logic->confirmManualPaid($order_no);
        return $this->success('确认成功');
    }

    /**
     * 删除数据
     * @param Request $request
     * @return Response
     */
    #[Permission('订单删除', 'saipay:order:destroy')]
    public function destroy(Request $request): Response
    {
        $ids = $request->post('ids', '');
        if (empty($ids)) {
            return $this->fail('请选择要删除的数据');
        }
        $result = $this->logic->destroy($ids);
        if ($result) {
            return $this->success('删除成功');
        } else {
            return $this->fail('删除失败');
        }
    }

}
