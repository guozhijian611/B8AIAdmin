<?php
// +----------------------------------------------------------------------
// | saiadmin [ saiadmin快速开发框架 ]
// +----------------------------------------------------------------------
// | Author: your name
// +----------------------------------------------------------------------
namespace plugin\saipay\app\api\logic;

use plugin\saipay\app\model\Order;
use plugin\saipay\service\ManualScanPaymentService;
use plugin\saipay\service\PayService;
use plugin\saiadmin\basic\think\BaseLogic;
use plugin\saiadmin\exception\ApiException;
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
        $order = $this->model->where('order_no', $order_no)->whereIn('pay_status', [2, 3])->findOrEmpty();
        if (!$order->isEmpty()) {
            $this->markPaid($order, $money, $trade_no, [
                'pay_amount' => (float)$money,
                'trade_no' => $trade_no,
                'payload' => $payload,
            ]);
        }
    }

    /**
     * 用户确认人工扫码已付款
     */
    public function confirmManualPaidByUser(string $orderNo, string $payChannel): void
    {
        if (!ManualScanPaymentService::qrcode($payChannel)) {
            throw new ApiException('请选择有效的扫码支付收款码');
        }

        $order = $this->model
            ->where('order_no', $orderNo)
            ->where('pay_method', PayService::CHANNEL_MANUAL_SCAN)
            ->where('pay_status', 2)
            ->findOrEmpty();

        if ($order->isEmpty()) {
            throw new ApiException('订单不存在或当前状态不支持确认付款');
        }

        ManualScanPaymentService::assertNoticeConfigured();

        $order->pay_status = 3;
        $order->pay_channel = $payChannel;
        $order->trade_no = 'manual_pending_' . $order->order_no;
        $order->save();

        ManualScanPaymentService::sendPaymentNotice($order);
    }

    /**
     * 管理员确认人工扫码到账
     */
    public function confirmManualPaidByAdmin(string $orderNo): void
    {
        $order = $this->model
            ->where('order_no', $orderNo)
            ->where('pay_method', PayService::CHANNEL_MANUAL_SCAN)
            ->where('pay_status', 3)
            ->findOrEmpty();

        if ($order->isEmpty()) {
            throw new ApiException('订单不存在或当前状态不支持确认到账');
        }

        $tradeNo = 'manual_scan_' . ($order->pay_channel ?: 'unknown') . '_' . $order->order_no;
        $this->markPaid($order, $order->order_price, $tradeNo, [
            'pay_amount' => (float)$order->order_price,
            'trade_no' => $tradeNo,
            'payload' => [
                'source' => 'manual_scan',
                'pay_channel' => $order->pay_channel,
                'confirmed_by' => 'admin',
            ],
        ]);
    }

    private function markPaid(Order $order, $money, string $tradeNo = '', array $context = []): void
    {
        $order->pay_status = 1;
        $order->pay_price = $money;
        if ($tradeNo !== '') {
            $order->trade_no = $tradeNo;
        }
        $order->pay_time = date('Y-m-d H:i:s');
        $order->save();

        $context['notify_time'] = $order->pay_time;
        $this->handleBusinessLogic($order, $context);
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
