<?php
namespace plugin\saipay\service;

use plugin\saiadmin\utils\Arr;
use plugin\saiadmin\exception\ApiException;
use plugin\saiadmin\app\logic\system\SystemConfigLogic;

/**
 * 统一支付服务
 */
class PayService
{
    private const CONFIG_GROUP = 'saipay_config';

    /**
     * 支付渠道
     */
    const CHANNEL_ALIPAY = 'alipay';
    const CHANNEL_WECHAT = 'wechat';
    const CHANNEL_UNIPAY = 'unipay';
    const CHANNEL_MANUAL_SCAN = 'manual_scan';

    /**
     * 支付类型
     */
    const TYPE_WEB = 'web';
    const TYPE_H5 = 'h5';
    const TYPE_SCAN = 'scan';
    const TYPE_APP = 'app';
    const TYPE_MINI = 'mini';
    const TYPE_MP = 'mp';

    /**
     * 发起支付
     * @param string $channel 支付渠道 (alipay, wechat)
     * @param string $type 支付类型 (web, h5, scan, app, mini, mp)
     * @param array $params 订单参数
     *                      - out_trade_no: 订单号
     *                      - total_amount: 金额
     *                      - subject: 标题
     *                      - quit_url: H5支付同步跳转地址
     *                      - openid: 微信支付openid (mini/mp/h5等可能需要)
     *                      - ...其他yansongda支持的参数
     * @param array $config 额外的支付配置 (可选，覆盖默认配置)
     * @return mixed 返回结果取决于支付类型 (array|string|Response)
     */
    public static function pay(string $channel, string $type, array $params, array $config = [])
    {
        self::assertPaymentMethodEnabled($channel);
        self::validateParams($params);

        switch ($channel) {
            case self::CHANNEL_ALIPAY:
                return self::payAlipay($type, $params, $config);
            case self::CHANNEL_WECHAT:
                return self::payWechat($type, $params, $config);
            case self::CHANNEL_MANUAL_SCAN:
                return self::payManualScan($type, $params);
            default:
                throw new ApiException('不支持的支付渠道: ' . $channel);
        }
    }

    /**
     * 获取支付方式列表
     */
    public static function paymentMethods(bool $onlyEnabled = true): array
    {
        $manualScanQrcodes = ManualScanPaymentService::qrcodes();
        $methods = [
            [
                'label' => '支付宝支付',
                'value' => self::CHANNEL_ALIPAY,
                'description' => '支持手机支付宝扫码支付',
                'icon' => 'icon-alipay',
                'theme' => 'blue',
                'enabled' => self::isPaymentMethodEnabled(self::CHANNEL_ALIPAY),
            ],
            [
                'label' => '微信支付',
                'value' => self::CHANNEL_WECHAT,
                'description' => '支持手机微信扫码支付',
                'icon' => 'icon-wechat',
                'theme' => 'green',
                'enabled' => self::isPaymentMethodEnabled(self::CHANNEL_WECHAT),
            ],
            [
                'label' => '扫码支付',
                'value' => self::CHANNEL_MANUAL_SCAN,
                'description' => '展示配置的支付宝/微信收款码，管理员人工确认到账',
                'icon' => 'icon-scan',
                'theme' => 'orange',
                'enabled' => self::isPaymentMethodEnabled(self::CHANNEL_MANUAL_SCAN),
                'manual' => true,
                'configured' => !empty($manualScanQrcodes),
                'qrcodes' => $manualScanQrcodes,
            ],
            [
                'label' => '银联支付',
                'value' => self::CHANNEL_UNIPAY,
                'description' => '当前仅保留配置和回调，暂未接入统一发起支付',
                'icon' => 'icon-bank-card',
                'theme' => 'red',
                'enabled' => false,
                'supported' => false,
            ],
        ];

        if (!$onlyEnabled) {
            return $methods;
        }

        return array_values(array_filter($methods, static function (array $method): bool {
            return $method['enabled'] && ($method['supported'] ?? true) && ($method['configured'] ?? true);
        }));
    }

    /**
     * 校验支付方式是否启用
     */
    public static function assertPaymentMethodEnabled(string $channel): void
    {
        if (!self::isPaymentMethodEnabled($channel)) {
            $method = self::getPaymentMethod($channel);
            $label = $method['label'] ?? $channel;
            throw new ApiException($label . '已关闭');
        }
    }

    protected static function getPaymentMethod(string $channel): ?array
    {
        foreach (self::paymentMethods(false) as $method) {
            if ($method['value'] === $channel) {
                return $method;
            }
        }

        return null;
    }

