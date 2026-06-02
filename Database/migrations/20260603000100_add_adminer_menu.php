<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddAdminerMenu extends AbstractMigration
{
    private const REMARK = 'phinx:20260603000100_add_adminer_menu';

    public function up(): void
    {
        $this->execute(
            "INSERT INTO `sa_system_menu` (`parent_id`, `name`, `code`, `slug`, `type`, `path`, `component`, `method`, `icon`, `sort`, `link_url`, `is_iframe`, `is_keep_alive`, `is_hidden`, `is_fixed_tab`, `is_full_page`, `generate_id`, `generate_key`, `status`, `remark`, `created_by`, `updated_by`, `create_time`, `update_time`, `delete_time`)
            SELECT `id`, 'Adminer 数据库管理', 'Adminer', '', 2, 'adminer', '/safeguard/adminer', NULL, 'ri:database-2-line', 95, '', 2, 2, 2, 2, 2, 0, NULL, 1, '" . self::REMARK . "', 1, 1, NOW(), NOW(), NULL
            FROM `sa_system_menu`
            WHERE `code` = 'Safeguard'
              AND `delete_time` IS NULL
              AND NOT EXISTS (SELECT 1 FROM `sa_system_menu` WHERE `code` = 'Adminer' AND `delete_time` IS NULL)
            LIMIT 1"
        );

        $this->execute(
            "INSERT INTO `sa_system_menu` (`parent_id`, `name`, `code`, `slug`, `type`, `path`, `component`, `method`, `icon`, `sort`, `link_url`, `is_iframe`, `is_keep_alive`, `is_hidden`, `is_fixed_tab`, `is_full_page`, `generate_id`, `generate_key`, `status`, `remark`, `created_by`, `updated_by`, `create_time`, `update_time`, `delete_time`)
            SELECT `id`, '打开 Adminer', '', 'core:adminer:ticket', 3, '', '', NULL, '', 100, '', 2, 2, 2, 2, 2, 0, NULL, 1, '" . self::REMARK . "', 1, 1, NOW(), NOW(), NULL
            FROM `sa_system_menu`
            WHERE `code` = 'Adminer'
              AND `delete_time` IS NULL
              AND NOT EXISTS (SELECT 1 FROM `sa_system_menu` WHERE `slug` = 'core:adminer:ticket' AND `delete_time` IS NULL)
            LIMIT 1"
        );
    }

    public function down(): void
    {
        $this->execute("DELETE FROM `sa_system_menu` WHERE `slug` = 'core:adminer:ticket' AND `remark` = '" . self::REMARK . "'");
        $this->execute("DELETE FROM `sa_system_menu` WHERE `code` = 'Adminer' AND `remark` = '" . self::REMARK . "'");
    }
}
