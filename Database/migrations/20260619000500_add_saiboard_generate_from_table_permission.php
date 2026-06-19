<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddSaiboardGenerateFromTablePermission extends AbstractMigration
{
    private const REMARK = 'phinx:20260619000500_add_saiboard_generate_from_table_permission';

    public function up(): void
    {
        $this->insertPermission('SAIBoardScreen', '从数据表生成大屏', 'saiboard:screen:generateFromTable');
    }

    public function down(): void
    {
        $this->execute('DELETE FROM `sa_system_menu` WHERE `remark` = ' . $this->q(self::REMARK));
    }

    private function insertPermission(string $parentCode, string $name, string $slug): void
    {
        $this->execute(
            'INSERT INTO `sa_system_menu` (`parent_id`, `name`, `code`, `slug`, `type`, `path`, `component`, `method`, `icon`, `sort`, `link_url`, `is_iframe`, `is_keep_alive`, `is_hidden`, `is_fixed_tab`, `is_full_page`, `generate_id`, `generate_key`, `status`, `remark`, `created_by`, `updated_by`, `create_time`, `update_time`, `delete_time`)
            SELECT `id`, ' . $this->q($name) . ", '', " . $this->q($slug) . ", 3, '', '', NULL, '', 100, '', 2, 2, 2, 2, 2, 0, NULL, 1, " . $this->q(self::REMARK) . ', 1, 1, NOW(), NOW(), NULL
            FROM `sa_system_menu`
            WHERE `code` = ' . $this->q($parentCode) . '
              AND `delete_time` IS NULL
              AND NOT EXISTS (SELECT 1 FROM `sa_system_menu` WHERE `slug` = ' . $this->q($slug) . ' AND `delete_time` IS NULL)
            LIMIT 1'
        );
    }

    private function q(mixed $value): string
    {
        return $this->getAdapter()->getConnection()->quote((string) $value);
    }
}