    protected static function isPaymentMethodEnabled(string $channel): bool
    {
        $method = $channel . '_enabled';
        $logic = new SystemConfigLogic();
        $config = $logic->getGroup(self::CONFIG_GROUP);
        $value = Arr::getConfigValue($config, $method);

        if ($value === null || $value === '') {
            return true;
        }

        return !in_array(strtolower((string)$value), ['0', '2', 'false', 'off'], true);
    }

    /**
     * 支付宝支付
     */
    protected static function payAlipay(string $type, array $params, array $config)
    {
        $service = AlipayService::getInstance($config);
        
        // 构造订单参数
        $order = [
            'out_trade_no' => $params['out_trade_no'],
            'total_amount' => $params['total_amount'],
            'subject' => $params['subject'],
        ];

        // 合并其他参数
        $order = array_merge($params, $order);
        
        $result = [
            'pay_method' => 'alipay',
            'pay_type' => $type,
            'order_no' => $order['out_trade_no']
        ];

        switch ($type) {
            case self::TYPE_WEB:
                $html = $service->web($order)->getBody()->getContents();
                $result['html'] = $html;
                return $result;
            case self::TYPE_H5:
                if (!empty($params['quit_url'])) {
                    $order['quit_url'] = $params['quit_url'];
                }
                $html = $service->h5($order)->getBody()->getContents();
                $result['html'] = $html;
                return $result;
            case self::TYPE_SCAN:
                $response = $service->scan($order);
                if ($response->code !== '10000') {
                    throw new ApiException('支付宝扫码支付失败: ' . $response->msg);
                }
                $result['pay_url'] = $response->qr_code;
                return $result;
            case self::TYPE_APP:
                return $service->app($order);
            case self::TYPE_MINI:
                return $service->mini($order);
            default:
                throw new ApiException('不支持的支付宝支付类型: ' . $type);
        }
    }

    /**
     * 微信支付
     */
    protected static function payWechat(string $type, array $params, array $config)
    {
        $service = WechatPayService::getInstance($config);

        // 构造订单参数
        $order = [
            'out_trade_no' => $params['out_trade_no'],
            'description' => $params['subject'],
            'amount' => [
                'total' => (int)($params['total_amount'] * 100), // 微信单位为分
            ]
        ];
        $order = array_merge($params, $order);

        $result = [
            'pay_method' => 'wechat',
            'pay_type' => $type,
            'order_no' => $order['out_trade_no']
        ];

        switch ($type) {
            case self::TYPE_WEB: // 微信没有web，通常指Native扫码或H5
            case self::TYPE_SCAN:
                $logic = new SystemConfigLogic();
                $wxpay_config = $logic->getGroup('wxpay_config');
                $type = Arr::getConfigValue($wxpay_config, 'type');
                if (!empty($type)) {
                    $order['_type'] = $type;
                }
                $resp = $service->scan($order);
                $result['pay_url'] = $resp['code_url'] ?? ''; // scan
                return $result;
                
            case self::TYPE_H5:
                $order['scene_info'] = [
                    'payer_client_ip' => $params['client_ip'],
                    'h5_info' => [
                        'type' => 'Wap',
                    ]
                ];
                $resp = $service->h5($order);
                $result['pay_url'] = $resp['h5_url'];
                return $result;
            case self::TYPE_APP:
                return $service->app($order);
            case self::TYPE_MINI:
                $order['payer'] = [
                    'openid' => $params['openid']
                ];
                $resp = $service->mini($order);
                $result['info'] = $resp;
                return $result;
            case self::TYPE_MP: // 公众号
                $order['payer'] = [
                    'openid' => $params['openid']
                ];
                $response = $service->mp($order);
                $result['info'] = $response;
                return $result;
            default:
                throw new ApiException('不支持的微信支付类型: ' . $type);
        }
    }

    /**
     * 人工扫码支付
     */
    protected static function payManualScan(string $type, array $params): array
    {
        if (!in_array($type, [self::TYPE_SCAN, self::CHANNEL_MANUAL_SCAN], true)) {
            throw new ApiException('扫码支付仅支持人工扫码类型');
        }

        ManualScanPaymentService::assertConfigured();

        return [
            'pay_method' => self::CHANNEL_MANUAL_SCAN,
            'pay_type' => self::TYPE_SCAN,
            'order_no' => $params['out_trade_no'],
            'manual' => true,
            'qrcodes' => ManualScanPaymentService::qrcodes(),
        ];
    }

    /**
     * 验证必要参数
     */
    protected static function validateParams(array $params)
    {
        if (empty($params['out_trade_no'])) {
            throw new ApiException('缺少参数: out_trade_no');
        }
        if (!isset($params['total_amount'])) {
            throw new ApiException('缺少参数: total_amount');
        }
        if (empty($params['subject'])) {
            throw new ApiException('缺少参数: subject');
        }
    }
}
