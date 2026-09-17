<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class SeedB8aiadminBrandConfig extends AbstractMigration
{
    public function up(): void
    {
        // Update sa_system_config for brand configs idempotently
        if ($this->hasTable('sa_system_config')) {
            $this->execute("UPDATE `sa_system_config` SET `value` = 'Copyright © 2026 B8AIAdmin' WHERE `key` = 'site_copyright' AND `value` = 'Copyright © 2024 saithink'");
            $this->execute("UPDATE `sa_system_config` SET `value` = 'B8AIAdmin 可配置品牌的中后台管理系统' WHERE `key` = 'site_desc' AND `value` = '基于vue3 + webman 的极速开发框架'");
            $this->execute("UPDATE `sa_system_config` SET `value` = 'B8AIAdmin,后台管理系统' WHERE `key` = 'site_keywords' AND `value` = '后台管理系统'");
            $this->execute("UPDATE `sa_system_config` SET `value` = 'B8AIAdmin' WHERE `key` = 'site_name' AND `value` = 'SaiAdmin'");
            $this->execute("UPDATE `sa_system_config` SET `value` = '' WHERE `key` = 'site_record_number' AND `value` = '9527'");

            // Insert site_logo if not exists
            $this->execute(
                "INSERT INTO `sa_system_config` (`group_id`, `key`, `value`, `name`, `input_type`, `sort`, `created_by`, `updated_by`, `create_time`, `update_time`)
                SELECT 1, 'site_logo', '', '站点Logo', 'uploadImage', 94, 1, 1, NOW(), NOW()
                WHERE NOT EXISTS (
                    SELECT 1 FROM `sa_system_config` WHERE `key` = 'site_logo'
                )"
            );

            // Insert site_favicon if not exists
            $this->execute(
                "INSERT INTO `sa_system_config` (`group_id`, `key`, `value`, `name`, `input_type`, `sort`, `created_by`, `updated_by`, `create_time`, `update_time`)
                SELECT 1, 'site_favicon', '', '站点Favicon', 'uploadImage', 93, 1, 1, NOW(), NOW()
                WHERE NOT EXISTS (
                    SELECT 1 FROM `sa_system_config` WHERE `key` = 'site_favicon'
                )"
            );
        }

        // Update sa_site_info for brand configs idempotently
        if ($this->hasTable('sa_site_info')) {
            $this->execute("UPDATE `sa_site_info` SET `site_name` = 'B8AIAdmin' WHERE `id` = 1 AND `site_name` = 'saiadmin会员系统'");
            $this->execute("UPDATE `sa_site_info` SET `site_logo` = '' WHERE `id` = 1 AND `site_logo` = 'https://saithink.top/images/logo.png'");
            $this->execute("UPDATE `sa_site_info` SET `site_desc` = 'B8AIAdmin 可配置品牌的中后台管理系统' WHERE `id` = 1 AND `site_desc` = '开箱即用的高质量中后台管理系统'");
        }
        
        // Update sa_system_user admin signature idempotently
        if ($this->hasTable('sa_system_user')) {
            $this->execute("UPDATE `sa_system_user` SET `signed` = 'B8AIAdmin 是兼具设计美学与高效开发的中后台管理系统!' WHERE `username` = 'admin' AND `signed` = 'SaiAdmin是兼具设计美学与高效开发的后台系统!'");
        }
    }

    public function down(): void
    {
        // Revert sa_system_config
        if ($this->hasTable('sa_system_config')) {
            $this->execute("UPDATE `sa_system_config` SET `value` = 'Copyright © 2024 saithink' WHERE `key` = 'site_copyright' AND `value` = 'Copyright © 2026 B8AIAdmin'");
            $this->execute("UPDATE `sa_system_config` SET `value` = '基于vue3 + webman 的极速开发框架' WHERE `key` = 'site_desc' AND `value` = 'B8AIAdmin 可配置品牌的中后台管理系统'");
            $this->execute("UPDATE `sa_system_config` SET `value` = '后台管理系统' WHERE `key` = 'site_keywords' AND `value` = 'B8AIAdmin,后台管理系统'");
            $this->execute("UPDATE `sa_system_config` SET `value` = 'SaiAdmin' WHERE `key` = 'site_name' AND `value` = 'B8AIAdmin'");
            // intentionally do not restore site_record_number to 9527

            // We intentionally don't delete site_logo/site_favicon in down() to avoid data loss if configured
        }

        // Revert sa_site_info
        if ($this->hasTable('sa_site_info')) {
            $this->execute("UPDATE `sa_site_info` SET `site_name` = 'saiadmin会员系统' WHERE `id` = 1 AND `site_name` = 'B8AIAdmin'");
            $this->execute("UPDATE `sa_site_info` SET `site_logo` = 'https://saithink.top/images/logo.png' WHERE `id` = 1 AND `site_logo` = ''");
            $this->execute("UPDATE `sa_site_info` SET `site_desc` = '开箱即用的高质量中后台管理系统' WHERE `id` = 1 AND `site_desc` = 'B8AIAdmin 可配置品牌的中后台管理系统'");
        }
        
        // Revert sa_system_user
        if ($this->hasTable('sa_system_user')) {
            $this->execute("UPDATE `sa_system_user` SET `signed` = 'SaiAdmin是兼具设计美学与高效开发的后台系统!' WHERE `username` = 'admin' AND `signed` = 'B8AIAdmin 是兼具设计美学与高效开发的中后台管理系统!'");
        }
    }
}
