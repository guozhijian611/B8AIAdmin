<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddDatabaseBackupMenu extends AbstractMigration
{
    private const REMARK = 'phinx:20260603000200_add_database_backup_menu';

    public function up(): void
    {
        $this->execute(
            "INSERT INTO `sa_system_menu` (`parent_id`, `name`, `code`, `slug`, `type`, `path`, `component`, `method`, `icon`, `sort`, `link_url`, `is_iframe`, `is_keep_alive`, `is_hidden`, `is_fixed_tab`, `is_full_page`, `generate_id`, `generate_key`, `status`, `remark`, `created_by`, `updated_by`, `create_time`, `update_time`, `delete_time`)
            SELECT `id`, '数据库导入导出', 'DatabaseBackup', '', 2, 'database-backup', '/safeguard/database-backup', NULL, 'ri:database-2-line', 90, '', 2, 2, 2, 2, 2, 0, NULL, 1, '" . self::REMARK . "', 1, 1, NOW(), NOW(), NULL
            FROM `sa_system_menu`
            WHERE `code` = 'Safeguard'
              AND `delete_time` IS NULL
              AND NOT EXISTS (SELECT 1 FROM `sa_system_menu` WHERE `code` = 'DatabaseBackup' AND `delete_time` IS NULL)
            LIMIT 1"
        );

        $this->insertPermission('列表', 'core:database-backup:index');
        $this->insertPermission('导出', 'core:database-backup:export');
        $this->insertPermission('导入', 'core:database-backup:import');
        $this->insertPermission('删除备份', 'core:database-backup:delete');
    }

    public function down(): void
    {
        $this->execute("DELETE FROM `sa_system_menu` WHERE `slug` IN ('core:database-backup:index', 'core:database-backup:export', 'core:database-backup:import', 'core:database-backup:delete') AND `remark` = '" . self::REMARK . "'");
        $this->execute("DELETE FROM `sa_system_menu` WHERE `code` = 'DatabaseBackup' AND `remark` = '" . self::REMARK . "'");
    }

    private function insertPermission(string $name, string $slug): void
    {
        $this->execute(
            "INSERT INTO `sa_system_menu` (`parent_id`, `name`, `code`, `slug`, `type`, `path`, `component`, `method`, `icon`, `sort`, `link_url`, `is_iframe`, `is_keep_alive`, `is_hidden`, `is_fixed_tab`, `is_full_page`, `generate_id`, `generate_key`, `status`, `remark`, `created_by`, `updated_by`, `create_time`, `update_time`, `delete_time`)
            SELECT `id`, '{$name}', '', '{$slug}', 3, '', '', NULL, '', 100, '', 2, 2, 2, 2, 2, 0, NULL, 1, '" . self::REMARK . "', 1, 1, NOW(), NOW(), NULL
            FROM `sa_system_menu`
            WHERE `code` = 'DatabaseBackup'
              AND `delete_time` IS NULL
              AND NOT EXISTS (SELECT 1 FROM `sa_system_menu` WHERE `slug` = '{$slug}' AND `delete_time` IS NULL)
            LIMIT 1"
        );
    }
}
