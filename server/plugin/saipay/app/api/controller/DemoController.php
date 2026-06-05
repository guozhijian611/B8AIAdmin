<?php
namespace plugin\saipay\app\api\controller;

use hg\apidoc\annotation as Apidoc;
use support\Request;
use support\Response;
use plugin\saipay\app\api\logic\OrderLogic;
use plugin\saipay\service\PayService;
use plugin\saipay\app\model\Order;
use plugin\saiadmin\basic\OpenController;

/**
 * 支付使用案例控制器
 */
#[Apidoc\Group('支付插件')]
#[Apidoc\Title('支付使用案例')]
class DemoController extends OpenController
{
    /**
     * 可用支付方式
     */
    #[Apidoc\Title('可用支付方式')]
    #[Apidoc\Url('/app/saipay/api/demo/paymentMethods')]
    #[Apidoc\Method('GET')]
    #[Apidoc\Returned('label', type: 'string', desc: '支付方式名称')]
    #[Apidoc\Returned('value', type: 'string', desc: '支付方式标识')]
    #[Apidoc\Returned('description', type: 'string', desc: '支付方式说明')]
    #[Apidoc\Returned('enabled', type: 'boolean', desc: '是否启用')]
    public function paymentMethods(): Response
    {
        return $this->success(PayService::paymentMethods());
    }

    /**
     * 支付宝扫码支付示例
     */
    public function alipayScan(): Response
    {
        PayService::assertPaymentMethodEnabled(PayService::CHANNEL_ALIPAY);

        // 订单信息
        $orderData = [
            'order_no' => 'ALI_SCAN_' . uuid(), // 订单号
            'order_name' => '支付宝扫码测试', // 订单名称
            'order_price' => 0.01, // 订单金额
            'pay_price' => 0.00, // 支付金额
            'remark' => NULL,
            'pay_method' => PayService::CHANNEL_ALIPAY, // 支付方式
            'pay_type' => PayService::TYPE_SCAN, // 支付类型
            'pay_status' => 2, // 未支付
            'order_status' => 1, // 已下单
            'plugin' => 'saipay', // 插件名称
            'order_id' => 0, // 关联订单
            'member_id' => 0 // 关联用户
        ];
        // 创建订单
        $model = Order::create($orderData);
        if (!$model) {
            return $this->fail('订单创建失败');
        }

        $params = [
            'out_trade_no' => $orderData['order_no'],
            'total_amount' => $orderData['order_price'],
            'subject' => $orderData['order_name']
        ];
        $result = PayService::pay(PayService::CHANNEL_ALIPAY, PayService::TYPE_SCAN, $params);
        $result['order_price'] = $orderData['order_price'];
        $result['pay_method'] = $orderData['pay_method'];
        $result['pay_url_expire'] = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        // 保存支付二维码和过期时间
        $model->pay_url = $result['pay_url'];
        $model->pay_url_expire = $result['pay_url_expire'];
        $model->save();
        
        return $this->success($result);
    }

    /**
     * 微信扫码支付示例
     */
    public function wechatScan(): Response
    {
        PayService::assertPaymentMethodEnabled(PayService::CHANNEL_WECHAT);

        // 订单信息
        $orderData = [
            'order_no' => 'WE_SCAN_' . uuid(), // 订单号
            'order_name' => '微信扫码测试', // 订单名称
            'order_price' => 0.01, // 订单金额
            'pay_price' => 0.00, // 支付金额
            'remark' => NULL,
            'pay_method' => PayService::CHANNEL_WECHAT, // 支付方式
            'pay_type' => PayService::TYPE_SCAN, // 支付类型
            'pay_status' => 2, // 未支付
            'order_status' => 1, // 已下单
            'plugin' => 'saipay', // 插件名称
            'order_id' => 0, // 关联订单
            'member_id' => 0 // 关联用户
        ];
        // 创建订单
        $model = Order::create($orderData);
        if (!$model) {
            return $this->fail('订单创建失败');
        }

        $params = [
            'out_trade_no' => $orderData['order_no'],
            'total_amount' => $orderData['order_price'],
            'subject' => $orderData['order_name']
        ];
        $result = PayService::pay(PayService::CHANNEL_WECHAT, PayService::TYPE_SCAN, $params);
        $result['order_price'] = $orderData['order_price'];
        $result['pay_method'] = $orderData['pay_method'];
        $result['pay_type'] = $orderData['pay_type'];
        $result['pay_url_expire'] = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        // 保存支付二维码和过期时间
        $model->pay_url = $result['pay_url'];
        $model->pay_url_expire = $result['pay_url_expire'];
        $model->save();
        
        return $this->success($result);
    }

