<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class SeedB8cmsProductExtraParams extends AbstractMigration
{
    public function up(): void
    {
        foreach ($this->products() as $product) {
            $extra = $this->extra($product['lang'], $product['params']);
            $this->execute(
                'UPDATE `b8cms_content`
                SET `extra` = ' . $this->q($extra) . ', `update_time` = NOW()
                WHERE `content_type` = ' . $this->q('product') . '
                  AND `lang_code` = ' . $this->q($product['lang']) . '
                  AND `slug` = ' . $this->q($product['slug']) . '
                  AND `delete_time` IS NULL
                  AND (`extra` IS NULL OR `extra` = ' . $this->q('') . ' OR `extra` = ' . $this->q('{}') . ')'
            );
        }
    }

    public function down(): void
    {
        foreach ($this->products() as $product) {
            $extra = $this->extra($product['lang'], $product['params']);
            $this->execute(
                'UPDATE `b8cms_content`
                SET `extra` = ' . $this->q('{}') . ', `update_time` = NOW()
                WHERE `content_type` = ' . $this->q('product') . '
                  AND `lang_code` = ' . $this->q($product['lang']) . '
                  AND `slug` = ' . $this->q($product['slug']) . '
                  AND `delete_time` IS NULL
                  AND `extra` = ' . $this->q($extra)
            );
        }
    }

    private function products(): array
    {
        return [
            ['lang' => 'zh-CN', 'slug' => 'b8cms-starter', 'params' => ['model' => 'B8CMS-STARTER', 'lead_time' => '3 个工作日', 'min_order' => 1, 'deployment' => 'SaaS 托管', 'seo_ready' => true]],
            ['lang' => 'en-US', 'slug' => 'b8cms-starter', 'params' => ['model' => 'B8CMS-STARTER', 'lead_time' => '3 business days', 'min_order' => 1, 'deployment' => 'SaaS hosting', 'seo_ready' => true]],
            ['lang' => 'zh-CN', 'slug' => 'b8cms-growth-suite', 'params' => ['model' => 'B8CMS-GROWTH', 'lead_time' => '7 个工作日', 'min_order' => 1, 'deployment' => '混合部署', 'seo_ready' => true]],
            ['lang' => 'en-US', 'slug' => 'b8cms-growth-suite', 'params' => ['model' => 'B8CMS-GROWTH', 'lead_time' => '7 business days', 'min_order' => 1, 'deployment' => 'Hybrid deployment', 'seo_ready' => true]],
            ['lang' => 'zh-CN', 'slug' => 'cross-border-product-catalog', 'params' => ['model' => 'B8CMS-CATALOG', 'lead_time' => '10 个工作日', 'min_order' => 1, 'deployment' => '私有化部署', 'seo_ready' => true]],
            ['lang' => 'en-US', 'slug' => 'cross-border-product-catalog', 'params' => ['model' => 'B8CMS-CATALOG', 'lead_time' => '10 business days', 'min_order' => 1, 'deployment' => 'Private deployment', 'seo_ready' => true]],
            ['lang' => 'zh-CN', 'slug' => 'multilingual-seo-booster', 'params' => ['model' => 'B8CMS-SEO', 'lead_time' => '2 个工作日', 'min_order' => 1, 'deployment' => 'SaaS 托管', 'seo_ready' => true]],
            ['lang' => 'en-US', 'slug' => 'multilingual-seo-booster', 'params' => ['model' => 'B8CMS-SEO', 'lead_time' => '2 business days', 'min_order' => 1, 'deployment' => 'SaaS hosting', 'seo_ready' => true]],
            ['lang' => 'zh-CN', 'slug' => 'inquiry-capture-crm', 'params' => ['model' => 'B8CMS-INQUIRY', 'lead_time' => '5 个工作日', 'min_order' => 1, 'deployment' => '混合部署', 'seo_ready' => true]],
            ['lang' => 'en-US', 'slug' => 'inquiry-capture-crm', 'params' => ['model' => 'B8CMS-INQUIRY', 'lead_time' => '5 business days', 'min_order' => 1, 'deployment' => 'Hybrid deployment', 'seo_ready' => true]],
        ];
    }

    private function extra(string $lang, array $params): string
    {
        return json_encode([
            'product_params_schema' => $this->schema($lang),
            'product_params' => $params,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function schema(string $lang): array
    {
        if ($lang === 'en-US') {
            return [
                ['key' => 'model', 'label' => 'Model', 'type' => 'text', 'placeholder' => 'B8CMS-PRO'],
                ['key' => 'lead_time', 'label' => 'Lead Time', 'type' => 'text', 'placeholder' => '7 business days'],
                ['key' => 'min_order', 'label' => 'MOQ', 'type' => 'number', 'unit' => 'set', 'min' => 1, 'precision' => 0, 'default' => 1],
                ['key' => 'deployment', 'label' => 'Deployment', 'type' => 'select', 'options' => [
                    ['label' => 'SaaS hosting', 'value' => 'SaaS hosting'],
                    ['label' => 'Private deployment', 'value' => 'Private deployment'],
                    ['label' => 'Hybrid deployment', 'value' => 'Hybrid deployment'],
                ]],
                ['key' => 'seo_ready', 'label' => 'SEO Ready', 'type' => 'switch', 'default' => true],
            ];
        }

        return [
            ['key' => 'model', 'label' => '产品型号', 'type' => 'text', 'placeholder' => 'B8CMS-PRO'],
            ['key' => 'lead_time', 'label' => '交付周期', 'type' => 'text', 'placeholder' => '7 个工作日'],
            ['key' => 'min_order', 'label' => '起订数量', 'type' => 'number', 'unit' => '套', 'min' => 1, 'precision' => 0, 'default' => 1],
            ['key' => 'deployment', 'label' => '部署方式', 'type' => 'select', 'options' => [
                ['label' => 'SaaS 托管', 'value' => 'SaaS 托管'],
                ['label' => '私有化部署', 'value' => '私有化部署'],
                ['label' => '混合部署', 'value' => '混合部署'],
            ]],
            ['key' => 'seo_ready', 'label' => '支持 SEO', 'type' => 'switch', 'default' => true],
        ];
    }

    private function q(mixed $value): string
    {
        return $this->getAdapter()->getConnection()->quote((string) $value);
    }
}
