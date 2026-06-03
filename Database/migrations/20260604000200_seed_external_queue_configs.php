<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class SeedExternalQueueConfigs extends AbstractMigration
{
    private const REMARK = 'phinx:20260604000200_seed_external_queue_configs';

    public function up(): void
    {
        if (
            !$this->hasTable('sa_tool_queue_config')
            || !$this->table('sa_tool_queue_config')->hasColumn('message_mode')
        ) {
            return;
        }

        $this->insertExternalQueueConfig(
            'Redis外部消息队列',
            'redis',
            'external_queue',
            '',
            'direct',
            '',
            1,
            60
        );
        $this->insertExternalQueueConfig(
            'RabbitMQ外部消息队列',
            'rabbitmq',
            'external_queue',
            'saiadmin.external',
            'direct',
            'external_queue',
            2,
            50
        );
    }

    public function down(): void
    {
        if (!$this->hasTable('sa_tool_queue_config')) {
            return;
        }

        $this->execute("DELETE FROM `sa_tool_queue_config` WHERE `remark` = '" . self::REMARK . "'");
    }

    private function insertExternalQueueConfig(
        string $name,
        string $driver,
        string $queueName,
        string $exchangeName,
        string $exchangeType,
        string $routingKey,
        int $status,
        int $sort
    ): void {
        $this->execute(
            "INSERT INTO `sa_tool_queue_config` (`name`, `driver`, `message_mode`, `connection`, `queue_name`, `exchange_name`, `exchange_type`, `routing_key`, `is_delayed`, `delay_mode`, `dead_letter_exchange`, `dead_letter_routing_key`, `prefetch_count`, `consumer_count`, `max_attempts`, `retry_delay_seconds`, `arguments`, `builder_class`, `sort`, `status`, `remark`, `created_by`, `updated_by`, `create_time`, `update_time`, `delete_time`)
            SELECT '{$name}', '{$driver}', 'external_message', 'default', '{$queueName}', '{$exchangeName}', '{$exchangeType}', '{$routingKey}', 2, 'none', '', '', 0, 0, 3, 5, '{}', '', {$sort}, {$status}, '" . self::REMARK . "', 1, 1, NOW(), NOW(), NULL
            WHERE NOT EXISTS (
                SELECT 1 FROM `sa_tool_queue_config`
                WHERE `driver` = '{$driver}'
                  AND `message_mode` = 'external_message'
                  AND `connection` = 'default'
                  AND `queue_name` = '{$queueName}'
                  AND `delete_time` IS NULL
            )"
        );
    }
}
