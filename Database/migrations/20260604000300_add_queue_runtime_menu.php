<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddQueueRuntimeMenu extends AbstractMigration
{
    private const REMARK = 'phinx:20260604000300_add_queue_runtime_menu';

    public function up(): void
    {
        $this->insertMenu('队列运行', 'QueueRuntime', 'queue/runtime', '/tool/queue/runtime', 'ri:pulse-line', 99);
        $this->insertPermission('QueueRuntime', '数据列表', 'tool:queue-runtime:index');
        $this->insertPermission('QueueRuntime', '清空队列', 'tool:queue-runtime:purge');
    }

    public function down(): void
    {
        $this->execute("DELETE FROM `sa_system_menu` WHERE `remark` = '" . self::REMARK . "'");
    }

    private function insertMenu(string $name, string $code, string $path, string $component, string $icon, int $sort): void
    {
        $this->execute(
            "INSERT INTO `sa_system_menu` (`parent_id`, `name`, `code`, `slug`, `type`, `path`, `component`, `method`, `icon`, `sort`, `link_url`, `is_iframe`, `is_keep_alive`, `is_hidden`, `is_fixed_tab`, `is_full_page`, `generate_id`, `generate_key`, `status`, `remark`, `created_by`, `updated_by`, `create_time`, `update_time`, `delete_time`)
            SELECT `id`, '{$name}', '{$code}', '', 2, '{$path}', '{$component}', NULL, '{$icon}', {$sort}, '', 2, 2, 2, 2, 2, 0, NULL, 1, '" . self::REMARK . "', 1, 1, NOW(), NOW(), NULL
            FROM `sa_system_menu`
            WHERE `code` = 'Tool'
              AND `delete_time` IS NULL
              AND NOT EXISTS (SELECT 1 FROM `sa_system_menu` WHERE `code` = '{$code}' AND `delete_time` IS NULL)
            LIMIT 1"
        );
    }

    private function insertPermission(string $parentCode, string $name, string $slug): void
    {
        $this->execute(
            "INSERT INTO `sa_system_menu` (`parent_id`, `name`, `code`, `slug`, `type`, `path`, `component`, `method`, `icon`, `sort`, `link_url`, `is_iframe`, `is_keep_alive`, `is_hidden`, `is_fixed_tab`, `is_full_page`, `generate_id`, `generate_key`, `status`, `remark`, `created_by`, `updated_by`, `create_time`, `update_time`, `delete_time`)
            SELECT `id`, '{$name}', '', '{$slug}', 3, '', '', NULL, '', 100, '', 2, 2, 2, 2, 2, 0, NULL, 1, '" . self::REMARK . "', 1, 1, NOW(), NOW(), NULL
            FROM `sa_system_menu`
            WHERE `code` = '{$parentCode}'
              AND `delete_time` IS NULL
              AND NOT EXISTS (SELECT 1 FROM `sa_system_menu` WHERE `slug` = '{$slug}' AND `delete_time` IS NULL)
            LIMIT 1"
        );
    }
}
