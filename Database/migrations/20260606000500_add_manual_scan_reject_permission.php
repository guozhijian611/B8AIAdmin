<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddManualScanRejectPermission extends AbstractMigration
{
    private const MENU_TABLE = 'sa_system_menu';
    private const SLUG = 'saipay:order:rejectManualPaid';
    private const REMARK = 'phinx:20260606000500_add_manual_scan_reject_permission';

    public function up(): void
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
            WHERE `slug` = '" . self::SLUG . "'
              AND `delete_time` IS NULL
            LIMIT 1"
        );
        if ($exists) {
            return;
        }

        $parentId = (int)$parent['id'];
        $this->execute(
            "INSERT INTO `" . self::MENU_TABLE . "` (`parent_id`, `name`, `code`, `slug`, `type`, `path`, `component`, `method`, `icon`, `sort`, `link_url`, `is_iframe`, `is_keep_alive`, `is_hidden`, `is_fixed_tab`, `is_full_page`, `generate_id`, `generate_key`, `status`, `remark`, `created_by`, `updated_by`, `create_time`, `update_time`, `delete_time`)
            VALUES ({$parentId}, '驳回付款', NULL, '" . self::SLUG . "', 3, NULL, NULL, NULL, NULL, 94, '', 2, 2, 2, 2, 2, 0, '', 1, '" . self::REMARK . "', 1, 1, NOW(), NOW(), NULL)"
        );
    }

    public function down(): void
    {
        if (!$this->hasTable(self::MENU_TABLE)) {
            return;
        }

        $this->execute(
            "DELETE FROM `" . self::MENU_TABLE . "`
            WHERE `slug` = '" . self::SLUG . "'
              AND `remark` = '" . self::REMARK . "'"
        );
    }
}
