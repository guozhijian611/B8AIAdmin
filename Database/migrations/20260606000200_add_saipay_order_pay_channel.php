<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddSaipayOrderPayChannel extends AbstractMigration
{
    private const ORDER_TABLE = 'saipay_order';
    private const DICT_TYPE_TABLE = 'sa_system_dict_type';
    private const DICT_DATA_TABLE = 'sa_system_dict_data';

    public function up(): void
    {
        if ($this->hasTable(self::ORDER_TABLE) && !$this->table(self::ORDER_TABLE)->hasColumn('pay_channel')) {
            $this->table(self::ORDER_TABLE)
                ->addColumn('pay_channel', 'string', [
                    'limit' => 30,
                    'null' => true,
                    'default' => null,
                    'comment' => '实际收款渠道',
                    'after' => 'pay_type',
                ])
                ->addIndex(['pay_channel'], ['name' => 'idx_pay_channel'])
                ->update();
        }

        $this->normalizeUnipayDictValue();
    }

    public function down(): void
    {
        if ($this->hasTable(self::ORDER_TABLE) && $this->table(self::ORDER_TABLE)->hasColumn('pay_channel')) {
            $this->table(self::ORDER_TABLE)
                ->removeColumn('pay_channel')
                ->update();
        }

        $this->restoreUnipayDictValue();
    }

    private function normalizeUnipayDictValue(): void
    {
        $typeId = $this->getSaipayMethodTypeId();
        if (!$typeId) {
            return;
        }

        $unipay = $this->fetchRow(
            "SELECT `id` FROM `" . self::DICT_DATA_TABLE . "`
            WHERE `type_id` = {$typeId}
              AND `value` = 'unipay'
              AND `delete_time` IS NULL
            LIMIT 1"
        );
        if ($unipay) {
            return;
        }

        $this->execute(
            "UPDATE `" . self::DICT_DATA_TABLE . "`
            SET `value` = 'unipay', `update_time` = NOW()
            WHERE `type_id` = {$typeId}
              AND `value` = 'union'
              AND `delete_time` IS NULL"
        );
    }

    private function restoreUnipayDictValue(): void
    {
        $typeId = $this->getSaipayMethodTypeId();
        if (!$typeId) {
            return;
        }

        $this->execute(
            "UPDATE `" . self::DICT_DATA_TABLE . "`
            SET `value` = 'union', `update_time` = NOW()
            WHERE `type_id` = {$typeId}
              AND `value` = 'unipay'
              AND `label` = '银联支付'
              AND `delete_time` IS NULL"
        );
    }

    private function getSaipayMethodTypeId(): ?int
    {
        if (!$this->hasTable(self::DICT_TYPE_TABLE) || !$this->hasTable(self::DICT_DATA_TABLE)) {
            return null;
        }

        $type = $this->fetchRow(
            "SELECT `id` FROM `" . self::DICT_TYPE_TABLE . "`
            WHERE `code` = 'saipay_method'
              AND `delete_time` IS NULL
            LIMIT 1"
        );

        return $type ? (int)$type['id'] : null;
    }
}
