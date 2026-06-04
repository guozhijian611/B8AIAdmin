<?php
// +----------------------------------------------------------------------
// | saiadmin [ saiadmin快速开发框架 ]
// +----------------------------------------------------------------------
// | Author: your name
// +----------------------------------------------------------------------
namespace plugin\saipay\app\api\logic;

use plugin\saipay\app\model\Order;
use plugin\saiadmin\basic\think\BaseLogic;
use Webman\Event\Event;

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
     * 检查订单支付情况
     * @param $order_no
     * @return bool
     */
    public function checkOrder($order_no): bool
    {
        $order = $this->model->where('order_no', $order_no)->where('pay_status', 1)->findOrEmpty();
        if (!$order->isEmpty()) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 支付通知处理
     * @param $order_no
     * @param $money
     */
    public function notifyOrder($order_no, $money, string $trade_no = '', array $payload = []): void
    {
        $order = $this->model->where('order_no', $order_no)->where('pay_status', 2)->findOrEmpty();
        if (!$order->isEmpty()) {
            // 处理订单状态
            $order->pay_status = 1;
            $order->pay_price = $money;
            if ($trade_no !== '') {
                $order->trade_no = $trade_no;
            }
            $order->pay_time = date('Y-m-d H:i:s');
            $order->save();

            // 业务逻辑
            $this->handleBusinessLogic($order, [
                'pay_amount' => (float)$money,
                'trade_no' => $trade_no,
                'payload' => $payload,
                'notify_time' => $order->pay_time,
            ]);
        }
    }

    /**
     * 处理业务逻辑
     * @param $order
     */
    public function handleBusinessLogic($order, array $context = []): void
    {
        Event::emit('saipay.order.paid', [
            'order' => $order,
            'context' => $context,
        ]);
    }

}
