<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddSaiboardPlugin extends AbstractMigration
{
    private const REMARK = 'phinx:20260619000100_add_saiboard_plugin';

    public function up(): void
    {
        $this->createTables();
        $this->seedMenus();
    }

    public function down(): void
    {
        $this->execute('DELETE FROM `sa_system_menu` WHERE `remark` = ' . $this->q(self::REMARK));
        $this->dropTableIfEmpty('saiboard_query_template', '查询模板');
        $this->dropTableIfEmpty('saiboard_screen', '大屏');
        $this->dropTableIfEmpty('saiboard_datasource', '数据源');
    }

    private function createTables(): void
    {
        $this->execute(
            "CREATE TABLE IF NOT EXISTS `saiboard_datasource` (
                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '编号',
                `name` varchar(60) NOT NULL DEFAULT '' COMMENT '数据源名称',
                `type` varchar(10) NOT NULL DEFAULT 'mysql' COMMENT '数据源类型 mysql/http',
                `config` json DEFAULT NULL COMMENT '连接配置',
                `cache_ttl` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '缓存秒数',
                `last_error` text COMMENT '最近错误',
                `status` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT '状态 1启用 2停用',
                `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
                `created_by` int(11) DEFAULT NULL COMMENT '创建者',
                `updated_by` int(11) DEFAULT NULL COMMENT '更新者',
                `create_time` datetime DEFAULT NULL COMMENT '创建时间',
                `update_time` datetime DEFAULT NULL COMMENT '修改时间',
                `delete_time` datetime DEFAULT NULL COMMENT '删除时间',
                PRIMARY KEY (`id`) USING BTREE,
                KEY `idx_type_status` (`type`, `status`) USING BTREE,
                KEY `idx_create_time` (`create_time`) USING BTREE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='SAI Board 数据源表' ROW_FORMAT=DYNAMIC"
        );

        $this->execute(
            "CREATE TABLE IF NOT EXISTS `saiboard_screen` (
                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '编号',
                `code` varchar(32) NOT NULL DEFAULT '' COMMENT '访问编码',
                `name` varchar(60) NOT NULL DEFAULT '' COMMENT '大屏名称',
                `width` int(11) unsigned NOT NULL DEFAULT 1920 COMMENT '设计宽度',
                `height` int(11) unsigned NOT NULL DEFAULT 1080 COMMENT '设计高度',
                `bg_config` json DEFAULT NULL COMMENT '背景配置',
                `is_public` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT '是否公开 1公开 2需鉴权',
                `access_token` varchar(64) DEFAULT NULL COMMENT '访问令牌',
                `draft_layout` json DEFAULT NULL COMMENT '草稿布局',
                `layout` json DEFAULT NULL COMMENT '发布布局',
                `status` tinyint(1) unsigned NOT NULL DEFAULT 2 COMMENT '状态 1已发布 2草稿',
                `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
                `created_by` int(11) DEFAULT NULL COMMENT '创建者',
                `updated_by` int(11) DEFAULT NULL COMMENT '更新者',
                `create_time` datetime DEFAULT NULL COMMENT '创建时间',
                `update_time` datetime DEFAULT NULL COMMENT '修改时间',
                `delete_time` datetime DEFAULT NULL COMMENT '删除时间',
                PRIMARY KEY (`id`) USING BTREE,
                UNIQUE KEY `uk_code` (`code`) USING BTREE,
                KEY `idx_status_public` (`status`, `is_public`) USING BTREE,
                KEY `idx_create_time` (`create_time`) USING BTREE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='SAI Board 大屏表' ROW_FORMAT=DYNAMIC"
        );

        $this->execute(
            "CREATE TABLE IF NOT EXISTS `saiboard_query_template` (
                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '编号',
                `datasource_id` bigint(20) unsigned NOT NULL COMMENT '数据源ID',
                `name` varchar(60) NOT NULL DEFAULT '' COMMENT '模板名称',
                `dataset_type` varchar(20) NOT NULL DEFAULT 'table_raw' COMMENT '取数类型',
                `config` json DEFAULT NULL COMMENT '模板配置',
                `status` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT '状态 1启用 2停用',
                `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
                `created_by` int(11) DEFAULT NULL COMMENT '创建者',
                `updated_by` int(11) DEFAULT NULL COMMENT '更新者',
                `create_time` datetime DEFAULT NULL COMMENT '创建时间',
                `update_time` datetime DEFAULT NULL COMMENT '修改时间',
                `delete_time` datetime DEFAULT NULL COMMENT '删除时间',
                PRIMARY KEY (`id`) USING BTREE,
                KEY `idx_datasource` (`datasource_id`) USING BTREE,
                KEY `idx_type_status` (`dataset_type`, `status`) USING BTREE,
                KEY `idx_create_time` (`create_time`) USING BTREE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='SAI Board 查询模板表' ROW_FORMAT=DYNAMIC"
        );
    }

    private function seedMenus(): void
    {
        $this->insertRootMenu('SAI Board', 'SAIBoard', '/saiboard', 'ri:dashboard-3-line', 88);
        $this->insertMenu('SAIBoard', '大屏管理', 'SAIBoardScreen', 'screen', '/plugin/saiboard/screen/index', 'ri:layout-masonry-line', 100);
        $this->insertMenu('SAIBoard', '数据源管理', 'SAIBoardDatasource', 'datasource', '/plugin/saiboard/datasource/index', 'ri:database-2-line', 95);
        $this->insertMenu('SAIBoard', '查询模板', 'SAIBoardQueryTemplate', 'query-template', '/plugin/saiboard/query-template/index', 'ri:file-search-line', 90);
        $this->insertHiddenMenu('SAIBoard', '大屏编辑器', 'SAIBoardEditor', 'editor/:id', '/plugin/saiboard/editor/[id]', 2);

        foreach ([
            'screen' => ['SAIBoardScreen', '大屏', ['index', 'read', 'save', 'update', 'destroy', 'changeStatus', 'saveLayout', 'publish', 'copy']],
            'datasource' => ['SAIBoardDatasource', '数据源', ['index', 'read', 'save', 'update', 'destroy', 'changeStatus', 'test']],
            'query_template' => ['SAIBoardQueryTemplate', '查询模板', ['index', 'read', 'save', 'update', 'destroy', 'changeStatus', 'preview']],
        ] as $module => [$parentCode, $label, $actions]) {
            foreach ($actions as $action) {
                $this->insertPermission($parentCode, $this->permissionName($label, $action), "saiboard:{$module}:{$action}");
            }
        }
    }

    private function insertRootMenu(string $name, string $code, string $path, string $icon, int $sort): void
    {
        $this->execute(
            'INSERT INTO `sa_system_menu` (`parent_id`, `name`, `code`, `slug`, `type`, `path`, `component`, `method`, `icon`, `sort`, `link_url`, `is_iframe`, `is_keep_alive`, `is_hidden`, `is_fixed_tab`, `is_full_page`, `generate_id`, `generate_key`, `status`, `remark`, `created_by`, `updated_by`, `create_time`, `update_time`, `delete_time`)
            SELECT 0, ' . $this->q($name) . ', ' . $this->q($code) . ", '', 1, " . $this->q($path) . ", '/index/index', NULL, " . $this->q($icon) . ", {$sort}, '', 2, 2, 2, 2, 2, 0, NULL, 1, " . $this->q(self::REMARK) . ', 1, 1, NOW(), NOW(), NULL
            WHERE NOT EXISTS (SELECT 1 FROM `sa_system_menu` WHERE `code` = ' . $this->q($code) . ' AND `delete_time` IS NULL)'
        );
    }

    private function insertMenu(string $parentCode, string $name, string $code, string $path, string $component, string $icon, int $sort): void
    {
        $this->execute(
            'INSERT INTO `sa_system_menu` (`parent_id`, `name`, `code`, `slug`, `type`, `path`, `component`, `method`, `icon`, `sort`, `link_url`, `is_iframe`, `is_keep_alive`, `is_hidden`, `is_fixed_tab`, `is_full_page`, `generate_id`, `generate_key`, `status`, `remark`, `created_by`, `updated_by`, `create_time`, `update_time`, `delete_time`)
            SELECT `id`, ' . $this->q($name) . ', ' . $this->q($code) . ", '', 2, " . $this->q($path) . ', ' . $this->q($component) . ', NULL, ' . $this->q($icon) . ", {$sort}, '', 2, 2, 2, 2, 2, 0, NULL, 1, " . $this->q(self::REMARK) . ', 1, 1, NOW(), NOW(), NULL
            FROM `sa_system_menu`
            WHERE `code` = ' . $this->q($parentCode) . '
              AND `delete_time` IS NULL
              AND NOT EXISTS (SELECT 1 FROM `sa_system_menu` WHERE `code` = ' . $this->q($code) . ' AND `delete_time` IS NULL)
            LIMIT 1'
        );
    }

    private function insertHiddenMenu(string $parentCode, string $name, string $code, string $path, string $component, int $isFullPage): void
    {
        $this->execute(
            'INSERT INTO `sa_system_menu` (`parent_id`, `name`, `code`, `slug`, `type`, `path`, `component`, `method`, `icon`, `sort`, `link_url`, `is_iframe`, `is_keep_alive`, `is_hidden`, `is_fixed_tab`, `is_full_page`, `generate_id`, `generate_key`, `status`, `remark`, `created_by`, `updated_by`, `create_time`, `update_time`, `delete_time`)
            SELECT `id`, ' . $this->q($name) . ', ' . $this->q($code) . ", '', 2, " . $this->q($path) . ', ' . $this->q($component) . ", NULL, '', 10, '', 2, 2, 1, 2, {$isFullPage}, 0, NULL, 1, " . $this->q(self::REMARK) . ', 1, 1, NOW(), NOW(), NULL
            FROM `sa_system_menu`
            WHERE `code` = ' . $this->q($parentCode) . '
              AND `delete_time` IS NULL
              AND NOT EXISTS (SELECT 1 FROM `sa_system_menu` WHERE `code` = ' . $this->q($code) . ' AND `delete_time` IS NULL)
            LIMIT 1'
        );
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

    private function permissionName(string $label, string $action): string
    {
        return $label . match ($action) {
            'index' => '列表',
            'read' => '读取',
            'save' => '添加',
            'update' => '修改',
            'destroy' => '删除',
            'changeStatus' => '状态',
            'saveLayout' => '保存布局',
            'publish' => '发布',
            'copy' => '复制',
            'test' => '测试',
            'preview' => '预览',
            default => $action,
        };
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
