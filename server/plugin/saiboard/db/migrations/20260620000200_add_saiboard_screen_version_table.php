<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddSaiboardScreenVersionTable extends AbstractMigration
{
    private const REMARK = 'phinx:20260620000200_add_saiboard_screen_version_table';

    public function up(): void
    {
        $this->execute(
            "CREATE TABLE IF NOT EXISTS `saiboard_screen_version` (
                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '编号',
                `screen_id` bigint(20) unsigned NOT NULL COMMENT '大屏ID',
                `version_no` int(11) unsigned NOT NULL DEFAULT 1 COMMENT '版本号',
                `source` varchar(32) NOT NULL DEFAULT 'save_layout' COMMENT '来源 save_layout/publish/restore_before',
                `title` varchar(120) NOT NULL DEFAULT '' COMMENT '快照标题',
                `width` int(11) unsigned NOT NULL DEFAULT 1920 COMMENT '设计宽度',
                `height` int(11) unsigned NOT NULL DEFAULT 1080 COMMENT '设计高度',
                `bg_config` json DEFAULT NULL COMMENT '背景配置',
                `layout` json DEFAULT NULL COMMENT '布局快照',
                `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
                `created_by` int(11) DEFAULT NULL COMMENT '创建者',
                `updated_by` int(11) DEFAULT NULL COMMENT '更新者',
                `create_time` datetime DEFAULT NULL COMMENT '创建时间',
                `update_time` datetime DEFAULT NULL COMMENT '修改时间',
                `delete_time` datetime DEFAULT NULL COMMENT '删除时间',
                PRIMARY KEY (`id`) USING BTREE,
                UNIQUE KEY `uk_screen_version` (`screen_id`, `version_no`) USING BTREE,
                KEY `idx_screen_time` (`screen_id`, `create_time`) USING BTREE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='SAI Board 大屏版本快照表' ROW_FORMAT=DYNAMIC"
        );

        foreach ([
            ['大屏版本列表', 'saiboard:screen:versions'],
            ['大屏版本恢复', 'saiboard:screen:restoreVersion'],
            ['大屏版本删除', 'saiboard:screen:deleteVersion'],
        ] as [$name, $slug]) {
            $this->insertPermission('SAIBoardScreen', $name, $slug);
        }
    }

    public function down(): void
    {
        $this->dropTableIfEmpty('saiboard_screen_version', '版本快照');
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

    private function dropTableIfEmpty(string $table, string $label): void
    {
        if (!$this->hasTable($table)) {
            return;
        }

        $count = (int) ($this->fetchRow("SELECT COUNT(*) AS `total` FROM `{$table}`")['total'] ?? 0);
        if ($count > 0) {
            throw new RuntimeException("{$table} 已存在{$label}数据，为避免误删数据，请先备份并清空后再回滚。");
        }

        $this->table($table)->drop()->save();
    }

    private function q(mixed $value): string
    {
        return $this->getAdapter()->getConnection()->quote((string) $value);
    }
}
