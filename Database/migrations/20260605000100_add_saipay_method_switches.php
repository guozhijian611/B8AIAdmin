<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddSaipayMethodSwitches extends AbstractMigration
{
    private const GROUP_TABLE = 'sa_system_config_group';
    private const CONFIG_TABLE = 'sa_system_config';
    private const GROUP_CODE = 'saipay_config';
    private const REMARK = 'phinx:20260605000100_add_saipay_method_switches';

    public function up(): void
    {
        if (!$this->hasTable(self::GROUP_TABLE) || !$this->hasTable(self::CONFIG_TABLE)) {
            return;
        }

        if (!$this->getGroupId()) {
            $this->execute(
                "INSERT INTO `" . self::GROUP_TABLE . "` (`name`, `code`, `remark`, `created_by`, `updated_by`, `create_time`, `update_time`, `delete_time`)
                VALUES ('支付插件配置', '" . self::GROUP_CODE . "', '" . self::REMARK . "', 1, 1, NOW(), NOW(), NULL)"
            );
        }

        $this->insertSwitch('alipay_enabled', '支付宝支付', 100, '关闭后前端不展示支付宝，后端拒绝发起支付宝支付');
        $this->insertSwitch('wechat_enabled', '微信支付', 90, '关闭后前端不展示微信支付，后端拒绝发起微信支付');
    }

    public function down(): void
    {
        if (!$this->hasTable(self::GROUP_TABLE) || !$this->hasTable(self::CONFIG_TABLE)) {
            return;
        }

        $this->execute(
            "DELETE FROM `" . self::CONFIG_TABLE . "`
            WHERE `remark` LIKE '" . self::REMARK . "%'
              AND `value` = '1'
              AND `group_id` IN (
                SELECT `id` FROM `" . self::GROUP_TABLE . "`
                WHERE `code` = '" . self::GROUP_CODE . "'
              )"
        );

        $this->execute(
            "DELETE FROM `" . self::GROUP_TABLE . "`
            WHERE `code` = '" . self::GROUP_CODE . "'
              AND `remark` = '" . self::REMARK . "'
              AND NOT EXISTS (
                SELECT 1 FROM `" . self::CONFIG_TABLE . "`
                WHERE `" . self::CONFIG_TABLE . "`.`group_id` = `" . self::GROUP_TABLE . "`.`id`
                LIMIT 1
              )"
        );
    }

    private function insertSwitch(string $key, string $name, int $sort, string $remark): void
    {
        $groupId = $this->getGroupId();
        if (!$groupId || $this->configExists($groupId, $key)) {
            return;
        }

        $this->execute(
            "INSERT INTO `" . self::CONFIG_TABLE . "` (`group_id`, `key`, `value`, `name`, `input_type`, `config_select_data`, `sort`, `remark`, `created_by`, `updated_by`, `create_time`, `update_time`, `delete_time`)
            VALUES ({$groupId}, '{$key}', '1', '{$name}', 'switch', NULL, {$sort}, '" . self::REMARK . " | {$remark}', 1, 1, NOW(), NOW(), NULL)"
        );
    }

    private function getGroupId(): ?int
    {
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
