<?php
// +----------------------------------------------------------------------
// | saiadmin [ saiadmin快速开发框架 ]
// +----------------------------------------------------------------------
// | Author: your name
// +----------------------------------------------------------------------
namespace plugin\saipay\app\admin\logic;

use plugin\saipay\app\api\logic\OrderLogic as ApiOrderLogic;
use plugin\saipay\app\model\Order;
use plugin\saiadmin\basic\think\BaseLogic;
use plugin\saiadmin\exception\ApiException;

/**
 * 订单记录逻辑层
 */
class OrderLogic extends BaseLogic
{
    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->model = new Order();
    }

    /**
     * 刷新订单状态
     * @param $order_no
     * @return bool
     */
    public function refresh($order_no)
    {
        $order = $this->model->where('order_no', $order_no)->where('pay_status', 1)->findOrEmpty();
        if (!$order->isEmpty()) {
            // 处理订单状态
            $logic = new ApiOrderLogic();
            $logic->handleBusinessLogic($order);
            return true;
        }
        return false;
    }

    /**
     * 管理员确认扫码支付到账
     */
    public function confirmManualPaid(string $orderNo): void
    {
        $logic = new ApiOrderLogic();
        $logic->confirmManualPaidByAdmin($orderNo);
    }

    /**
     * 管理员驳回扫码支付付款确认
     */
    public function rejectManualPaid(string $orderNo, string $remark): void
    {
        $logic = new ApiOrderLogic();
        $logic->rejectManualPaidByAdmin($orderNo, $remark);
    }

}
