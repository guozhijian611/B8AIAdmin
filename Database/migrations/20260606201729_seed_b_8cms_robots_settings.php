<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class SeedB8cmsRobotsSettings extends AbstractMigration
{
    private const REMARK = 'B8CMS robots settings seed';

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

        foreach ($this->settings() as [$key, $title, $value]) {
            $this->execute(
                'DELETE FROM `b8cms_site_setting`
                WHERE `group_key` = ' . $this->sqlQuote('robots') . '
                  AND `setting_key` = ' . $this->sqlQuote($key) . '
                  AND `lang_code` = ' . $this->sqlQuote('') . '
                  AND `title` = ' . $this->sqlQuote($title) . '
                  AND `value` = ' . $this->sqlQuote(json_encode($value, JSON_UNESCAPED_UNICODE)) . '
                  AND `remark` = ' . $this->sqlQuote(self::REMARK)
            );
        }
    }

    private function settings(): array
    {
        return [
            [
                'robots_rules',
                'Robots 基础规则',
                implode("\n", [
                    'User-agent: *',
                    'Allow: /',
                    'Disallow: /admin',
                    'Disallow: /app/',
                    'Disallow: /apidoc/',
                    'Disallow: /runtime/',
                ]),
                'textarea',
                10,
            ],
            [
                'robots_extra',
                'Robots 附加规则',
                '',
                'textarea',
                20,
            ],
        ];
    }

    private function insertSetting(string $key, string $title, string $value, string $inputType, int $sort): void
    {
        $this->execute(
            'INSERT INTO `b8cms_site_setting` (`group_key`, `setting_key`, `lang_code`, `title`, `value`, `input_type`, `options`, `sort`, `status`, `remark`, `created_by`, `updated_by`, `create_time`, `update_time`, `delete_time`)
            SELECT ' . $this->sqlQuote('robots') . ', ' . $this->sqlQuote($key) . ', ' . $this->sqlQuote('') . ', ' . $this->sqlQuote($title) . ', ' . $this->sqlQuote(json_encode($value, JSON_UNESCAPED_UNICODE)) . ', ' . $this->sqlQuote($inputType) . ', ' . $this->sqlQuote('[]') . ', ' . $sort . ', 1, ' . $this->sqlQuote(self::REMARK) . ', 1, 1, NOW(), NOW(), NULL
            WHERE NOT EXISTS (
                SELECT 1 FROM `b8cms_site_setting`
                WHERE `group_key` = ' . $this->sqlQuote('robots') . '
                  AND `setting_key` = ' . $this->sqlQuote($key) . '
                  AND `lang_code` = ' . $this->sqlQuote('') . '
                  AND `delete_time` IS NULL
            )'
        );
    }

    private function sqlQuote(string $value): string
    {
        return $this->getAdapter()->getConnection()->quote($value);
    }
}
