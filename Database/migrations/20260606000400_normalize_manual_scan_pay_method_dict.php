<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class NormalizeManualScanPayMethodDict extends AbstractMigration
{
    private const DICT_TYPE_TABLE = 'sa_system_dict_type';
    private const DICT_DATA_TABLE = 'sa_system_dict_data';
    private const DICT_CODE = 'saipay_method';
    private const DICT_VALUE = 'manual_scan';
    private const DICT_LABEL = '扫码支付';
    private const DICT_COLOR = '#f59e0b';

    public function up(): void
    {
        $typeId = $this->getPayMethodTypeId();
        if (!$typeId) {
            return;
        }

        $exists = $this->fetchRow(
            "SELECT `id` FROM `" . self::DICT_DATA_TABLE . "`
            WHERE `code` = '" . self::DICT_CODE . "'
              AND `value` = '" . self::DICT_VALUE . "'
              AND `delete_time` IS NULL
            LIMIT 1"
        );

        if ($exists) {
            $id = (int)$exists['id'];
            $this->execute(
                "UPDATE `" . self::DICT_DATA_TABLE . "`
                SET `type_id` = {$typeId},
                    `label` = '" . self::DICT_LABEL . "',
                    `color` = '" . self::DICT_COLOR . "',
                    `code` = '" . self::DICT_CODE . "',
                    `sort` = 100,
                    `status` = 1,
                    `remark` = '',
                    `update_time` = NOW()
                WHERE `id` = {$id}"
            );
        } else {
            $this->execute(
                "INSERT INTO `" . self::DICT_DATA_TABLE . "` (`type_id`, `label`, `value`, `color`, `code`, `sort`, `status`, `remark`, `created_by`, `updated_by`, `create_time`, `update_time`, `delete_time`)
                VALUES ({$typeId}, '" . self::DICT_LABEL . "', '" . self::DICT_VALUE . "', '" . self::DICT_COLOR . "', '" . self::DICT_CODE . "', 100, 1, '', 1, 1, NOW(), NOW(), NULL)"
            );
        }

        $this->clearDictCache();
    }

    public function down(): void
    {
        $typeId = $this->getPayMethodTypeId();
        if (!$typeId) {
            return;
        }

        $this->execute(
            "UPDATE `" . self::DICT_DATA_TABLE . "`
            SET `sort` = 90,
                `remark` = 'phinx:20260605000200_add_manual_scan_payment',
                `update_time` = NOW()
            WHERE `type_id` = {$typeId}
              AND `code` = '" . self::DICT_CODE . "'
              AND `value` = '" . self::DICT_VALUE . "'
              AND `label` = '" . self::DICT_LABEL . "'
              AND `delete_time` IS NULL"
        );

        $this->clearDictCache();
    }

    private function getPayMethodTypeId(): ?int
    {
        if (!$this->hasTable(self::DICT_TYPE_TABLE) || !$this->hasTable(self::DICT_DATA_TABLE)) {
            return null;
        }

        $type = $this->fetchRow(
            "SELECT `id` FROM `" . self::DICT_TYPE_TABLE . "`
            WHERE `code` = '" . self::DICT_CODE . "'
              AND `delete_time` IS NULL
            LIMIT 1"
        );

        return $type ? (int)$type['id'] : null;
    }

    private function clearDictCache(): void
    {
        $this->deleteFileCache('saiadmin:dict_cache');

        try {
            if (class_exists(\plugin\saiadmin\app\cache\DictCache::class)) {
                \plugin\saiadmin\app\cache\DictCache::clear();
            }
        } catch (\Throwable) {
            // Phinx dry-run 可能未初始化 ThinkCache 驱动，上方已经优先清理文件缓存。
        }
    }

    private function deleteFileCache(string $key): void
    {
        $hash = md5($key);
        $path = dirname(__DIR__, 2) . '/server/runtime/file/' . substr($hash, 0, 2) . '/' . substr($hash, 2) . '.php';
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
