<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddSaiboardMarketItem extends AbstractMigration
{
    private const REMARK = 'phinx:20260619000400_add_saiboard_market_item';

    public function up(): void
    {
        if (!$this->hasTable('saiboard_market_item')) {
            $this->execute(
                "CREATE TABLE `saiboard_market_item` (
                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '编号',
                `type` varchar(20) NOT NULL DEFAULT 'screen' COMMENT '类型 screen/component',
                `name` varchar(80) NOT NULL DEFAULT '' COMMENT '模板名称',
                `category` varchar(60) NOT NULL DEFAULT '' COMMENT '分类',
                `description` varchar(255) NOT NULL DEFAULT '' COMMENT '说明',
                `cover_image` varchar(255) NOT NULL DEFAULT '' COMMENT '封面图',
                `content` json DEFAULT NULL COMMENT '模板内容',
                `component_count` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '组件数量',
                `is_public` tinyint(1) unsigned NOT NULL DEFAULT 2 COMMENT '是否公开 1公开 2私有',
                `status` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT '状态 1启用 2停用',
                `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
                `created_by` int(11) DEFAULT NULL COMMENT '创建者',
                `updated_by` int(11) DEFAULT NULL COMMENT '更新者',
                `create_time` datetime DEFAULT NULL COMMENT '创建时间',
                `update_time` datetime DEFAULT NULL COMMENT '修改时间',
                `delete_time` datetime DEFAULT NULL COMMENT '删除时间',
                PRIMARY KEY (`id`) USING BTREE,
                KEY `idx_type_status` (`type`, `status`) USING BTREE,
                KEY `idx_public_status` (`is_public`, `status`) USING BTREE,
                KEY `idx_category` (`category`) USING BTREE,
                KEY `idx_create_time` (`create_time`) USING BTREE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='SAI Board 模板市场表 " . self::REMARK . "' ROW_FORMAT=DYNAMIC"
            );
        }

        $this->insertMenu('SAIBoard', '模板市场', 'SAIBoardMarketItem', 'market', '/plugin/saiboard/market/index', 'ri:store-2-line', 82);
        foreach ([
            ['模板市场列表', 'saiboard:market_item:index'],
            ['模板市场读取', 'saiboard:market_item:read'],
            ['模板市场添加', 'saiboard:market_item:save'],
            ['模板市场修改', 'saiboard:market_item:update'],
            ['模板市场删除', 'saiboard:market_item:destroy'],
            ['模板市场状态', 'saiboard:market_item:changeStatus'],
        ] as [$name, $slug]) {
            $this->insertPermission('SAIBoardMarketItem', $name, $slug);
        }
    }

    public function down(): void
    {
        if (!$this->hasTable('saiboard_market_item')) {
            $this->execute('DELETE FROM `sa_system_menu` WHERE `remark` = ' . $this->q(self::REMARK));
            return;
        }

        $comment = (string) ($this->fetchRow(
            "SELECT `TABLE_COMMENT`
             FROM `information_schema`.`TABLES`
             WHERE `TABLE_SCHEMA` = DATABASE()
               AND `TABLE_NAME` = 'saiboard_market_item'
             LIMIT 1"
        )['TABLE_COMMENT'] ?? '');
        if (!str_contains($comment, self::REMARK)) {
            $this->execute('DELETE FROM `sa_system_menu` WHERE `remark` = ' . $this->q(self::REMARK));
            return;
        }

        $count = (int) ($this->fetchRow('SELECT COUNT(*) AS `total` FROM `saiboard_market_item`')['total'] ?? 0);
        if ($count > 0) {
            throw new RuntimeException('saiboard_market_item 已存在模板数据，为避免误删数据，请先备份并清空后再回滚。');
        }

        $this->execute('DELETE FROM `sa_system_menu` WHERE `remark` = ' . $this->q(self::REMARK));
        $this->table('saiboard_market_item')->drop()->save();
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
