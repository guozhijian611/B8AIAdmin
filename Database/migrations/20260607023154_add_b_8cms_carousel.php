<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddB8cmsCarousel extends AbstractMigration
{
    private const REMARK = 'phinx:20260607023154_add_b_8cms_carousel';

    public function up(): void
    {
        $this->createTable();
        $this->seedCarousels();
        $this->seedMenus();
    }

    public function down(): void
    {
        $this->execute('DELETE FROM `sa_system_menu` WHERE `remark` = ' . $this->q(self::REMARK));
        $this->execute('DROP TABLE IF EXISTS `b8cms_carousel`');
    }

    private function createTable(): void
    {
        $this->execute(
            "CREATE TABLE IF NOT EXISTS `b8cms_carousel` (
                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
                `lang_code` varchar(20) NOT NULL COMMENT '语言标识',
                `position` varchar(40) NOT NULL DEFAULT 'home' COMMENT '轮播位置 home',
                `title` varchar(255) NOT NULL DEFAULT '' COMMENT '标题',
                `subtitle` varchar(500) NOT NULL DEFAULT '' COMMENT '副标题',
                `description` varchar(1000) NOT NULL DEFAULT '' COMMENT '描述',
                `image` varchar(1000) NOT NULL DEFAULT '' COMMENT '桌面图片',
                `mobile_image` varchar(1000) NOT NULL DEFAULT '' COMMENT '移动端图片',
                `image_alt` varchar(255) NOT NULL DEFAULT '' COMMENT '图片Alt',
                `button_text` varchar(120) NOT NULL DEFAULT '' COMMENT '按钮文案',
                `button_url` varchar(500) NOT NULL DEFAULT '' COMMENT '按钮链接',
                `secondary_button_text` varchar(120) NOT NULL DEFAULT '' COMMENT '次按钮文案',
                `secondary_button_url` varchar(500) NOT NULL DEFAULT '' COMMENT '次按钮链接',
                `target` varchar(20) NOT NULL DEFAULT '_self' COMMENT '打开方式',
                `sort` int(11) NOT NULL DEFAULT 100 COMMENT '排序',
                `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态 1启用 2禁用',
                `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
                `created_by` int(11) DEFAULT NULL COMMENT '创建者',
                `updated_by` int(11) DEFAULT NULL COMMENT '更新者',
                `create_time` datetime DEFAULT NULL COMMENT '创建时间',
                `update_time` datetime DEFAULT NULL COMMENT '修改时间',
                `delete_time` datetime DEFAULT NULL COMMENT '删除时间',
                PRIMARY KEY (`id`) USING BTREE,
                KEY `idx_lang_position_status` (`lang_code`, `position`, `status`) USING BTREE,
                KEY `idx_sort` (`sort`) USING BTREE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='B8CMS多语言轮播图表' ROW_FORMAT=DYNAMIC"
        );
    }

    private function seedCarousels(): void
    {
        foreach ($this->carousels() as $item) {
            $this->insertCarousel($item);
        }
    }

    private function seedMenus(): void
    {
        $this->insertMenu('B8CMS', '轮播图', 'B8CMSCarousel', 'carousel', '/plugin/b8cms/carousel/index', 'ri:image-2-line', 88);

        foreach ([
            ['轮播图列表', 'b8cms:carousel:index'],
            ['轮播图读取', 'b8cms:carousel:read'],
            ['轮播图添加', 'b8cms:carousel:save'],
            ['轮播图修改', 'b8cms:carousel:update'],
            ['轮播图删除', 'b8cms:carousel:destroy'],
            ['轮播图状态', 'b8cms:carousel:changeStatus'],
        ] as [$name, $slug]) {
            $this->insertPermission('B8CMSCarousel', $name, $slug);
        }
    }

    private function carousels(): array
    {
        return [
            [
                'lang_code' => 'zh-CN',
                'position' => 'home',
                'title' => '用 B8CMS 搭建多语言独立站',
                'subtitle' => '内容、产品、SEO、轮播与询盘统一管理',
                'description' => '首页轮播图按语言独立配置，支持桌面图、移动图、按钮跳转和 SEO 友好的图片 Alt。',
                'image' => '',
                'mobile_image' => '',
                'image_alt' => 'B8CMS 多语言独立站首页轮播图',
                'button_text' => '查看产品',
                'button_url' => '/page/products',
                'secondary_button_text' => '联系我们',
                'secondary_button_url' => '/page/contact',
                'target' => '_self',
                'sort' => 10,
            ],
            [
                'lang_code' => 'zh-CN',
                'position' => 'home',
                'title' => '让产品展示和内容运营同频',
                'subtitle' => '支持单页模板、多语言导航和产品参数扩展',
                'description' => '适合跨境独立站、企业官网和产品目录站，后台可为每个语言维护不同轮播文案。',
                'image' => '',
                'mobile_image' => '',
                'image_alt' => 'B8CMS 产品展示轮播图',
                'button_text' => '阅读文章',
                'button_url' => '/page/news',
                'secondary_button_text' => '',
                'secondary_button_url' => '',
                'target' => '_self',
                'sort' => 20,
            ],
            [
                'lang_code' => 'en-US',
                'position' => 'home',
                'title' => 'Build multilingual standalone sites with B8CMS',
                'subtitle' => 'Content, products, SEO, sliders and inquiries in one place',
                'description' => 'Homepage carousel slides are managed per language with desktop images, mobile images, CTA links, and SEO-ready image alt text.',
                'image' => '',
                'mobile_image' => '',
                'image_alt' => 'B8CMS multilingual standalone site carousel',
                'button_text' => 'View products',
                'button_url' => '/page/products',
                'secondary_button_text' => 'Contact us',
                'secondary_button_url' => '/page/contact',
                'target' => '_self',
                'sort' => 10,
            ],
            [
                'lang_code' => 'en-US',
                'position' => 'home',
                'title' => 'Keep product pages and content operations aligned',
                'subtitle' => 'Template overrides, translated navigation and product parameters',
                'description' => 'Designed for cross-border stores, company websites and product catalog sites that need language-specific homepage messaging.',
                'image' => '',
                'mobile_image' => '',
                'image_alt' => 'B8CMS product showcase carousel',
                'button_text' => 'Read insights',
                'button_url' => '/page/news',
                'secondary_button_text' => '',
                'secondary_button_url' => '',
                'target' => '_self',
                'sort' => 20,
            ],
        ];
    }

    private function insertCarousel(array $item): void
    {
        $this->execute(
            'INSERT INTO `b8cms_carousel` (`lang_code`, `position`, `title`, `subtitle`, `description`, `image`, `mobile_image`, `image_alt`, `button_text`, `button_url`, `secondary_button_text`, `secondary_button_url`, `target`, `sort`, `status`, `remark`, `created_by`, `updated_by`, `create_time`, `update_time`, `delete_time`)
            SELECT ' . $this->q($item['lang_code']) . ', ' . $this->q($item['position']) . ', ' . $this->q($item['title']) . ', ' . $this->q($item['subtitle']) . ', ' . $this->q($item['description']) . ', ' . $this->q($item['image']) . ', ' . $this->q($item['mobile_image']) . ', ' . $this->q($item['image_alt']) . ', ' . $this->q($item['button_text']) . ', ' . $this->q($item['button_url']) . ', ' . $this->q($item['secondary_button_text']) . ', ' . $this->q($item['secondary_button_url']) . ', ' . $this->q($item['target']) . ', ' . (int) $item['sort'] . ', 1, ' . $this->q(self::REMARK) . ', 1, 1, NOW(), NOW(), NULL
            WHERE NOT EXISTS (
                SELECT 1 FROM `b8cms_carousel`
                WHERE `lang_code` = ' . $this->q($item['lang_code']) . '
                  AND `position` = ' . $this->q($item['position']) . '
                  AND `title` = ' . $this->q($item['title']) . '
                  AND `delete_time` IS NULL
            )'
        );
    }

    private function insertMenu(string $parentCode, string $name, string $code, string $path, string $component, string $icon, int $sort): void
    {
        $this->execute(
            'INSERT INTO `sa_system_menu` (`parent_id`, `name`, `code`, `slug`, `type`, `path`, `component`, `method`, `icon`, `sort`, `link_url`, `is_iframe`, `is_keep_alive`, `is_hidden`, `is_fixed_tab`, `is_full_page`, `generate_id`, `generate_key`, `status`, `remark`, `created_by`, `updated_by`, `create_time`, `update_time`, `delete_time`)
            SELECT `id`, ' . $this->q($name) . ', ' . $this->q($code) . ', ' . $this->q('') . ', 2, ' . $this->q($path) . ', ' . $this->q($component) . ', NULL, ' . $this->q($icon) . ', ' . $sort . ', ' . $this->q('') . ', 2, 2, 2, 2, 2, 0, NULL, 1, ' . $this->q(self::REMARK) . ', 1, 1, NOW(), NOW(), NULL
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
