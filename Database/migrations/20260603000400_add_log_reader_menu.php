<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddLogReaderMenu extends AbstractMigration
{
    private const REMARK = 'phinx:20260603000400_add_log_reader_menu';

    public function up(): void
    {
        $this->execute(
            "INSERT INTO `sa_system_menu` (`parent_id`, `name`, `code`, `slug`, `type`, `path`, `component`, `method`, `icon`, `sort`, `link_url`, `is_iframe`, `is_keep_alive`, `is_hidden`, `is_fixed_tab`, `is_full_page`, `generate_id`, `generate_key`, `status`, `remark`, `created_by`, `updated_by`, `create_time`, `update_time`, `delete_time`)
            SELECT `id`, '日志查看器', 'LogReader', '', 2, 'log-reader', '/safeguard/log-reader', NULL, 'ri:file-search-line', 96, '', 2, 2, 2, 2, 2, 0, NULL, 1, '" . self::REMARK . "', 1, 1, NOW(), NOW(), NULL
            FROM `sa_system_menu`
            WHERE `code` = 'Safeguard'
              AND `delete_time` IS NULL
              AND NOT EXISTS (SELECT 1 FROM `sa_system_menu` WHERE `code` = 'LogReader' AND `delete_time` IS NULL)
            LIMIT 1"
        );

        $this->execute(
            "INSERT INTO `sa_system_menu` (`parent_id`, `name`, `code`, `slug`, `type`, `path`, `component`, `method`, `icon`, `sort`, `link_url`, `is_iframe`, `is_keep_alive`, `is_hidden`, `is_fixed_tab`, `is_full_page`, `generate_id`, `generate_key`, `status`, `remark`, `created_by`, `updated_by`, `create_time`, `update_time`, `delete_time`)
            SELECT `id`, '打开日志查看器', '', 'core:log-reader:ticket', 3, '', '', NULL, '', 100, '', 2, 2, 2, 2, 2, 0, NULL, 1, '" . self::REMARK . "', 1, 1, NOW(), NOW(), NULL
            FROM `sa_system_menu`
            WHERE `code` = 'LogReader'
              AND `delete_time` IS NULL
              AND NOT EXISTS (SELECT 1 FROM `sa_system_menu` WHERE `slug` = 'core:log-reader:ticket' AND `delete_time` IS NULL)
            LIMIT 1"
        );
    }

    public function down(): void
    {
        $this->execute("DELETE FROM `sa_system_menu` WHERE `slug` = 'core:log-reader:ticket' AND `remark` = '" . self::REMARK . "'");
        $this->execute("DELETE FROM `sa_system_menu` WHERE `code` = 'LogReader' AND `remark` = '" . self::REMARK . "'");
    }
}
