<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddB8cmsPlugin extends AbstractMigration
{
    private const REMARK = 'phinx:20260606151515_add_b_8cms_plugin';

    public function up(): void
    {
        $this->createTables();
        $this->seedLanguages();
        $this->seedTemplate();
        $this->seedSettings();
        $this->seedContent();
        $this->seedNavigations();
        $this->seedMenus();
    }

    public function down(): void
    {
        $this->execute("DELETE FROM `sa_system_menu` WHERE `remark` = '" . self::REMARK . "'");
        $this->execute('DROP TABLE IF EXISTS `b8cms_contact_message`');
        $this->execute('DROP TABLE IF EXISTS `b8cms_site_setting`');
        $this->execute('DROP TABLE IF EXISTS `b8cms_navigation`');
        $this->execute('DROP TABLE IF EXISTS `b8cms_content`');
        $this->execute('DROP TABLE IF EXISTS `b8cms_template`');
        $this->execute('DROP TABLE IF EXISTS `b8cms_language`');
    }

    private function createTables(): void
    {
        $this->execute(
            "CREATE TABLE IF NOT EXISTS `b8cms_language` (
                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
                `code` varchar(20) NOT NULL COMMENT '语言标识',
                `name` varchar(80) NOT NULL DEFAULT '' COMMENT '语言名称',
                `native_name` varchar(80) NOT NULL DEFAULT '' COMMENT '本地化名称',
                `locale` varchar(40) NOT NULL DEFAULT '' COMMENT '区域标识',
                `is_default` tinyint(1) NOT NULL DEFAULT 2 COMMENT '是否默认 1是 2否',
                `sort` int(11) NOT NULL DEFAULT 100 COMMENT '排序',
                `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态 1启用 2禁用',
                `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
                `created_by` int(11) DEFAULT NULL COMMENT '创建者',
                `updated_by` int(11) DEFAULT NULL COMMENT '更新者',
                `create_time` datetime DEFAULT NULL COMMENT '创建时间',
                `update_time` datetime DEFAULT NULL COMMENT '修改时间',
                `delete_time` datetime DEFAULT NULL COMMENT '删除时间',
                PRIMARY KEY (`id`) USING BTREE,
                UNIQUE KEY `unx_code` (`code`) USING BTREE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='B8CMS语言表' ROW_FORMAT=DYNAMIC"
        );

        $this->execute(
            "CREATE TABLE IF NOT EXISTS `b8cms_template` (
                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
                `template_key` varchar(80) NOT NULL COMMENT '模板标识',
                `name` varchar(120) NOT NULL DEFAULT '' COMMENT '模板名称',
                `description` varchar(500) NOT NULL DEFAULT '' COMMENT '模板说明',
                `preview_image` varchar(1000) NOT NULL DEFAULT '' COMMENT '预览图',
                `options` text COMMENT '模板配置JSON',
                `is_active` tinyint(1) NOT NULL DEFAULT 2 COMMENT '是否启用 1是 2否',
                `sort` int(11) NOT NULL DEFAULT 100 COMMENT '排序',
                `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态 1启用 2禁用',
                `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
                `created_by` int(11) DEFAULT NULL COMMENT '创建者',
                `updated_by` int(11) DEFAULT NULL COMMENT '更新者',
                `create_time` datetime DEFAULT NULL COMMENT '创建时间',
                `update_time` datetime DEFAULT NULL COMMENT '修改时间',
                `delete_time` datetime DEFAULT NULL COMMENT '删除时间',
                PRIMARY KEY (`id`) USING BTREE,
                UNIQUE KEY `unx_template_key` (`template_key`) USING BTREE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='B8CMS模板表' ROW_FORMAT=DYNAMIC"
        );

        $this->execute(
            "CREATE TABLE IF NOT EXISTS `b8cms_content` (
                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
                `content_type` varchar(20) NOT NULL COMMENT '内容类型 article/product/page',
                `lang_code` varchar(20) NOT NULL COMMENT '语言标识',
                `slug` varchar(160) NOT NULL COMMENT '访问别名',
                `title` varchar(255) NOT NULL DEFAULT '' COMMENT '标题',
                `subtitle` varchar(255) NOT NULL DEFAULT '' COMMENT '副标题',
                `category` varchar(120) NOT NULL DEFAULT '' COMMENT '分类',
                `summary` varchar(1000) NOT NULL DEFAULT '' COMMENT '摘要',
                `content` mediumtext COMMENT '正文',
                `cover_image` varchar(1000) NOT NULL DEFAULT '' COMMENT '封面图',
                `images` text COMMENT '图集JSON',
                `price` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '产品价格',
                `currency` varchar(12) NOT NULL DEFAULT 'USD' COMMENT '币种',
                `stock` int(11) NOT NULL DEFAULT 0 COMMENT '库存',
                `sku` varchar(120) NOT NULL DEFAULT '' COMMENT 'SKU',
                `sort` int(11) NOT NULL DEFAULT 100 COMMENT '排序',
                `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态 1启用 2禁用',
                `is_featured` tinyint(1) NOT NULL DEFAULT 2 COMMENT '是否推荐 1是 2否',
                `published_at` datetime DEFAULT NULL COMMENT '发布时间',
                `template_file` varchar(80) NOT NULL DEFAULT '' COMMENT '模板文件',
                `seo_title` varchar(255) NOT NULL DEFAULT '' COMMENT 'SEO标题',
                `seo_keywords` varchar(500) NOT NULL DEFAULT '' COMMENT 'SEO关键词',
                `seo_description` varchar(1000) NOT NULL DEFAULT '' COMMENT 'SEO描述',
                `extra` text COMMENT '扩展字段JSON',
                `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
                `created_by` int(11) DEFAULT NULL COMMENT '创建者',
                `updated_by` int(11) DEFAULT NULL COMMENT '更新者',
                `create_time` datetime DEFAULT NULL COMMENT '创建时间',
                `update_time` datetime DEFAULT NULL COMMENT '修改时间',
                `delete_time` datetime DEFAULT NULL COMMENT '删除时间',
                PRIMARY KEY (`id`) USING BTREE,
                KEY `idx_type_lang_status` (`content_type`, `lang_code`, `status`) USING BTREE,
                KEY `idx_slug` (`content_type`, `lang_code`, `slug`) USING BTREE,
                KEY `idx_published_at` (`published_at`) USING BTREE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='B8CMS内容表' ROW_FORMAT=DYNAMIC"
        );

        $this->execute(
            "CREATE TABLE IF NOT EXISTS `b8cms_navigation` (
                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
                `parent_id` bigint(20) unsigned NOT NULL DEFAULT 0 COMMENT '父级ID',
                `lang_code` varchar(20) NOT NULL COMMENT '语言标识',
                `position` varchar(20) NOT NULL DEFAULT 'header' COMMENT '位置 header/footer',
                `title` varchar(120) NOT NULL DEFAULT '' COMMENT '标题',
                `url` varchar(500) NOT NULL DEFAULT '' COMMENT '链接',
                `target` varchar(20) NOT NULL DEFAULT '_self' COMMENT '打开方式',
                `content_type` varchar(20) NOT NULL DEFAULT 'custom' COMMENT '关联类型',
                `content_id` bigint(20) unsigned NOT NULL DEFAULT 0 COMMENT '关联内容ID',
                `sort` int(11) NOT NULL DEFAULT 100 COMMENT '排序',
                `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态 1启用 2禁用',
                `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
                `created_by` int(11) DEFAULT NULL COMMENT '创建者',
                `updated_by` int(11) DEFAULT NULL COMMENT '更新者',
                `create_time` datetime DEFAULT NULL COMMENT '创建时间',
                `update_time` datetime DEFAULT NULL COMMENT '修改时间',
                `delete_time` datetime DEFAULT NULL COMMENT '删除时间',
                PRIMARY KEY (`id`) USING BTREE,
                KEY `idx_lang_position` (`lang_code`, `position`, `status`) USING BTREE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='B8CMS导航表' ROW_FORMAT=DYNAMIC"
        );

        $this->execute(
            "CREATE TABLE IF NOT EXISTS `b8cms_site_setting` (
                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
                `group_key` varchar(80) NOT NULL DEFAULT '' COMMENT '配置分组',
                `setting_key` varchar(120) NOT NULL COMMENT '配置标识',
                `lang_code` varchar(20) NOT NULL DEFAULT '' COMMENT '语言标识',
                `title` varchar(120) NOT NULL DEFAULT '' COMMENT '配置标题',
                `value` text COMMENT '配置值JSON',
                `input_type` varchar(40) NOT NULL DEFAULT 'input' COMMENT '输入组件',
                `options` text COMMENT '组件选项JSON',
                `sort` int(11) NOT NULL DEFAULT 100 COMMENT '排序',
                `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态 1启用 2禁用',
                `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
                `created_by` int(11) DEFAULT NULL COMMENT '创建者',
                `updated_by` int(11) DEFAULT NULL COMMENT '更新者',
                `create_time` datetime DEFAULT NULL COMMENT '创建时间',
                `update_time` datetime DEFAULT NULL COMMENT '修改时间',
                `delete_time` datetime DEFAULT NULL COMMENT '删除时间',
                PRIMARY KEY (`id`) USING BTREE,
                UNIQUE KEY `unx_setting_lang` (`group_key`, `setting_key`, `lang_code`) USING BTREE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='B8CMS站点配置表' ROW_FORMAT=DYNAMIC"
        );

        $this->execute(
            "CREATE TABLE IF NOT EXISTS `b8cms_contact_message` (
                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
                `lang_code` varchar(20) NOT NULL DEFAULT '' COMMENT '语言标识',
                `name` varchar(120) NOT NULL DEFAULT '' COMMENT '姓名',
                `email` varchar(180) NOT NULL DEFAULT '' COMMENT '邮箱',
                `phone` varchar(80) NOT NULL DEFAULT '' COMMENT '电话',
                `company` varchar(180) NOT NULL DEFAULT '' COMMENT '公司',
                `subject` varchar(255) NOT NULL DEFAULT '' COMMENT '主题',
                `message` text COMMENT '留言内容',
                `source` varchar(120) NOT NULL DEFAULT 'site' COMMENT '来源',
                `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态 1待处理 2已处理 3已忽略',
                `reply_content` text COMMENT '处理备注',
                `processed_at` datetime DEFAULT NULL COMMENT '处理时间',
                `ip` varchar(80) NOT NULL DEFAULT '' COMMENT 'IP',
                `user_agent` varchar(500) NOT NULL DEFAULT '' COMMENT 'User Agent',
                `created_by` int(11) DEFAULT NULL COMMENT '创建者',
                `updated_by` int(11) DEFAULT NULL COMMENT '更新者',
                `create_time` datetime DEFAULT NULL COMMENT '创建时间',
                `update_time` datetime DEFAULT NULL COMMENT '修改时间',
                `delete_time` datetime DEFAULT NULL COMMENT '删除时间',
                PRIMARY KEY (`id`) USING BTREE,
                KEY `idx_status_time` (`status`, `create_time`) USING BTREE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='B8CMS联系表单留言表' ROW_FORMAT=DYNAMIC"
        );
    }

    private function seedLanguages(): void
    {
        $this->insertLanguage('zh-CN', 'Chinese', '简体中文', 'zh_CN', 1, 10);
        $this->insertLanguage('en-US', 'English', 'English', 'en_US', 2, 20);
    }

    private function seedTemplate(): void
    {
        $this->execute(
            'INSERT INTO `b8cms_template` (`template_key`, `name`, `description`, `preview_image`, `options`, `is_active`, `sort`, `status`, `remark`, `created_by`, `updated_by`, `create_time`, `update_time`, `delete_time`)
            SELECT ' . $this->q('default') . ', ' . $this->q('默认独立站模板') . ', ' . $this->q('内置 ThinkTemplate 模板，包含公共头部、底部、首页、文章、产品、页面视图。') . ', ' . $this->q('') . ', ' . $this->q('{}') . ', 1, 10, 1, ' . $this->q(self::REMARK) . ', 1, 1, NOW(), NOW(), NULL
            WHERE NOT EXISTS (SELECT 1 FROM `b8cms_template` WHERE `template_key` = ' . $this->q('default') . ' AND `delete_time` IS NULL)'
        );
    }

    private function seedSettings(): void
    {
        $global = [
            ['brand', 'logo', '站点 Logo', '', 'image', 10],
            ['brand', 'favicon', '站点 Favicon', '', 'image', 20],
            ['media', 'social_links', '社交媒体链接', [['label' => 'LinkedIn', 'url' => 'https://www.linkedin.com/company/example']], 'json', 30],
        ];

        foreach ($global as [$group, $key, $title, $value, $inputType, $sort]) {
            $this->insertSetting($group, $key, '', $title, $value, $inputType, $sort);
        }

        $localized = [
            'zh-CN' => [
                ['brand', 'site_name', '站点名称', 'B8 独立站', 'input', 10],
                ['seo', 'seo_title', '默认 SEO 标题', 'B8 独立站 - 多语言 CMS 与产品展示', 'input', 20],
                ['seo', 'seo_keywords', '默认 SEO 关键词', 'B8CMS,独立站,产品展示,多语言', 'input', 30],
                ['seo', 'seo_description', '默认 SEO 描述', 'B8CMS 为 B8AIadmin 提供多语言内容、产品、页面、SEO 与模板渲染能力。', 'textarea', 40],
                ['home', 'hero_title', '首页主标题', '用 B8CMS 快速搭建多语言独立站', 'input', 50],
                ['home', 'hero_subtitle', '首页副标题', '统一管理文章、产品、页面、导航、SEO、媒体资料和联系表单，并通过 ThinkTemplate 输出前台页面。', 'textarea', 60],
                ['contact', 'contact_email', '联系邮箱', 'hello@example.com', 'input', 70],
                ['contact', 'contact_phone', '联系电话', '+86 000 0000 0000', 'input', 80],
                ['contact', 'contact_address', '联系地址', '中国 上海', 'input', 90],
                ['footer', 'footer_text', '底部文案', '面向独立站业务的 B8CMS 内容管理插件。', 'textarea', 100],
            ],
            'en-US' => [
                ['brand', 'site_name', 'Site Name', 'B8 Standalone Site', 'input', 10],
                ['seo', 'seo_title', 'Default SEO Title', 'B8 Standalone Site - Multilingual CMS and Product Showcase', 'input', 20],
                ['seo', 'seo_keywords', 'Default SEO Keywords', 'B8CMS,standalone site,product showcase,multilingual', 'input', 30],
                ['seo', 'seo_description', 'Default SEO Description', 'B8CMS brings multilingual content, products, pages, SEO and template rendering to B8AIadmin.', 'textarea', 40],
                ['home', 'hero_title', 'Home Hero Title', 'Build a multilingual standalone site with B8CMS', 'input', 50],
                ['home', 'hero_subtitle', 'Home Hero Subtitle', 'Manage articles, products, pages, navigation, SEO, media settings and contact forms in one CMS plugin.', 'textarea', 60],
                ['contact', 'contact_email', 'Contact Email', 'hello@example.com', 'input', 70],
                ['contact', 'contact_phone', 'Contact Phone', '+1 000 000 0000', 'input', 80],
                ['contact', 'contact_address', 'Contact Address', 'Shanghai, China', 'input', 90],
                ['footer', 'footer_text', 'Footer Text', 'B8CMS content management plugin for standalone business sites.', 'textarea', 100],
            ],
        ];

        foreach ($localized as $lang => $items) {
            foreach ($items as [$group, $key, $title, $value, $inputType, $sort]) {
                $this->insertSetting($group, $key, $lang, $title, $value, $inputType, $sort);
            }
        }
    }

    private function seedContent(): void
    {
        $now = date('Y-m-d H:i:s');
        $this->insertContent('page', 'zh-CN', 'about', '关于我们', '让 B8 框架拥有可运营的独立站能力', '页面', 'B8CMS 是独立站内容管理插件，提供模板、多语言、SEO 和产品展示能力。', '<p>B8CMS 将文章、产品、页面、导航、SEO、媒体资料和联系表单整合在一个插件中，适合做企业官网、产品独立站和内容型站点。</p>', '', 0, 'CNY', 0, '', 10, 1, 'page', '关于我们 - B8CMS', 'B8CMS,关于我们', '了解 B8CMS 独立站内容管理能力。', $now);
        $this->insertContent('page', 'zh-CN', 'products', '产品中心', '展示独立站产品', '页面', '集中管理产品内容、SEO、图片和价格信息。', '<p>这里展示所有已发布产品。后台可以继续扩展分类、规格和询盘流程。</p>', '', 0, 'CNY', 0, '', 20, 1, 'page', '产品中心 - B8CMS', '产品中心,B8CMS', 'B8CMS 产品中心页面。', $now);
        $this->insertContent('page', 'zh-CN', 'news', '文章资讯', '发布多语言内容', '页面', '用文章持续沉淀行业内容和搜索流量。', '<p>这里展示文章资讯。每篇文章都支持独立 SEO 设置。</p>', '', 0, 'CNY', 0, '', 30, 1, 'page', '文章资讯 - B8CMS', '文章资讯,B8CMS', 'B8CMS 文章资讯页面。', $now);
        $this->insertContent('page', 'zh-CN', 'contact', '联系我们', '提交联系表单', '页面', '通过联系表单收集客户询盘。', '<p>欢迎通过邮箱、电话或页面联系表单与我们联系。</p>', '', 0, 'CNY', 0, '', 40, 1, 'page', '联系我们 - B8CMS', '联系我们,B8CMS', '联系 B8CMS。', $now);
        $this->insertContent('article', 'zh-CN', 'multilingual-cms-launch', 'B8CMS 多语言独立站能力上线', '面向独立站的 CMS 首版能力', '新闻', 'B8CMS 首版支持文章、产品、页面、导航、SEO 与模板渲染。', '<p>本次预设数据展示了 B8CMS 的基础运营闭环：多语言内容管理、产品展示、页面 SEO、导航翻译和联系表单。</p>', '', 0, 'CNY', 0, '', 50, 1, 'article', 'B8CMS 多语言 CMS 上线', 'B8CMS,多语言CMS,独立站', 'B8CMS 支持多语言文章、产品和页面管理。', $now);
        $this->insertContent('product', 'zh-CN', 'b8cms-starter', 'B8CMS Starter', '独立站基础套件', '软件', '适合企业官网和产品展示站的内容管理基础套件。', '<p>B8CMS Starter 提供模板切换、内容管理、SEO 设置、导航翻译和联系表单收集能力。</p>', '', 1999.00, 'CNY', 100, 'B8CMS-STARTER', 60, 1, 'product', 'B8CMS Starter 产品介绍', 'B8CMS Starter,独立站套件', '了解 B8CMS Starter 独立站基础套件。', $now);

        $this->insertContent('page', 'en-US', 'about', 'About Us', 'A CMS layer for standalone sites', 'Page', 'B8CMS provides templates, multilingual content, SEO and product showcase features.', '<p>B8CMS combines articles, products, pages, navigation, SEO, media settings and contact forms for business websites.</p>', '', 0, 'USD', 0, '', 10, 1, 'page', 'About B8CMS', 'B8CMS,about', 'Learn about B8CMS standalone site features.', $now);
        $this->insertContent('page', 'en-US', 'products', 'Products', 'Showcase standalone site products', 'Page', 'Manage product content, SEO, images and pricing in one place.', '<p>This page introduces published products. The backend can be expanded with categories, variants and inquiry flows.</p>', '', 0, 'USD', 0, '', 20, 1, 'page', 'Products - B8CMS', 'products,B8CMS', 'B8CMS product page.', $now);
        $this->insertContent('page', 'en-US', 'news', 'Insights', 'Publish multilingual content', 'Page', 'Use articles to build organic search and industry context.', '<p>Each article supports independent SEO settings.</p>', '', 0, 'USD', 0, '', 30, 1, 'page', 'Insights - B8CMS', 'insights,B8CMS', 'B8CMS insight page.', $now);
        $this->insertContent('page', 'en-US', 'contact', 'Contact', 'Submit an inquiry form', 'Page', 'Collect customer inquiries through the contact form.', '<p>Contact us by email, phone, or the website inquiry form.</p>', '', 0, 'USD', 0, '', 40, 1, 'page', 'Contact B8CMS', 'contact,B8CMS', 'Contact B8CMS.', $now);
        $this->insertContent('article', 'en-US', 'multilingual-cms-launch', 'B8CMS multilingual standalone site features are live', 'The first CMS version for standalone sites', 'News', 'B8CMS now supports articles, products, pages, navigation, SEO and template rendering.', '<p>The preset data demonstrates the basic operation loop: multilingual content, product showcase, page SEO, translated navigation and contact forms.</p>', '', 0, 'USD', 0, '', 50, 1, 'article', 'B8CMS multilingual CMS launch', 'B8CMS,multilingual CMS,standalone site', 'B8CMS supports multilingual articles, products and pages.', $now);
        $this->insertContent('product', 'en-US', 'b8cms-starter', 'B8CMS Starter', 'Standalone site starter kit', 'Software', 'A content management starter kit for company websites and product showcase sites.', '<p>B8CMS Starter provides template switching, content management, SEO settings, translated navigation and contact form collection.</p>', '', 299.00, 'USD', 100, 'B8CMS-STARTER', 60, 1, 'product', 'B8CMS Starter product page', 'B8CMS Starter,standalone site kit', 'Learn about the B8CMS Starter kit.', $now);
    }

    private function seedNavigations(): void
    {
        $zh = [
            ['header', '首页', '/?lang=zh-CN', 10],
            ['header', '产品中心', '/page/products?lang=zh-CN', 20],
            ['header', '文章资讯', '/page/news?lang=zh-CN', 30],
            ['header', '关于我们', '/page/about?lang=zh-CN', 40],
            ['header', '联系我们', '/page/contact?lang=zh-CN', 50],
            ['footer', '产品中心', '/page/products?lang=zh-CN', 10],
            ['footer', '文章资讯', '/page/news?lang=zh-CN', 20],
            ['footer', '联系我们', '/page/contact?lang=zh-CN', 30],
        ];
        $en = [
            ['header', 'Home', '/?lang=en-US', 10],
            ['header', 'Products', '/page/products?lang=en-US', 20],
            ['header', 'Insights', '/page/news?lang=en-US', 30],
            ['header', 'About', '/page/about?lang=en-US', 40],
            ['header', 'Contact', '/page/contact?lang=en-US', 50],
            ['footer', 'Products', '/page/products?lang=en-US', 10],
            ['footer', 'Insights', '/page/news?lang=en-US', 20],
            ['footer', 'Contact', '/page/contact?lang=en-US', 30],
        ];

        foreach ($zh as [$position, $title, $url, $sort]) {
            $this->insertNavigation('zh-CN', $position, $title, $url, $sort);
        }
        foreach ($en as [$position, $title, $url, $sort]) {
            $this->insertNavigation('en-US', $position, $title, $url, $sort);
        }
    }

    private function seedMenus(): void
    {
        $this->insertRootMenu('B8CMS', 'B8CMS', '/b8cms', 'ri:global-line', 90);
        $this->insertMenu('B8CMS', '内容管理', 'B8CMSContent', 'content', '/plugin/b8cms/content/index', 'ri:file-list-3-line', 100);
        $this->insertMenu('B8CMS', '语言管理', 'B8CMSLanguage', 'language', '/plugin/b8cms/language/index', 'ri:translate-2', 95);
        $this->insertMenu('B8CMS', '模板管理', 'B8CMSTemplate', 'template', '/plugin/b8cms/template/index', 'ri:layout-5-line', 90);
        $this->insertMenu('B8CMS', '导航管理', 'B8CMSNavigation', 'navigation', '/plugin/b8cms/navigation/index', 'ri:menu-4-line', 85);
        $this->insertMenu('B8CMS', '站点配置', 'B8CMSSetting', 'setting', '/plugin/b8cms/setting/index', 'ri:settings-3-line', 80);
        $this->insertMenu('B8CMS', '联系留言', 'B8CMSContact', 'contact', '/plugin/b8cms/contact/index', 'ri:message-3-line', 75);

        foreach ([
            'content' => '内容',
            'language' => '语言',
            'template' => '模板',
            'navigation' => '导航',
            'setting' => '站点配置',
        ] as $module => $label) {
            $parent = 'B8CMS' . ucfirst($module);
            $this->insertPermission($parent, $label . '列表', "b8cms:{$module}:index");
            $this->insertPermission($parent, $label . '读取', "b8cms:{$module}:read");
            $this->insertPermission($parent, $label . '添加', "b8cms:{$module}:save");
            $this->insertPermission($parent, $label . '修改', "b8cms:{$module}:update");
            $this->insertPermission($parent, $label . '删除', "b8cms:{$module}:destroy");
            $this->insertPermission($parent, $label . '状态', "b8cms:{$module}:changeStatus");
        }

        $this->insertPermission('B8CMSLanguage', '设为默认语言', 'b8cms:language:setDefault');
        $this->insertPermission('B8CMSTemplate', '启用模板', 'b8cms:template:activate');
        $this->insertPermission('B8CMSContact', '联系留言列表', 'b8cms:contact:index');
        $this->insertPermission('B8CMSContact', '联系留言读取', 'b8cms:contact:read');
        $this->insertPermission('B8CMSContact', '联系留言处理', 'b8cms:contact:handle');
        $this->insertPermission('B8CMSContact', '联系留言删除', 'b8cms:contact:destroy');
    }

    private function insertLanguage(string $code, string $name, string $nativeName, string $locale, int $isDefault, int $sort): void
    {
        $this->execute(
            'INSERT INTO `b8cms_language` (`code`, `name`, `native_name`, `locale`, `is_default`, `sort`, `status`, `remark`, `created_by`, `updated_by`, `create_time`, `update_time`, `delete_time`)
            SELECT ' . $this->q($code) . ', ' . $this->q($name) . ', ' . $this->q($nativeName) . ', ' . $this->q($locale) . ", {$isDefault}, {$sort}, 1, " . $this->q(self::REMARK) . ', 1, 1, NOW(), NOW(), NULL
            WHERE NOT EXISTS (SELECT 1 FROM `b8cms_language` WHERE `code` = ' . $this->q($code) . ' AND `delete_time` IS NULL)'
        );
    }

    private function insertSetting(string $group, string $key, string $lang, string $title, mixed $value, string $inputType, int $sort): void
    {
        $this->execute(
            'INSERT INTO `b8cms_site_setting` (`group_key`, `setting_key`, `lang_code`, `title`, `value`, `input_type`, `options`, `sort`, `status`, `remark`, `created_by`, `updated_by`, `create_time`, `update_time`, `delete_time`)
            SELECT ' . $this->q($group) . ', ' . $this->q($key) . ', ' . $this->q($lang) . ', ' . $this->q($title) . ', ' . $this->q(json_encode($value, JSON_UNESCAPED_UNICODE)) . ', ' . $this->q($inputType) . ', ' . $this->q('[]') . ", {$sort}, 1, " . $this->q(self::REMARK) . ', 1, 1, NOW(), NOW(), NULL
            WHERE NOT EXISTS (
                SELECT 1 FROM `b8cms_site_setting`
                WHERE `group_key` = ' . $this->q($group) . ' AND `setting_key` = ' . $this->q($key) . ' AND `lang_code` = ' . $this->q($lang) . ' AND `delete_time` IS NULL
            )'
        );
    }

    private function insertContent(
        string $type,
        string $lang,
        string $slug,
        string $title,
        string $subtitle,
        string $category,
        string $summary,
        string $content,
        string $coverImage,
        float $price,
        string $currency,
        int $stock,
        string $sku,
        int $sort,
        int $featured,
        string $templateFile,
        string $seoTitle,
        string $seoKeywords,
        string $seoDescription,
        string $publishedAt
    ): void {
        $this->execute(
            'INSERT INTO `b8cms_content` (`content_type`, `lang_code`, `slug`, `title`, `subtitle`, `category`, `summary`, `content`, `cover_image`, `images`, `price`, `currency`, `stock`, `sku`, `sort`, `status`, `is_featured`, `published_at`, `template_file`, `seo_title`, `seo_keywords`, `seo_description`, `extra`, `remark`, `created_by`, `updated_by`, `create_time`, `update_time`, `delete_time`)
            SELECT ' . $this->q($type) . ', ' . $this->q($lang) . ', ' . $this->q($slug) . ', ' . $this->q($title) . ', ' . $this->q($subtitle) . ', ' . $this->q($category) . ', ' . $this->q($summary) . ', ' . $this->q($content) . ', ' . $this->q($coverImage) . ', ' . $this->q('[]') . ", {$price}, " . $this->q($currency) . ", {$stock}, " . $this->q($sku) . ", {$sort}, 1, {$featured}, " . $this->q($publishedAt) . ', ' . $this->q($templateFile) . ', ' . $this->q($seoTitle) . ', ' . $this->q($seoKeywords) . ', ' . $this->q($seoDescription) . ', ' . $this->q('{}') . ', ' . $this->q(self::REMARK) . ', 1, 1, NOW(), NOW(), NULL
            WHERE NOT EXISTS (
                SELECT 1 FROM `b8cms_content`
                WHERE `content_type` = ' . $this->q($type) . ' AND `lang_code` = ' . $this->q($lang) . ' AND `slug` = ' . $this->q($slug) . ' AND `delete_time` IS NULL
            )'
        );
    }

    private function insertNavigation(string $lang, string $position, string $title, string $url, int $sort): void
    {
        $this->execute(
            'INSERT INTO `b8cms_navigation` (`parent_id`, `lang_code`, `position`, `title`, `url`, `target`, `content_type`, `content_id`, `sort`, `status`, `remark`, `created_by`, `updated_by`, `create_time`, `update_time`, `delete_time`)
            SELECT 0, ' . $this->q($lang) . ', ' . $this->q($position) . ', ' . $this->q($title) . ', ' . $this->q($url) . ', ' . $this->q('_self') . ', ' . $this->q('custom') . ", 0, {$sort}, 1, " . $this->q(self::REMARK) . ', 1, 1, NOW(), NOW(), NULL
            WHERE NOT EXISTS (
                SELECT 1 FROM `b8cms_navigation`
                WHERE `lang_code` = ' . $this->q($lang) . ' AND `position` = ' . $this->q($position) . ' AND `title` = ' . $this->q($title) . ' AND `delete_time` IS NULL
            )'
        );
    }

    private function insertRootMenu(string $name, string $code, string $path, string $icon, int $sort): void
    {
        $this->execute(
            "INSERT INTO `sa_system_menu` (`parent_id`, `name`, `code`, `slug`, `type`, `path`, `component`, `method`, `icon`, `sort`, `link_url`, `is_iframe`, `is_keep_alive`, `is_hidden`, `is_fixed_tab`, `is_full_page`, `generate_id`, `generate_key`, `status`, `remark`, `created_by`, `updated_by`, `create_time`, `update_time`, `delete_time`)
            SELECT 0, '{$name}', '{$code}', '', 1, '{$path}', '', NULL, '{$icon}', {$sort}, '', 2, 2, 2, 2, 2, 0, NULL, 1, '" . self::REMARK . "', 1, 1, NOW(), NOW(), NULL
            WHERE NOT EXISTS (SELECT 1 FROM `sa_system_menu` WHERE `code` = '{$code}' AND `delete_time` IS NULL)"
        );
    }

    private function insertMenu(string $parentCode, string $name, string $code, string $path, string $component, string $icon, int $sort): void
    {
        $this->execute(
            "INSERT INTO `sa_system_menu` (`parent_id`, `name`, `code`, `slug`, `type`, `path`, `component`, `method`, `icon`, `sort`, `link_url`, `is_iframe`, `is_keep_alive`, `is_hidden`, `is_fixed_tab`, `is_full_page`, `generate_id`, `generate_key`, `status`, `remark`, `created_by`, `updated_by`, `create_time`, `update_time`, `delete_time`)
            SELECT `id`, '{$name}', '{$code}', '', 2, '{$path}', '{$component}', NULL, '{$icon}', {$sort}, '', 2, 2, 2, 2, 2, 0, NULL, 1, '" . self::REMARK . "', 1, 1, NOW(), NOW(), NULL
            FROM `sa_system_menu`
            WHERE `code` = '{$parentCode}'
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

    private function q(mixed $value): string
    {
        return $this->getAdapter()->getConnection()->quote((string) $value);
    }
}