    /**
     * 扫码支付示例
     */
    #[Apidoc\Title('扫码支付示例')]
    #[Apidoc\Url('/app/saipay/api/demo/manualScan')]
    #[Apidoc\Method('GET')]
    #[Apidoc\Returned('order_no', type: 'string', desc: '订单号')]
    #[Apidoc\Returned('pay_method', type: 'string', desc: '支付方式')]
    #[Apidoc\Returned('qrcodes', type: 'array', desc: '收款码列表')]
    public function manualScan(): Response
    {
        PayService::assertPaymentMethodEnabled(PayService::CHANNEL_MANUAL_SCAN);

        $orderData = [
            'order_no' => 'MANUAL_SCAN_' . uuid(),
            'order_name' => '扫码支付测试',
            'order_price' => 0.01,
            'pay_price' => 0.00,
            'remark' => NULL,
            'pay_method' => PayService::CHANNEL_MANUAL_SCAN,
            'pay_type' => PayService::TYPE_SCAN,
            'pay_status' => 2,
            'order_status' => 1,
            'plugin' => 'saipay',
            'order_id' => 0,
            'member_id' => 0
        ];
        $model = Order::create($orderData);
        if (!$model) {
            return $this->fail('订单创建失败');
        }

        $params = [
            'out_trade_no' => $orderData['order_no'],
            'total_amount' => $orderData['order_price'],
            'subject' => $orderData['order_name']
        ];
        $result = PayService::pay(PayService::CHANNEL_MANUAL_SCAN, PayService::TYPE_SCAN, $params);
        $result['order_price'] = $orderData['order_price'];
        $result['pay_method'] = $orderData['pay_method'];
        $result['pay_type'] = $orderData['pay_type'];

        return $this->success($result);
    }

    /**
     * 继续支付
     */
    public function payOrder(Request $result): Response
    {
        $data = $result->post();
        $order = Order::where('order_no', $data['order_no'])->findOrEmpty();
        if ($order->isEmpty()) {
            return $this->fail('订单信息不存在');
        }
        if ($order->pay_status === 1) {
            return $this->fail('订单已支付');
        }
        // 之前已经生成过订单, 且未过期, 直接返回支付链接
        $pay_method = $data['pay_method'] ?? $order->pay_method;
        $pay_type = $data['pay_type'] ?? $order->pay_type;
        PayService::assertPaymentMethodEnabled($pay_method);

        if ($pay_method === $order->pay_method) {
            if (!empty($order->pay_url)) {
                if (time() <= strtotime($order->pay_url_expire)) {
                    $result = [
                        'order_price' => $order->order_price,
                        'pay_method' => $order->pay_method,
                        'pay_type' => $order->pay_type,
                        'order_no' => $order->order_no,
                        'pay_url' => $order->pay_url,
                        'pay_url_expire' => $order->pay_url_expire,
                    ];
                    return $this->success($result);
                }
            }
        }
        // 更换了支付方式或者订单支付链接过期，重新拉起支付
        $params = [
            'out_trade_no' => $order->order_no,
            'total_amount' => $order->order_price,
            'subject' => $order->order_name
        ];
        $result = PayService::pay($pay_method, $pay_type, $params);
        $result['order_price'] = $order->order_price;
        $result['pay_method'] = $pay_method;
        $result['pay_type'] = $pay_type;
        if ($pay_method === PayService::CHANNEL_MANUAL_SCAN) {
            $order->pay_method = $pay_method;
            $order->pay_type = $pay_type;
            $order->pay_url = '';
            $order->pay_url_expire = NULL;
            $order->save();

            return $this->success($result);
        }

        $result['pay_url_expire'] = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        // 保存支付二维码和过期时间
        $order->pay_method = $pay_method;
        $order->pay_type = $pay_type;
        $order->pay_url = $result['pay_url'];
        $order->pay_url_expire = $result['pay_url_expire'];
        $order->save();
        
        return $this->success($result);
    }

    /**
     * 用户确认扫码支付已付款
     */
    #[Apidoc\Title('用户确认扫码支付已付款')]
    #[Apidoc\Url('/app/saipay/api/demo/confirmManualPaid')]
    #[Apidoc\Method('POST')]
    #[Apidoc\Param('order_no', type: 'string', require: true, desc: '订单号')]
    public function confirmManualPaid(Request $request): Response
    {
        $orderNo = (string)$request->post('order_no', '');
        if ($orderNo === '') {
            return $this->fail('请输入订单号');
        }

        (new OrderLogic())->confirmManualPaidByUser($orderNo);
        return $this->success('已提交付款确认，请等待管理员核对到账');
    }
}
