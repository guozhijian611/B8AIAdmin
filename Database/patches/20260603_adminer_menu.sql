-- Adminer 数据库管理菜单
SET @safeguard_id := (SELECT `id` FROM `sa_system_menu` WHERE `code` = 'Safeguard' AND `delete_time` IS NULL LIMIT 1);

INSERT INTO `sa_system_menu` (`parent_id`, `name`, `code`, `slug`, `type`, `path`, `component`, `method`, `icon`, `sort`, `link_url`, `is_iframe`, `is_keep_alive`, `is_hidden`, `is_fixed_tab`, `is_full_page`, `generate_id`, `generate_key`, `status`, `remark`, `created_by`, `updated_by`, `create_time`, `update_time`, `delete_time`)
SELECT @safeguard_id, 'Adminer 数据库管理', 'Adminer', '', 2, 'adminer', '/safeguard/adminer', NULL, 'ri:database-2-line', 95, '', 2, 2, 2, 2, 2, 0, NULL, 1, '', 1, 1, NOW(), NOW(), NULL
WHERE @safeguard_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `sa_system_menu` WHERE `code` = 'Adminer' AND `delete_time` IS NULL);

SET @adminer_id := (SELECT `id` FROM `sa_system_menu` WHERE `code` = 'Adminer' AND `delete_time` IS NULL LIMIT 1);

INSERT INTO `sa_system_menu` (`parent_id`, `name`, `code`, `slug`, `type`, `path`, `component`, `method`, `icon`, `sort`, `link_url`, `is_iframe`, `is_keep_alive`, `is_hidden`, `is_fixed_tab`, `is_full_page`, `generate_id`, `generate_key`, `status`, `remark`, `created_by`, `updated_by`, `create_time`, `update_time`, `delete_time`)
SELECT @adminer_id, '打开 Adminer', '', 'core:adminer:ticket', 3, '', '', NULL, '', 100, '', 2, 2, 2, 2, 2, 0, NULL, 1, '', 1, 1, NOW(), NOW(), NULL
WHERE @adminer_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `sa_system_menu` WHERE `slug` = 'core:adminer:ticket' AND `delete_time` IS NULL);
