<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class EnhanceB8cmsSeoSettings extends AbstractMigration
{
    private const REMARK = 'B8CMS enhanced SEO settings seed';

    public function up(): void
    {
        if (!$this->hasTable('b8cms_site_setting')) {
            return;
        }

        foreach ($this->settings() as $setting) {
            $this->insertSetting(...$setting);
        }
    }

    public function down(): void
    {
        if (!$this->hasTable('b8cms_site_setting')) {
            return;
        }

        foreach ($this->settings() as [$group, $key, $title, $value]) {
            $this->execute(
                'DELETE FROM `b8cms_site_setting`
                WHERE `group_key` = ' . $this->q($group) . '
                  AND `setting_key` = ' . $this->q($key) . '
                  AND `lang_code` = ' . $this->q('') . '
                  AND `title` = ' . $this->q($title) . '
                  AND `value` = ' . $this->q(json_encode($value, JSON_UNESCAPED_UNICODE)) . '
                  AND `remark` = ' . $this->q(self::REMARK)
            );
        }
    }

    private function settings(): array
    {
        return [
            ['brand', 'site_url', '正式站点域名', '', 'input', 15],
            ['brand', 'legal_name', '组织法定名称', '', 'input', 16],
            ['seo', 'force_https', '强制 HTTPS', '0', 'input', 50],
            ['seo', 'canonical_host_mode', '规范域名模式', 'keep', 'input', 55],
            ['seo', 'seo_robots', '默认 Robots Meta', 'index,follow', 'input', 60],
            ['seo', 'og_image', '默认 OG 图片', '', 'image', 65],
            ['seo', 'og_site_name', 'Open Graph 站点名', '', 'input', 70],
            ['seo', 'twitter_site', 'Twitter 站点账号', '', 'input', 75],
            ['seo', 'twitter_creator', 'Twitter 默认作者', '', 'input', 80],
            ['seo', 'theme_color', '浏览器主题色', '#0f766e', 'input', 85],
            ['seo', 'business_contact_type', '结构化联系方式类型', 'customer service', 'input', 90],
        ];
    }

    private function insertSetting(string $group, string $key, string $title, string $value, string $inputType, int $sort): void
    {
        $this->execute(
            'INSERT INTO `b8cms_site_setting` (`group_key`, `setting_key`, `lang_code`, `title`, `value`, `input_type`, `options`, `sort`, `status`, `remark`, `created_by`, `updated_by`, `create_time`, `update_time`, `delete_time`)
            SELECT ' . $this->q($group) . ', ' . $this->q($key) . ', ' . $this->q('') . ', ' . $this->q($title) . ', ' . $this->q(json_encode($value, JSON_UNESCAPED_UNICODE)) . ', ' . $this->q($inputType) . ', ' . $this->q('[]') . ', ' . $sort . ', 1, ' . $this->q(self::REMARK) . ', 1, 1, NOW(), NOW(), NULL
            WHERE NOT EXISTS (
                SELECT 1 FROM `b8cms_site_setting`
                WHERE `group_key` = ' . $this->q($group) . '
                  AND `setting_key` = ' . $this->q($key) . '
                  AND `lang_code` = ' . $this->q('') . '
                  AND `delete_time` IS NULL
            )'
        );
    }

    private function q(string $value): string
    {
        return $this->getAdapter()->getConnection()->quote($value);
    }
}
