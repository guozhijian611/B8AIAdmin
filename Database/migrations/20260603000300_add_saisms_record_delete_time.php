<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddSaismsRecordDeleteTime extends AbstractMigration
{
    private const META_TABLE = 'phinx_migration_meta';
    private const MIGRATION = '20260603000300_add_saisms_record_delete_time';
    private const META_KEY = 'added_column:saisms_record.delete_time';

    public function up(): void
    {
        if (!$this->hasTable('saisms_record')) {
            return;
        }

        if ($this->table('saisms_record')->hasColumn('delete_time')) {
            return;
        }

        $this->ensureMetaTable();
        $this->table('saisms_record')
            ->addColumn('delete_time', 'datetime', [
                'null' => true,
                'default' => null,
                'comment' => '删除时间',
                'after' => 'update_time',
            ])
            ->update();

        $this->execute(
            "INSERT INTO `" . self::META_TABLE . "` (`migration`, `meta_key`, `meta_value`, `created_at`)
            VALUES ('" . self::MIGRATION . "', '" . self::META_KEY . "', '1', NOW())
            ON DUPLICATE KEY UPDATE `meta_value` = VALUES(`meta_value`)"
        );
    }

    public function down(): void
    {
        if (!$this->hasTable(self::META_TABLE) || !$this->hasTable('saisms_record')) {
            return;
        }

        $exists = $this->fetchRow(
            "SELECT 1 FROM `" . self::META_TABLE . "`
            WHERE `migration` = '" . self::MIGRATION . "'
              AND `meta_key` = '" . self::META_KEY . "'
            LIMIT 1"
        );

        if (!$exists || !$this->table('saisms_record')->hasColumn('delete_time')) {
            return;
        }

        $this->table('saisms_record')->removeColumn('delete_time')->update();
        $this->execute(
            "DELETE FROM `" . self::META_TABLE . "`
            WHERE `migration` = '" . self::MIGRATION . "'
              AND `meta_key` = '" . self::META_KEY . "'"
        );

        $remaining = $this->fetchRow("SELECT 1 FROM `" . self::META_TABLE . "` LIMIT 1");
        if (!$remaining) {
            $this->execute("DROP TABLE IF EXISTS `" . self::META_TABLE . "`");
        }
    }

    private function ensureMetaTable(): void
    {
        if ($this->hasTable(self::META_TABLE)) {
            return;
        }

        $this->table(self::META_TABLE, ['id' => false, 'primary_key' => ['migration', 'meta_key']])
            ->addColumn('migration', 'string', ['limit' => 191, 'null' => false])
            ->addColumn('meta_key', 'string', ['limit' => 191, 'null' => false])
            ->addColumn('meta_value', 'string', ['limit' => 191, 'null' => true])
            ->addColumn('created_at', 'datetime')
            ->create();
    }
}
