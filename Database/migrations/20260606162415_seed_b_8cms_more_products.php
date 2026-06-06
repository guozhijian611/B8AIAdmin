<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class SeedB8cmsMoreProducts extends AbstractMigration
{
    private const REMARK = 'phinx:20260606162415_seed_b_8cms_more_products';

    public function up(): void
    {
        $products = [
            [
                'slug' => 'b8cms-growth-suite',
                'sort' => 70,
                'price' => 499.00,
                'stock' => 80,
                'sku' => 'B8CMS-GROWTH',
                'template' => 'product-feature',
                'zh' => [
                    'title' => 'B8CMS 增长型独立站套件',
                    'subtitle' => '面向品牌出海的多语言内容与询盘增长方案',
                    'category' => '独立站套件',
                    'summary' => '整合多语言内容、产品展示、SEO 页面和联系表单，适合品牌官网与轻量 B2B 独立站快速上线。',
                    'content' => '<p>B8CMS 增长型独立站套件适合希望快速验证海外官网、产品目录和询盘表单的团队。</p><p>它内置多语言内容、SEO 元信息、模板切换和后台管理能力，可以作为品牌独立站的基础版本继续扩展。</p>',
                    'seo_title' => 'B8CMS 增长型独立站套件',
                    'seo_keywords' => 'B8CMS,独立站套件,多语言官网,询盘增长',
                    'seo_description' => 'B8CMS 增长型独立站套件提供多语言内容、产品展示、SEO 页面和询盘表单能力。',
                ],
                'en' => [
                    'title' => 'B8CMS Growth Suite',
                    'subtitle' => 'Multilingual content and lead capture for global brand sites',
                    'category' => 'Website Suite',
                    'summary' => 'A ready-to-extend website suite with multilingual content, product pages, SEO metadata, and inquiry forms.',
                    'content' => '<p>B8CMS Growth Suite helps teams launch brand websites, product catalogs, and inquiry flows faster.</p><p>It includes multilingual publishing, SEO metadata, template switching, and admin workflows out of the box.</p>',
                    'seo_title' => 'B8CMS Growth Suite',
                    'seo_keywords' => 'B8CMS,website suite,multilingual website,lead capture',
                    'seo_description' => 'B8CMS Growth Suite provides multilingual content, product pages, SEO metadata, and inquiry forms.',
                ],
            ],
            [
                'slug' => 'cross-border-product-catalog',
                'sort' => 80,
                'price' => 799.00,
                'stock' => 50,
                'sku' => 'B8CMS-CATALOG',
                'template' => 'product',
                'zh' => [
                    'title' => '跨境商品目录系统',
                    'subtitle' => '适合 B2B 产品展示和多语言选型页',
                    'category' => '产品目录',
                    'summary' => '支持产品分类、价格、SKU、库存和详情页 SEO，可作为跨境独立站的产品目录基础。',
                    'content' => '<p>跨境商品目录系统用于展示多 SKU 产品线、规格说明和询盘入口。</p><p>每个产品都可以独立配置标题、摘要、正文、价格、SKU 与 SEO 信息。</p>',
                    'seo_title' => '跨境商品目录系统',
                    'seo_keywords' => '跨境商品目录,B2B产品展示,产品SEO',
                    'seo_description' => '跨境商品目录系统支持多语言产品展示、SKU 管理和产品详情 SEO 设置。',
                ],
                'en' => [
                    'title' => 'Cross-border Product Catalog',
                    'subtitle' => 'Product catalog pages for B2B showcase and selection',
                    'category' => 'Product Catalog',
                    'summary' => 'A multilingual catalog foundation for categories, prices, SKUs, stock, and product-level SEO.',
                    'content' => '<p>Cross-border Product Catalog is designed for multi-SKU product lines, specification pages, and inquiry entry points.</p><p>Each product can manage title, summary, body, price, SKU, and SEO metadata independently.</p>',
                    'seo_title' => 'Cross-border Product Catalog',
                    'seo_keywords' => 'cross-border catalog,B2B product showcase,product SEO',
                    'seo_description' => 'Cross-border Product Catalog supports multilingual product pages, SKU fields, and product-level SEO.',
                ],
            ],
            [
                'slug' => 'multilingual-seo-booster',
                'sort' => 90,
                'price' => 199.00,
                'stock' => 120,
                'sku' => 'B8CMS-SEO',
                'template' => 'product',
                'zh' => [
                    'title' => '多语言 SEO 增长包',
                    'subtitle' => '为文章、页面和产品补齐搜索引擎基础信号',
                    'category' => 'SEO 工具',
                    'summary' => '提供多语言 canonical、hreflang、页面描述和关键词管理思路，降低独立站重复内容风险。',
                    'content' => '<p>多语言 SEO 增长包聚焦搜索引擎识别多语言页面时需要的基础信号。</p><p>它适合搭配 B8CMS 的文章、产品和页面管理一起使用。</p>',
                    'seo_title' => '多语言 SEO 增长包',
                    'seo_keywords' => '多语言SEO,hreflang,canonical,独立站SEO',
                    'seo_description' => '多语言 SEO 增长包帮助独立站补齐 canonical、hreflang 和页面级 SEO 元信息。',
                ],
                'en' => [
                    'title' => 'Multilingual SEO Booster',
                    'subtitle' => 'Search-friendly signals for articles, pages, and products',
                    'category' => 'SEO Tools',
                    'summary' => 'A practical SEO layer for multilingual canonical links, hreflang links, page descriptions, and keywords.',
                    'content' => '<p>Multilingual SEO Booster focuses on the signals search engines need to understand language variants.</p><p>It works together with B8CMS articles, products, and pages.</p>',
                    'seo_title' => 'Multilingual SEO Booster',
                    'seo_keywords' => 'multilingual SEO,hreflang,canonical,website SEO',
                    'seo_description' => 'Multilingual SEO Booster adds canonical, hreflang, and page-level SEO metadata to standalone sites.',
                ],
            ],
            [
                'slug' => 'inquiry-capture-crm',
                'sort' => 100,
                'price' => 299.00,
                'stock' => 100,
                'sku' => 'B8CMS-INQUIRY',
                'template' => 'product',
                'zh' => [
                    'title' => '询盘收集与跟进模块',
                    'subtitle' => '把联系表单沉淀成后台可处理的销售线索',
                    'category' => '询盘管理',
                    'summary' => '用于收集官网留言、记录来源、处理状态和跟进备注，适合独立站早期销售线索管理。',
                    'content' => '<p>询盘收集与跟进模块把前台联系表单提交沉淀到后台。</p><p>管理员可以查看客户信息、留言来源、处理状态和跟进备注。</p>',
                    'seo_title' => '询盘收集与跟进模块',
                    'seo_keywords' => '询盘表单,销售线索,独立站CRM',
                    'seo_description' => '询盘收集与跟进模块帮助独立站收集联系表单并在后台处理销售线索。',
                ],
                'en' => [
                    'title' => 'Inquiry Capture CRM',
                    'subtitle' => 'Turn contact form submissions into manageable sales leads',
                    'category' => 'Lead Management',
                    'summary' => 'Capture website messages, source data, statuses, and follow-up notes for early-stage sales workflows.',
                    'content' => '<p>Inquiry Capture CRM stores contact form submissions in the admin backend.</p><p>Operators can review customer details, source information, status, and follow-up notes.</p>',
                    'seo_title' => 'Inquiry Capture CRM',
                    'seo_keywords' => 'inquiry form,sales leads,website CRM',
                    'seo_description' => 'Inquiry Capture CRM helps standalone sites capture contact forms and manage sales leads in the backend.',
                ],
            ],
        ];

        foreach ($products as $product) {
            $this->insertProduct($product, 'zh-CN', $product['zh'], 'CNY');
            $this->insertProduct($product, 'en-US', $product['en'], 'USD');
        }
    }

    public function down(): void
    {
        $this->execute('DELETE FROM `b8cms_content` WHERE `remark` = ' . $this->q(self::REMARK));
    }

    private function insertProduct(array $product, string $lang, array $text, string $currency): void
    {
        $this->execute(
            'INSERT INTO `b8cms_content` (`content_type`, `lang_code`, `slug`, `title`, `subtitle`, `category`, `summary`, `content`, `cover_image`, `images`, `price`, `currency`, `stock`, `sku`, `sort`, `status`, `is_featured`, `published_at`, `template_file`, `seo_title`, `seo_keywords`, `seo_description`, `extra`, `remark`, `created_by`, `updated_by`, `create_time`, `update_time`, `delete_time`)
            SELECT ' . $this->q('product') . ', ' . $this->q($lang) . ', ' . $this->q($product['slug']) . ', ' . $this->q($text['title']) . ', ' . $this->q($text['subtitle']) . ', ' . $this->q($text['category']) . ', ' . $this->q($text['summary']) . ', ' . $this->q($text['content']) . ', ' . $this->q('') . ', ' . $this->q('[]') . ', ' . (float) $product['price'] . ', ' . $this->q($currency) . ', ' . (int) $product['stock'] . ', ' . $this->q($product['sku']) . ', ' . (int) $product['sort'] . ', 1, 1, ' . $this->q('2026-06-07 00:24:15') . ', ' . $this->q($product['template']) . ', ' . $this->q($text['seo_title']) . ', ' . $this->q($text['seo_keywords']) . ', ' . $this->q($text['seo_description']) . ', ' . $this->q('{}') . ', ' . $this->q(self::REMARK) . ', 1, 1, NOW(), NOW(), NULL
            WHERE NOT EXISTS (
                SELECT 1 FROM `b8cms_content`
                WHERE `content_type` = ' . $this->q('product') . ' AND `lang_code` = ' . $this->q($lang) . ' AND `slug` = ' . $this->q($product['slug']) . ' AND `delete_time` IS NULL
            )'
        );
    }

    private function q(mixed $value): string
    {
        return $this->getAdapter()->getConnection()->quote((string) $value);
    }
}
