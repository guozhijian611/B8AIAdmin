<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddB8cmsArticleComments extends AbstractMigration
{
    private const REMARK = 'phinx:20260606170315_add_b_8cms_article_comments';

    public function up(): void
    {
        $this->createTables();
        $this->seedFilters();
        $this->seedMenus();
    }

    public function down(): void
    {
        $this->execute("DELETE FROM `sa_system_menu` WHERE `remark` = '" . self::REMARK . "'");
        $this->execute('DROP TABLE IF EXISTS `b8cms_comment_filter`');
        $this->execute('DROP TABLE IF EXISTS `b8cms_comment`');
    }

    private function createTables(): void
    {
        $this->execute(
            "CREATE TABLE IF NOT EXISTS `b8cms_comment` (
                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
                `content_id` bigint(20) unsigned NOT NULL COMMENT '内容ID',
                `parent_id` bigint(20) unsigned NOT NULL DEFAULT 0 COMMENT '父级评论ID',
                `root_id` bigint(20) unsigned NOT NULL DEFAULT 0 COMMENT '根评论ID',
                `level` int(10) unsigned NOT NULL DEFAULT 1 COMMENT '层级',
                `path` varchar(1000) NOT NULL DEFAULT '' COMMENT '层级路径',
                `content_type` varchar(30) NOT NULL DEFAULT 'article' COMMENT '内容类型',
                `content_slug` varchar(160) NOT NULL DEFAULT '' COMMENT '内容别名',
                `content_title` varchar(255) NOT NULL DEFAULT '' COMMENT '内容标题',
                `lang_code` varchar(20) NOT NULL DEFAULT '' COMMENT '语言标识',
                `nickname` varchar(80) NOT NULL DEFAULT '' COMMENT '昵称',
                `email` varchar(160) NOT NULL DEFAULT '' COMMENT '邮箱',
                `website` varchar(255) NOT NULL DEFAULT '' COMMENT '网站',
                `comment` text COMMENT '评论内容',
                `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态 1已通过 2待审核 3已屏蔽',
                `block_reason` varchar(255) NOT NULL DEFAULT '' COMMENT '屏蔽原因',
                `matched_rule` varchar(255) NOT NULL DEFAULT '' COMMENT '命中规则',
                `ip` varchar(45) NOT NULL DEFAULT '' COMMENT 'IP地址',
                `user_agent` varchar(500) NOT NULL DEFAULT '' COMMENT '浏览器UA',
                `browser_fingerprint` varchar(255) NOT NULL DEFAULT '' COMMENT '浏览器指纹',
                `source_url` varchar(500) NOT NULL DEFAULT '' COMMENT '来源页面',
                `reviewed_by` bigint(20) unsigned DEFAULT NULL COMMENT '审核人',
                `reviewed_at` datetime DEFAULT NULL COMMENT '审核时间',
                `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
                `created_by` int(11) DEFAULT NULL COMMENT '创建者',
                `updated_by` int(11) DEFAULT NULL COMMENT '更新者',
                `create_time` datetime DEFAULT NULL COMMENT '创建时间',
                `update_time` datetime DEFAULT NULL COMMENT '修改时间',
                `delete_time` datetime DEFAULT NULL COMMENT '删除时间',
                PRIMARY KEY (`id`) USING BTREE,
                KEY `idx_content_status_time` (`content_id`, `status`, `create_time`) USING BTREE,
                KEY `idx_parent_id` (`parent_id`) USING BTREE,
                KEY `idx_root_id` (`root_id`) USING BTREE,
                KEY `idx_email` (`email`) USING BTREE,
                KEY `idx_ip` (`ip`) USING BTREE,
                KEY `idx_delete_time` (`delete_time`) USING BTREE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='B8CMS文章评论表' ROW_FORMAT=DYNAMIC"
        );

        $this->execute(
            "CREATE TABLE IF NOT EXISTS `b8cms_comment_filter` (
                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
                `rule_type` varchar(20) NOT NULL DEFAULT 'word' COMMENT '规则类型 word/email',
                `match_type` varchar(20) NOT NULL DEFAULT 'contains' COMMENT '匹配方式 contains/exact/domain/regex',
                `value` varchar(255) NOT NULL DEFAULT '' COMMENT '规则值',
                `description` varchar(255) NOT NULL DEFAULT '' COMMENT '规则说明',
                `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态 1启用 2禁用',
                `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
                `created_by` int(11) DEFAULT NULL COMMENT '创建者',
                `updated_by` int(11) DEFAULT NULL COMMENT '更新者',
                `create_time` datetime DEFAULT NULL COMMENT '创建时间',
                `update_time` datetime DEFAULT NULL COMMENT '修改时间',
                `delete_time` datetime DEFAULT NULL COMMENT '删除时间',
                PRIMARY KEY (`id`) USING BTREE,
                KEY `idx_rule` (`rule_type`, `match_type`, `value`) USING BTREE,
                KEY `idx_status` (`status`) USING BTREE,
                KEY `idx_delete_time` (`delete_time`) USING BTREE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='B8CMS评论屏蔽规则表' ROW_FORMAT=DYNAMIC"
        );
    }

    private function seedFilters(): void
    {
        foreach ([
            ['word', 'contains', '垃圾广告', '预设中文垃圾广告词'],
            ['word', 'contains', '博彩', '预设高风险营销词'],
            ['word', 'contains', '彩票', '预设高风险营销词'],
            ['word', 'contains', '代开发票', '预设违法广告词'],
            ['word', 'contains', '加微信', '预设站外引流词'],
            ['word', 'contains', '刷单', '预设欺诈风险词'],
            ['word', 'contains', '贷款', '预设金融广告词'],
            ['word', 'contains', '色情', '预设违规内容词'],
            ['word', 'contains', '暴力', '预设违规内容词'],
            ['word', 'contains', '诈骗', '预设欺诈风险词'],
            ['word', 'contains', '站外引流', '预设站外引流词'],
            ['word', 'contains', '免费领取', '预设诱导营销词'],
            ['word', 'contains', 'casino', 'Preset spam keyword'],
            ['word', 'contains', 'viagra', 'Preset spam keyword'],
            ['word', 'contains', 'porn', 'Preset spam keyword'],
            ['word', 'contains', 'scam', 'Preset spam keyword'],
            ['word', 'contains', 'loan', 'Preset spam keyword'],
            ['email', 'exact', 'spam@example.com', '预设屏蔽邮箱'],
            ['email', 'exact', 'blackhole@example.com', '预设屏蔽邮箱'],
            ['email', 'exact', 'abuse@example.com', '预设屏蔽邮箱'],
            ['email', 'domain', 'mailinator.com', '预设临时邮箱域名'],
            ['email', 'domain', '10minutemail.com', '预设临时邮箱域名'],
            ['email', 'domain', 'tempmail.com', '预设临时邮箱域名'],
            ['email', 'domain', 'guerrillamail.com', '预设临时邮箱域名'],
            ['email', 'domain', 'yopmail.com', '预设临时邮箱域名'],
            ['email', 'domain', 'trashmail.com', '预设临时邮箱域名'],
        ] as [$ruleType, $matchType, $value, $description]) {
            $this->insertFilter($ruleType, $matchType, $value, $description);
        }
    }

    private function seedMenus(): void
    {
        $this->insertMenu('B8CMS', '评论管理', 'B8CMSComment', 'comment', '/plugin/b8cms/comment/index', 'ri:chat-3-line', 74);
        $this->insertMenu('B8CMS', '屏蔽规则', 'B8CMSCommentFilter', 'comment-filter', '/plugin/b8cms/comment-filter/index', 'ri:shield-keyhole-line', 73);

        $this->insertPermission('B8CMSComment', '评论列表', 'b8cms:comment:index');
        $this->insertPermission('B8CMSComment', '评论读取', 'b8cms:comment:read');
        $this->insertPermission('B8CMSComment', '评论修改', 'b8cms:comment:update');
        $this->insertPermission('B8CMSComment', '评论删除', 'b8cms:comment:destroy');
        $this->insertPermission('B8CMSComment', '评论处理', 'b8cms:comment:handle');

        $this->insertPermission('B8CMSCommentFilter', '屏蔽规则列表', 'b8cms:comment-filter:index');
        $this->insertPermission('B8CMSCommentFilter', '屏蔽规则读取', 'b8cms:comment-filter:read');
        $this->insertPermission('B8CMSCommentFilter', '屏蔽规则添加', 'b8cms:comment-filter:save');
        $this->insertPermission('B8CMSCommentFilter', '屏蔽规则修改', 'b8cms:comment-filter:update');
        $this->insertPermission('B8CMSCommentFilter', '屏蔽规则删除', 'b8cms:comment-filter:destroy');
        $this->insertPermission('B8CMSCommentFilter', '屏蔽规则状态', 'b8cms:comment-filter:changeStatus');
    }

    private function insertFilter(string $ruleType, string $matchType, string $value, string $description): void
    {
        $this->execute(
            'INSERT INTO `b8cms_comment_filter` (`rule_type`, `match_type`, `value`, `description`, `status`, `remark`, `created_by`, `updated_by`, `create_time`, `update_time`, `delete_time`)
            SELECT ' . $this->q($ruleType) . ', ' . $this->q($matchType) . ', ' . $this->q($value) . ', ' . $this->q($description) . ', 1, ' . $this->q(self::REMARK) . ', 1, 1, NOW(), NOW(), NULL
            WHERE NOT EXISTS (
                SELECT 1 FROM `b8cms_comment_filter`
                WHERE `rule_type` = ' . $this->q($ruleType) . ' AND `match_type` = ' . $this->q($matchType) . ' AND `value` = ' . $this->q($value) . ' AND `delete_time` IS NULL
            )'
        );
    }

    private function insertMenu(string $parentCode, string $name, string $code, string $path, string $component, string $icon, int $sort): void
    {
        $this->execute(
            'INSERT INTO `sa_system_menu` (`parent_id`, `name`, `code`, `slug`, `type`, `path`, `component`, `method`, `icon`, `sort`, `link_url`, `is_iframe`, `is_keep_alive`, `is_hidden`, `is_fixed_tab`, `is_full_page`, `generate_id`, `generate_key`, `status`, `remark`, `created_by`, `updated_by`, `create_time`, `update_time`, `delete_time`)
            SELECT `id`, ' . $this->q($name) . ', ' . $this->q($code) . ', ' . $this->q('') . ', 2, ' . $this->q($path) . ', ' . $this->q($component) . ', NULL, ' . $this->q($icon) . ", {$sort}, " . $this->q('') . ', 2, 2, 2, 2, 2, 0, NULL, 1, ' . $this->q(self::REMARK) . ', 1, 1, NOW(), NOW(), NULL
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
            SELECT `id`, ' . $this->q($name) . ', ' . $this->q('') . ', ' . $this->q($slug) . ', 3, ' . $this->q('') . ', ' . $this->q('') . ', NULL, ' . $this->q('') . ', 100, ' . $this->q('') . ', 2, 2, 2, 2, 2, 0, NULL, 1, ' . $this->q(self::REMARK) . ', 1, 1, NOW(), NOW(), NULL
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
