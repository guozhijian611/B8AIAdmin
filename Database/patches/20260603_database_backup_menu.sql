-- 数据库导入导出菜单
SET @safeguard_id := (SELECT `id` FROM `sa_system_menu` WHERE `code` = 'Safeguard' AND `delete_time` IS NULL LIMIT 1);

INSERT INTO `sa_system_menu` (`parent_id`, `name`, `code`, `slug`, `type`, `path`, `component`, `method`, `icon`, `sort`, `link_url`, `is_iframe`, `is_keep_alive`, `is_hidden`, `is_fixed_tab`, `is_full_page`, `generate_id`, `generate_key`, `status`, `remark`, `created_by`, `updated_by`, `create_time`, `update_time`, `delete_time`)
SELECT @safeguard_id, '数据库导入导出', 'DatabaseBackup', '', 2, 'database-backup', '/safeguard/database-backup', NULL, 'ri:database-2-line', 90, '', 2, 2, 2, 2, 2, 0, NULL, 1, '', 1, 1, NOW(), NOW(), NULL
WHERE @safeguard_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `sa_system_menu` WHERE `code` = 'DatabaseBackup' AND `delete_time` IS NULL);

SET @database_backup_id := (SELECT `id` FROM `sa_system_menu` WHERE `code` = 'DatabaseBackup' AND `delete_time` IS NULL LIMIT 1);

INSERT INTO `sa_system_menu` (`parent_id`, `name`, `code`, `slug`, `type`, `path`, `component`, `method`, `icon`, `sort`, `link_url`, `is_iframe`, `is_keep_alive`, `is_hidden`, `is_fixed_tab`, `is_full_page`, `generate_id`, `generate_key`, `status`, `remark`, `created_by`, `updated_by`, `create_time`, `update_time`, `delete_time`)
SELECT @database_backup_id, '列表', '', 'core:database-backup:index', 3, '', '', NULL, '', 100, '', 2, 2, 2, 2, 2, 0, NULL, 1, '', 1, 1, NOW(), NOW(), NULL
WHERE @database_backup_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `sa_system_menu` WHERE `slug` = 'core:database-backup:index' AND `delete_time` IS NULL);

INSERT INTO `sa_system_menu` (`parent_id`, `name`, `code`, `slug`, `type`, `path`, `component`, `method`, `icon`, `sort`, `link_url`, `is_iframe`, `is_keep_alive`, `is_hidden`, `is_fixed_tab`, `is_full_page`, `generate_id`, `generate_key`, `status`, `remark`, `created_by`, `updated_by`, `create_time`, `update_time`, `delete_time`)
SELECT @database_backup_id, '导出', '', 'core:database-backup:export', 3, '', '', NULL, '', 100, '', 2, 2, 2, 2, 2, 0, NULL, 1, '', 1, 1, NOW(), NOW(), NULL
WHERE @database_backup_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `sa_system_menu` WHERE `slug` = 'core:database-backup:export' AND `delete_time` IS NULL);

INSERT INTO `sa_system_menu` (`parent_id`, `name`, `code`, `slug`, `type`, `path`, `component`, `method`, `icon`, `sort`, `link_url`, `is_iframe`, `is_keep_alive`, `is_hidden`, `is_fixed_tab`, `is_full_page`, `generate_id`, `generate_key`, `status`, `remark`, `created_by`, `updated_by`, `create_time`, `update_time`, `delete_time`)
SELECT @database_backup_id, '导入', '', 'core:database-backup:import', 3, '', '', NULL, '', 100, '', 2, 2, 2, 2, 2, 0, NULL, 1, '', 1, 1, NOW(), NOW(), NULL
WHERE @database_backup_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `sa_system_menu` WHERE `slug` = 'core:database-backup:import' AND `delete_time` IS NULL);

INSERT INTO `sa_system_menu` (`parent_id`, `name`, `code`, `slug`, `type`, `path`, `component`, `method`, `icon`, `sort`, `link_url`, `is_iframe`, `is_keep_alive`, `is_hidden`, `is_fixed_tab`, `is_full_page`, `generate_id`, `generate_key`, `status`, `remark`, `created_by`, `updated_by`, `create_time`, `update_time`, `delete_time`)
SELECT @database_backup_id, '删除备份', '', 'core:database-backup:delete', 3, '', '', NULL, '', 100, '', 2, 2, 2, 2, 2, 0, NULL, 1, '', 1, 1, NOW(), NOW(), NULL
WHERE @database_backup_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `sa_system_menu` WHERE `slug` = 'core:database-backup:delete' AND `delete_time` IS NULL);
