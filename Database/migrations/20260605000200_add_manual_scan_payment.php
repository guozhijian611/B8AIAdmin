<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddManualScanPayment extends AbstractMigration
{
    private const GROUP_TABLE = 'sa_system_config_group';
    private const CONFIG_TABLE = 'sa_system_config';
    private const MENU_TABLE = 'sa_system_menu';
    private const DICT_TYPE_TABLE = 'sa_system_dict_type';
    private const DICT_DATA_TABLE = 'sa_system_dict_data';
    private const GROUP_CODE = 'saipay_config';
    private const REMARK = 'phinx:20260605000200_add_manual_scan_payment';

    public function up(): void
    {
        $this->ensureSaipayConfigGroup();
        $this->insertSaipayConfigs();
        $this->insertManualScanDict();
        $this->insertConfirmPermission();
    }

    public function down(): void
    {
        if ($this->hasTable(self::CONFIG_TABLE) && $this->hasTable(self::GROUP_TABLE)) {
            $this->execute(
                "DELETE FROM `" . self::CONFIG_TABLE . "`
                WHERE `remark` LIKE '" . self::REMARK . "%'
                  AND `value` IN ('', '1', '2')
                  AND `group_id` IN (
                    SELECT `id` FROM `" . self::GROUP_TABLE . "`
                    WHERE `code` = '" . self::GROUP_CODE . "'
                  )"
            );
        }

        if ($this->hasTable(self::MENU_TABLE)) {
            $this->execute(
                "DELETE FROM `" . self::MENU_TABLE . "`
                WHERE `slug` = 'saipay:order:confirmManualPaid'
                  AND `remark` = '" . self::REMARK . "'"
            );
        }

        if ($this->hasTable(self::DICT_DATA_TABLE)) {
            $this->execute(
                "DELETE FROM `" . self::DICT_DATA_TABLE . "`
                WHERE `code` = 'saipay_method'
                  AND `value` = 'manual_scan'
                  AND `remark` = '" . self::REMARK . "'"
            );
        }
    }

    private function ensureSaipayConfigGroup(): void
    {
        if (!$this->hasTable(self::GROUP_TABLE)) {
            return;
        }

        if ($this->getGroupId()) {
            return;
        }

        $this->execute(
            "INSERT INTO `" . self::GROUP_TABLE . "` (`name`, `code`, `remark`, `created_by`, `updated_by`, `create_time`, `update_time`, `delete_time`)
            VALUES ('支付插件配置', '" . self::GROUP_CODE . "', '" . self::REMARK . "', 1, 1, NOW(), NOW(), NULL)"
        );
    }

    private function insertSaipayConfigs(): void
    {
        if (!$this->hasTable(self::GROUP_TABLE) || !$this->hasTable(self::CONFIG_TABLE)) {
            return;
        }

        $configs = [
            ['unipay_enabled', '银联支付', '2', 'switch', 80, '当前仅保留银联配置和回调，暂未接入统一发起支付'],
            ['manual_scan_enabled', '扫码支付', '1', 'switch', 70, '展示配置的微信/支付宝收款码，用户确认后管理员人工核对到账'],
            ['manual_scan_alipay_qrcode', '支付宝收款码', '', 'uploadImage', 60, '扫码支付使用的支付宝收款二维码'],
            ['manual_scan_wechat_qrcode', '微信收款码', '', 'uploadImage', 50, '扫码支付使用的微信收款二维码'],
            ['manual_scan_notice_emails', '管理员通知邮箱', '', 'textarea', 40, '扫码支付用户确认后通知的管理员邮箱，多个邮箱用逗号或换行分隔'],
        ];

        foreach ($configs as [$key, $name, $value, $inputType, $sort, $remark]) {
            $this->insertConfig((string)$key, (string)$name, (string)$value, (string)$inputType, (int)$sort, (string)$remark);
        }
    }

    private function insertManualScanDict(): void
    {
        if (!$this->hasTable(self::DICT_TYPE_TABLE) || !$this->hasTable(self::DICT_DATA_TABLE)) {
            return;
        }

        $type = $this->fetchRow(
            "SELECT `id` FROM `" . self::DICT_TYPE_TABLE . "`
            WHERE `code` = 'saipay_method'
              AND `delete_time` IS NULL
            LIMIT 1"
        );
        if (!$type) {
            return;
        }

        $exists = $this->fetchRow(
            "SELECT `id` FROM `" . self::DICT_DATA_TABLE . "`
            WHERE `code` = 'saipay_method'
              AND `value` = 'manual_scan'
              AND `delete_time` IS NULL
            LIMIT 1"
        );
        if ($exists) {
            return;
        }

        $typeId = (int)$type['id'];
        $this->execute(
            "INSERT INTO `" . self::DICT_DATA_TABLE . "` (`type_id`, `label`, `value`, `color`, `code`, `sort`, `status`, `remark`, `created_by`, `updated_by`, `create_time`, `update_time`, `delete_time`)
            VALUES ({$typeId}, '扫码支付', 'manual_scan', '#f59e0b', 'saipay_method', 90, 1, '" . self::REMARK . "', 1, 1, NOW(), NOW(), NULL)"
        );
    }

    private function insertConfirmPermission(): void
    {
        if (!$this->hasTable(self::MENU_TABLE)) {
            return;
        }

        $parent = $this->fetchRow(
            "SELECT `id` FROM `" . self::MENU_TABLE . "`
            WHERE `code` = 'saipay/order'
              AND `delete_time` IS NULL
            LIMIT 1"
        );
        if (!$parent) {
            return;
        }

        $exists = $this->fetchRow(
            "SELECT `id` FROM `" . self::MENU_TABLE . "`
            WHERE `slug` = 'saipay:order:confirmManualPaid'
              AND `delete_time` IS NULL
            LIMIT 1"
        );
        if ($exists) {
            return;
        }

        $parentId = (int)$parent['id'];
        $this->execute(
            "INSERT INTO `" . self::MENU_TABLE . "` (`parent_id`, `name`, `code`, `slug`, `type`, `path`, `component`, `method`, `icon`, `sort`, `link_url`, `is_iframe`, `is_keep_alive`, `is_hidden`, `is_fixed_tab`, `is_full_page`, `generate_id`, `generate_key`, `status`, `remark`, `created_by`, `updated_by`, `create_time`, `update_time`, `delete_time`)
            VALUES ({$parentId}, '确认到账', NULL, 'saipay:order:confirmManualPaid', 3, NULL, NULL, NULL, NULL, 95, '', 2, 2, 2, 2, 2, 0, '', 1, '" . self::REMARK . "', 1, 1, NOW(), NOW(), NULL)"
        );
    }

    private function insertConfig(string $key, string $name, string $value, string $inputType, int $sort, string $remark): void
    {
        $groupId = $this->getGroupId();
        if (!$groupId || $this->configExists($groupId, $key)) {
            return;
        }

        $this->execute(
            "INSERT INTO `" . self::CONFIG_TABLE . "` (`group_id`, `key`, `value`, `name`, `input_type`, `config_select_data`, `sort`, `remark`, `created_by`, `updated_by`, `create_time`, `update_time`, `delete_time`)
            VALUES ({$groupId}, '{$key}', '{$value}', '{$name}', '{$inputType}', NULL, {$sort}, '" . self::REMARK . " | {$remark}', 1, 1, NOW(), NOW(), NULL)"
        );
    }

    private function getGroupId(): ?int
    {
        if (!$this->hasTable(self::GROUP_TABLE)) {
            return null;
        }

        $row = $this->fetchRow(
            "SELECT `id` FROM `" . self::GROUP_TABLE . "`
            WHERE `code` = '" . self::GROUP_CODE . "'
              AND `delete_time` IS NULL
            LIMIT 1"
        );

        return $row ? (int)$row['id'] : null;
    }

    private function configExists(int $groupId, string $key): bool
    {
        $row = $this->fetchRow(
            "SELECT `id` FROM `" . self::CONFIG_TABLE . "`
            WHERE `group_id` = {$groupId}
              AND `key` = '{$key}'
              AND `delete_time` IS NULL
            LIMIT 1"
        );

        return (bool)$row;
    }
}
