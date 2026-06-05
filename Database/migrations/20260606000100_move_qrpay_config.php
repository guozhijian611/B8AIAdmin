<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class MoveQrpayConfig extends AbstractMigration
{
    private const GROUP_TABLE = 'sa_system_config_group';
    private const CONFIG_TABLE = 'sa_system_config';
    private const SAIPAY_GROUP = 'saipay_config';
    private const QRPAY_GROUP = 'qrpay_config';
    private const QRPAY_GROUP_NAME = '扫码支付配置';
    private const QRPAY_KEYS = [
        'manual_scan_alipay_qrcode',
        'manual_scan_wechat_qrcode',
        'manual_scan_notice_emails',
    ];

    private const CLEAN_REMARKS = [
        'alipay_enabled' => '关闭后前端不展示支付宝，后端拒绝发起支付宝支付',
        'wechat_enabled' => '关闭后前端不展示微信支付，后端拒绝发起微信支付',
        'unipay_enabled' => '当前仅保留银联配置和回调，暂未接入统一发起支付',
        'manual_scan_enabled' => '展示配置的微信/支付宝收款码，用户确认后管理员人工核对到账',
        'manual_scan_alipay_qrcode' => '扫码支付使用的支付宝收款二维码',
        'manual_scan_wechat_qrcode' => '扫码支付使用的微信收款二维码',
        'manual_scan_notice_emails' => '扫码支付用户确认后通知的管理员邮箱，多个邮箱用逗号或换行分隔',
    ];

    private const ROLLBACK_REMARKS = [
        'alipay_enabled' => 'phinx:20260605000100_add_saipay_method_switches | 关闭后前端不展示支付宝，后端拒绝发起支付宝支付',
        'wechat_enabled' => 'phinx:20260605000100_add_saipay_method_switches | 关闭后前端不展示微信支付，后端拒绝发起微信支付',
        'unipay_enabled' => 'phinx:20260605000200_add_manual_scan_payment | 当前仅保留银联配置和回调，暂未接入统一发起支付',
        'manual_scan_enabled' => 'phinx:20260605000200_add_manual_scan_payment | 展示配置的微信/支付宝收款码，用户确认后管理员人工核对到账',
        'manual_scan_alipay_qrcode' => 'phinx:20260605000200_add_manual_scan_payment | 扫码支付使用的支付宝收款二维码',
        'manual_scan_wechat_qrcode' => 'phinx:20260605000200_add_manual_scan_payment | 扫码支付使用的微信收款二维码',
        'manual_scan_notice_emails' => 'phinx:20260605000200_add_manual_scan_payment | 扫码支付用户确认后通知的管理员邮箱，多个邮箱用逗号或换行分隔',
    ];

    public function up(): void
    {
        if (!$this->hasTable(self::GROUP_TABLE) || !$this->hasTable(self::CONFIG_TABLE)) {
            return;
        }

        $this->ensureQrpayGroup();
        $this->moveQrpayConfigs(self::SAIPAY_GROUP, self::QRPAY_GROUP);
        $this->updateRemarks(self::CLEAN_REMARKS);
    }

    public function down(): void
    {
        if (!$this->hasTable(self::GROUP_TABLE) || !$this->hasTable(self::CONFIG_TABLE)) {
            return;
        }

        $this->moveQrpayConfigs(self::QRPAY_GROUP, self::SAIPAY_GROUP);
        $this->updateRemarks(self::ROLLBACK_REMARKS);
        $this->deleteEmptyQrpayGroup();
    }

    private function ensureQrpayGroup(): void
    {
        if ($this->getGroupId(self::QRPAY_GROUP)) {
            return;
        }

        $this->execute(
            "INSERT INTO `" . self::GROUP_TABLE . "` (`name`, `code`, `remark`, `created_by`, `updated_by`, `create_time`, `update_time`, `delete_time`)
            VALUES ('" . self::QRPAY_GROUP_NAME . "', '" . self::QRPAY_GROUP . "', '扫码支付收款码和管理员通知配置', 1, 1, NOW(), NOW(), NULL)"
        );
    }

    private function moveQrpayConfigs(string $fromGroupCode, string $toGroupCode): void
    {
        $fromGroupId = $this->getGroupId($fromGroupCode);
        $toGroupId = $this->getGroupId($toGroupCode);
        if (!$fromGroupId || !$toGroupId) {
            return;
        }

        foreach (self::QRPAY_KEYS as $key) {
            $source = $this->getConfig($fromGroupId, $key);
            if (!$source || $this->getConfig($toGroupId, $key)) {
                continue;
            }

            $this->execute(
                "UPDATE `" . self::CONFIG_TABLE . "`
                SET `group_id` = {$toGroupId}, `update_time` = NOW()
                WHERE `id` = " . (int)$source['id']
            );
        }
    }

    private function updateRemarks(array $remarks): void
    {
        foreach ($remarks as $key => $remark) {
            $this->execute(
                "UPDATE `" . self::CONFIG_TABLE . "`
                SET `remark` = " . $this->quote($remark) . ", `update_time` = NOW()
                WHERE `key` = " . $this->quote($key) . "
                  AND `delete_time` IS NULL"
            );
        }
    }

    private function deleteEmptyQrpayGroup(): void
    {
        $groupId = $this->getGroupId(self::QRPAY_GROUP);
        if (!$groupId) {
            return;
        }

        $config = $this->fetchRow(
            "SELECT `id` FROM `" . self::CONFIG_TABLE . "`
            WHERE `group_id` = {$groupId}
              AND `delete_time` IS NULL
            LIMIT 1"
        );
        if ($config) {
            return;
        }

        $this->execute(
            "DELETE FROM `" . self::GROUP_TABLE . "`
            WHERE `id` = {$groupId}
              AND `code` = '" . self::QRPAY_GROUP . "'
              AND `remark` = '扫码支付收款码和管理员通知配置'"
        );
    }

    private function getGroupId(string $code): ?int
    {
        $row = $this->fetchRow(
            "SELECT `id` FROM `" . self::GROUP_TABLE . "`
            WHERE `code` = " . $this->quote($code) . "
              AND `delete_time` IS NULL
            LIMIT 1"
        );

        return $row ? (int)$row['id'] : null;
    }

    private function getConfig(int $groupId, string $key): ?array
    {
        $row = $this->fetchRow(
            "SELECT `id` FROM `" . self::CONFIG_TABLE . "`
            WHERE `group_id` = {$groupId}
              AND `key` = " . $this->quote($key) . "
              AND `delete_time` IS NULL
            LIMIT 1"
        );

        return $row ?: null;
    }

    private function quote(string $value): string
    {
        return $this->getAdapter()->getConnection()->quote($value);
    }
}
