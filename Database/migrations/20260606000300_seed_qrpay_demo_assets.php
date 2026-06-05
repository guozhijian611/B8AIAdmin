<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class SeedQrpayDemoAssets extends AbstractMigration
{
    private const GROUP_TABLE = 'sa_system_config_group';
    private const CONFIG_TABLE = 'sa_system_config';
    private const QRPAY_GROUP = 'qrpay_config';
    private const ALIPAY_QRCODE = '/storage/20260606/7ab60d0efaf0f528384db5a3ea01aad28182fca5.JPG';
    private const WECHAT_QRCODE = '/storage/20260606/0cc2705353fef901a29974ab7ac27a7c58df965e.JPG';
    private const REMARK = '内置扫码支付演示收款码，可在后台替换为正式收款码';

    public function up(): void
    {
        if (!$this->hasTable(self::GROUP_TABLE) || !$this->hasTable(self::CONFIG_TABLE)) {
            return;
        }

        $groupId = $this->getGroupId();
        if (!$groupId) {
            return;
        }

        $this->seedQrcode($groupId, 'manual_scan_alipay_qrcode', self::ALIPAY_QRCODE);
        $this->seedQrcode($groupId, 'manual_scan_wechat_qrcode', self::WECHAT_QRCODE);
    }

    public function down(): void
    {
        if (!$this->hasTable(self::GROUP_TABLE) || !$this->hasTable(self::CONFIG_TABLE)) {
            return;
        }

        $groupId = $this->getGroupId();
        if (!$groupId) {
            return;
        }

        $this->clearSeededQrcode($groupId, 'manual_scan_alipay_qrcode', self::ALIPAY_QRCODE);
        $this->clearSeededQrcode($groupId, 'manual_scan_wechat_qrcode', self::WECHAT_QRCODE);
    }

    private function seedQrcode(int $groupId, string $key, string $path): void
    {
        $row = $this->getConfig($groupId, $key);
        if (!$row || !$this->canReplace((string)$row['value'], $path)) {
            return;
        }

        $this->execute(
            "UPDATE `" . self::CONFIG_TABLE . "`
            SET `value` = " . $this->quote($path) . ",
                `remark` = " . $this->quote(self::REMARK) . ",
                `update_time` = NOW()
            WHERE `id` = " . (int)$row['id']
        );
    }

    private function clearSeededQrcode(int $groupId, string $key, string $path): void
    {
        $row = $this->getConfig($groupId, $key);
        if (!$row || (string)$row['value'] !== $path || (string)$row['remark'] !== self::REMARK) {
            return;
        }

        $this->execute(
            "UPDATE `" . self::CONFIG_TABLE . "`
            SET `value` = '',
                `remark` = " . $this->quote($key === 'manual_scan_alipay_qrcode'
                    ? '扫码支付使用的支付宝收款二维码'
                    : '扫码支付使用的微信收款二维码') . ",
                `update_time` = NOW()
            WHERE `id` = " . (int)$row['id']
        );
    }

    private function canReplace(string $value, string $path): bool
    {
        if ($value === '' || $value === $path) {
            return true;
        }

        return str_contains($value, '127.0.0.1') && str_ends_with($value, $path);
    }

    private function getGroupId(): ?int
    {
        $row = $this->fetchRow(
            "SELECT `id` FROM `" . self::GROUP_TABLE . "`
            WHERE `code` = " . $this->quote(self::QRPAY_GROUP) . "
              AND `delete_time` IS NULL
            LIMIT 1"
        );

        return $row ? (int)$row['id'] : null;
    }

    private function getConfig(int $groupId, string $key): ?array
    {
        $row = $this->fetchRow(
            "SELECT `id`, `value`, `remark` FROM `" . self::CONFIG_TABLE . "`
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
