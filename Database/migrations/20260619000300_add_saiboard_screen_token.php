<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddSaiboardScreenToken extends AbstractMigration
{
    private const REMARK = 'phinx:20260619000300_add_saiboard_screen_token';

    public function up(): void
    {
        $this->execute(
            "CREATE TABLE IF NOT EXISTS `saiboard_screen_token` (
                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '编号',
                `screen_id` bigint(20) unsigned NOT NULL COMMENT '大屏ID',
                `name` varchar(80) NOT NULL DEFAULT '' COMMENT '令牌名称',
                `token_prefix` varchar(16) NOT NULL DEFAULT '' COMMENT '令牌前缀',
                `token_hash` char(64) NOT NULL COMMENT '令牌哈希',
                `last_used_time` datetime DEFAULT NULL COMMENT '最近使用时间',
                `expire_time` datetime DEFAULT NULL COMMENT '过期时间',
                `status` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT '状态 1启用 2停用',
                `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
                `created_by` int(11) DEFAULT NULL COMMENT '创建者',
                `updated_by` int(11) DEFAULT NULL COMMENT '更新者',
                `create_time` datetime DEFAULT NULL COMMENT '创建时间',
                `update_time` datetime DEFAULT NULL COMMENT '修改时间',
                `delete_time` datetime DEFAULT NULL COMMENT '删除时间',
                PRIMARY KEY (`id`) USING BTREE,
                UNIQUE KEY `uk_token_hash` (`token_hash`) USING BTREE,
                KEY `idx_screen_status` (`screen_id`, `status`) USING BTREE,
                KEY `idx_expire_time` (`expire_time`) USING BTREE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='SAI Board 大屏访问令牌表' ROW_FORMAT=DYNAMIC"
        );

        foreach ([
            ['大屏令牌列表', 'saiboard:screen:tokens'],
            ['大屏令牌创建', 'saiboard:screen:createToken'],
            ['大屏令牌重置', 'saiboard:screen:resetToken'],
            ['大屏令牌状态', 'saiboard:screen:changeTokenStatus'],
            ['大屏令牌删除', 'saiboard:screen:deleteToken'],
        ] as [$name, $slug]) {
            $this->insertPermission('SAIBoardScreen', $name, $slug);
        }
    }

    public function down(): void
    {
        $this->execute('DELETE FROM `sa_system_menu` WHERE `remark` = ' . $this->q(self::REMARK));
        $this->dropTableIfEmpty('saiboard_screen_token', '访问令牌');
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
