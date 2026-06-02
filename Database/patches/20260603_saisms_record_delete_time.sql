-- 修复 saisms_record 缺少软删除字段导致 SaiAdmin BaseModel 自动追加 delete_time 条件时报错。
-- 可重复执行：字段已存在时不会重复添加。

SET @db_name := DATABASE();
SET @patch_sql := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `saisms_record` ADD COLUMN `delete_time` datetime DEFAULT NULL COMMENT ''删除时间'' AFTER `update_time`',
        'SELECT ''saisms_record.delete_time already exists'' AS message'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'saisms_record'
      AND COLUMN_NAME = 'delete_time'
);

PREPARE stmt FROM @patch_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
