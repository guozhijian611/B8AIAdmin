<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddSaismsSmsbaoConfig extends AbstractMigration
{
    private const TABLE = 'saisms_config';
    private const GATEWAY = 'smsbao';
    private const REMARK = 'phinx:20260603000500_add_saisms_smsbao_config';
    private const CONFIG = '{"user":"","password":"","api_key":""}';

    public function up(): void
    {
        if (!$this->hasTable(self::TABLE)) {
            return;
        }

        $this->execute(
            "INSERT INTO `" . self::TABLE . "` (`gateway`, `config_name`, `config`, `status`, `sort`, `remark`, `created_by`, `updated_by`, `create_time`, `update_time`, `delete_time`)
            SELECT '" . self::GATEWAY . "', '短信宝', '" . self::CONFIG . "', 1, 98, '" . self::REMARK . "', 1, 1, NOW(), NOW(), NULL
            WHERE NOT EXISTS (
                SELECT 1 FROM `" . self::TABLE . "`
                WHERE `gateway` = '" . self::GATEWAY . "'
                LIMIT 1
            )"
        );
    }

    public function down(): void
    {
        if (!$this->hasTable(self::TABLE)) {
            return;
        }

        $this->execute(
            "DELETE FROM `" . self::TABLE . "`
            WHERE `gateway` = '" . self::GATEWAY . "'
              AND `remark` = '" . self::REMARK . "'
              AND `config` = '" . self::CONFIG . "'"
        );
    }
}
