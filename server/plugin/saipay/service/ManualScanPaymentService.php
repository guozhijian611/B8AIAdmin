<?php
namespace plugin\saipay\service;

use plugin\saipay\app\model\Order;
use plugin\saiadmin\app\logic\system\SystemConfigLogic;
use plugin\saiadmin\exception\ApiException;
use plugin\saiadmin\service\EmailService;
use plugin\saiadmin\utils\Arr;

/**
 * 人工扫码支付服务
 */
class ManualScanPaymentService
{
    private const CONFIG_GROUP = 'qrpay_config';

    public static function qrcodes(): array
    {
        $config = self::config();
        $qrcodes = [];

        $alipayQrcode = trim((string)Arr::getConfigValue($config, 'manual_scan_alipay_qrcode'));
        if ($alipayQrcode !== '') {
            $qrcodes[] = [
                'label' => '支付宝收款码',
                'method' => PayService::CHANNEL_ALIPAY,
                'image' => $alipayQrcode,
            ];
        }

        $wechatQrcode = trim((string)Arr::getConfigValue($config, 'manual_scan_wechat_qrcode'));
        if ($wechatQrcode !== '') {
            $qrcodes[] = [
                'label' => '微信收款码',
                'method' => PayService::CHANNEL_WECHAT,
                'image' => $wechatQrcode,
            ];
        }

        return $qrcodes;
    }

    public static function assertConfigured(): void
    {
        if (empty(self::qrcodes())) {
            throw new ApiException('请先配置扫码支付收款码');
        }
    }

    public static function assertNoticeConfigured(): void
    {
        if (empty(self::noticeEmails())) {
            throw new ApiException('请先配置扫码支付管理员通知邮箱');
        }
    }

    public static function sendPaymentNotice(Order $order): void
    {
        $emails = self::noticeEmails();
        if (empty($emails)) {
            throw new ApiException('请先配置扫码支付管理员通知邮箱');
        }

        $subject = '扫码支付待确认：' . $order->order_no;
        $content = self::buildNoticeContent($order);
        foreach ($emails as $email) {
            EmailService::sendByTemplate($email, $subject, $content);
        }
    }

    private static function config(): array
    {
        return (new SystemConfigLogic())->getGroup(self::CONFIG_GROUP);
    }

    private static function noticeEmails(): array
    {
        $config = self::config();
        $value = (string)Arr::getConfigValue($config, 'manual_scan_notice_emails');
        $emails = preg_split('/[\s,;，；]+/', $value, -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique(array_filter($emails ?: [], static function (string $email): bool {
            return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
        })));
    }

    private static function buildNoticeContent(Order $order): string
    {
        $rows = [
            '订单号' => $order->order_no,
            '订单名称' => $order->order_name,
            '订单金额' => $order->order_price,
            '支付方式' => '扫码支付',
            '应用插件' => $order->plugin,
            '关联订单ID' => $order->order_id,
            '会员ID' => $order->member_id,
            '下单时间' => $order->create_time,
            '用户确认时间' => date('Y-m-d H:i:s'),
        ];

        $html = '<h2>扫码支付待管理员确认</h2><table border="1" cellpadding="8" cellspacing="0">';
        foreach ($rows as $label => $value) {
            $html .= '<tr><th align="left">' . htmlspecialchars((string)$label) . '</th><td>'
                . htmlspecialchars((string)($value ?? '')) . '</td></tr>';
        }
        $html .= '</table><p>请登录后台订单列表核对到账后点击“确认到账”。</p>';

        return $html;
    }
}
