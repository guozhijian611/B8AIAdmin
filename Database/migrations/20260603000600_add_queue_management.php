<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddQueueManagement extends AbstractMigration
{
    private const REMARK = 'phinx:20260603000600_add_queue_management';

    public function up(): void
    {
        $this->execute(
            "CREATE TABLE IF NOT EXISTS `sa_tool_queue_config` (
                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
                `name` varchar(100) NOT NULL DEFAULT '' COMMENT '配置名称',
                `driver` varchar(16) NOT NULL DEFAULT 'redis' COMMENT '队列驱动 redis/rabbitmq',
                `connection` varchar(64) NOT NULL DEFAULT 'default' COMMENT '连接名',
                `queue_name` varchar(128) NOT NULL DEFAULT '' COMMENT '队列名称',
                `exchange_name` varchar(128) NOT NULL DEFAULT '' COMMENT 'RabbitMQ交换机名称',
                `exchange_type` varchar(32) NOT NULL DEFAULT 'direct' COMMENT 'RabbitMQ交换机类型',
                `routing_key` varchar(128) NOT NULL DEFAULT '' COMMENT 'RabbitMQ路由键',
                `is_delayed` tinyint(1) NOT NULL DEFAULT 2 COMMENT '是否延迟队列 1是 2否',
                `delay_mode` varchar(32) NOT NULL DEFAULT 'none' COMMENT '延迟模式 none/x_delay/ttl_dlx',
                `dead_letter_exchange` varchar(128) NOT NULL DEFAULT '' COMMENT '死信交换机',
                `dead_letter_routing_key` varchar(128) NOT NULL DEFAULT '' COMMENT '死信路由键',
                `prefetch_count` int(11) NOT NULL DEFAULT 0 COMMENT '预取数量',
                `consumer_count` int(11) NOT NULL DEFAULT 1 COMMENT '消费者进程数',
                `max_attempts` int(11) NOT NULL DEFAULT 3 COMMENT '最大重试次数',
                `retry_delay_seconds` int(11) NOT NULL DEFAULT 5 COMMENT '重试间隔秒数',
                `arguments` text COMMENT 'RabbitMQ扩展参数JSON',
                `builder_class` varchar(255) NOT NULL DEFAULT '' COMMENT '消费者Builder类',
                `sort` int(11) NOT NULL DEFAULT 100 COMMENT '排序',
                `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态 1启用 2禁用',
                `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
                `created_by` int(11) DEFAULT NULL COMMENT '创建者',
                `updated_by` int(11) DEFAULT NULL COMMENT '更新者',
                `create_time` datetime DEFAULT NULL COMMENT '创建时间',
                `update_time` datetime DEFAULT NULL COMMENT '修改时间',
                `delete_time` datetime DEFAULT NULL COMMENT '删除时间',
                PRIMARY KEY (`id`) USING BTREE,
                KEY `idx_driver_status` (`driver`, `status`) USING BTREE,
                KEY `idx_queue` (`driver`, `connection`, `queue_name`) USING BTREE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='队列配置表' ROW_FORMAT=DYNAMIC"
        );

        $this->execute(
            "CREATE TABLE IF NOT EXISTS `sa_tool_queue` (
                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
                `config_id` bigint(20) unsigned DEFAULT NULL COMMENT '队列配置ID',
                `driver` varchar(16) NOT NULL DEFAULT 'redis' COMMENT '队列驱动',
                `connections` varchar(64) NOT NULL DEFAULT 'default' COMMENT '连接名',
                `name` varchar(128) NOT NULL DEFAULT '' COMMENT '队列名称',
                `class_name` varchar(255) NOT NULL DEFAULT '' COMMENT '执行类',
                `method_name` varchar(128) NOT NULL DEFAULT '' COMMENT '执行方法',
                `routing_key` varchar(128) NOT NULL DEFAULT '' COMMENT 'RabbitMQ路由键',
                `run_time` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '执行时间毫秒',
                `run_memory` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '运行内存MB',
                `delay` int(11) NOT NULL DEFAULT 0 COMMENT '延迟时间秒',
                `request` text COMMENT '请求参数',
                `response` text COMMENT '返回结果',
                `io` text COMMENT 'IO日志',
                `status` smallint(1) NOT NULL DEFAULT 0 COMMENT '0待消费 1消费中 2已完成 3消费失败 4已取消',
                `err_num` tinyint(4) unsigned NOT NULL DEFAULT 0 COMMENT '消费失败次数',
                `source` varchar(128) NOT NULL DEFAULT '' COMMENT '来源',
                `created_by` int(11) DEFAULT NULL COMMENT '创建者',
                `updated_by` int(11) DEFAULT NULL COMMENT '更新者',
                `create_time` datetime DEFAULT NULL COMMENT '创建时间',
                `update_time` datetime DEFAULT NULL COMMENT '修改时间',
                `delete_time` datetime DEFAULT NULL COMMENT '删除时间',
                PRIMARY KEY (`id`) USING BTREE,
                KEY `idx_config_status` (`config_id`, `status`) USING BTREE,
                KEY `idx_driver_queue_status` (`driver`, `connections`, `name`, `status`) USING BTREE,
                KEY `idx_class_method` (`class_name`, `method_name`) USING BTREE,
                KEY `idx_create_time` (`create_time`) USING BTREE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='队列任务表' ROW_FORMAT=DYNAMIC"
        );

        $this->insertDefaultQueueConfig('Redis快速队列', 'redis', 'fast_queue', 1, 100);
        $this->insertDefaultQueueConfig('Redis慢速队列', 'redis', 'slow_queue', 1, 90);
        $this->insertDefaultQueueConfig('RabbitMQ快速队列', 'rabbitmq', 'fast_queue', 2, 80);
        $this->insertDefaultQueueConfig('RabbitMQ慢速队列', 'rabbitmq', 'slow_queue', 2, 70);

        $this->insertMenu('队列配置', 'QueueConfig', 'queue/config', '/tool/queue/config', 'ri:list-settings-line', 98);
        $this->insertPermission('QueueConfig', '数据列表', 'tool:queue-config:index');
        $this->insertPermission('QueueConfig', '读取', 'tool:queue-config:read');
        $this->insertPermission('QueueConfig', '管理', 'tool:queue-config:edit');
        $this->insertPermission('QueueConfig', '状态修改', 'tool:queue-config:status');

        $this->insertMenu('队列任务', 'QueueTask', 'queue/task', '/tool/queue/task', 'ri:list-check-3', 97);
        $this->insertPermission('QueueTask', '数据列表', 'tool:queue-task:index');
        $this->insertPermission('QueueTask', '读取', 'tool:queue-task:read');
        $this->insertPermission('QueueTask', '重试', 'tool:queue-task:retry');
        $this->insertPermission('QueueTask', '取消', 'tool:queue-task:cancel');
        $this->insertPermission('QueueTask', '删除', 'tool:queue-task:destroy');
        $this->insertPermission('QueueTask', '清理', 'tool:queue-task:clear');
    }

    public function down(): void
    {
        $this->execute("DELETE FROM `sa_system_menu` WHERE `remark` = '" . self::REMARK . "'");
        $this->execute('DROP TABLE IF EXISTS `sa_tool_queue`');
        $this->execute('DROP TABLE IF EXISTS `sa_tool_queue_config`');
    }

    private function insertDefaultQueueConfig(string $name, string $driver, string $queueName, int $status, int $sort): void
    {
        $exchangeName = $driver === 'rabbitmq' ? 'saiadmin.queue.' . $queueName : '';
        $this->execute(
            "INSERT INTO `sa_tool_queue_config` (`name`, `driver`, `connection`, `queue_name`, `exchange_name`, `exchange_type`, `routing_key`, `is_delayed`, `delay_mode`, `prefetch_count`, `consumer_count`, `max_attempts`, `retry_delay_seconds`, `arguments`, `sort`, `status`, `remark`, `created_by`, `updated_by`, `create_time`, `update_time`, `delete_time`)
            SELECT '{$name}', '{$driver}', 'default', '{$queueName}', '{$exchangeName}', 'direct', '{$queueName}', 2, 'none', 1, 1, 3, 5, '{}', {$sort}, {$status}, '" . self::REMARK . "', 1, 1, NOW(), NOW(), NULL
            WHERE NOT EXISTS (
                SELECT 1 FROM `sa_tool_queue_config`
                WHERE `driver` = '{$driver}' AND `connection` = 'default' AND `queue_name` = '{$queueName}' AND `delete_time` IS NULL
            )"
        );
    }

    private function insertMenu(string $name, string $code, string $path, string $component, string $icon, int $sort): void
    {
        $this->execute(
            "INSERT INTO `sa_system_menu` (`parent_id`, `name`, `code`, `slug`, `type`, `path`, `component`, `method`, `icon`, `sort`, `link_url`, `is_iframe`, `is_keep_alive`, `is_hidden`, `is_fixed_tab`, `is_full_page`, `generate_id`, `generate_key`, `status`, `remark`, `created_by`, `updated_by`, `create_time`, `update_time`, `delete_time`)
            SELECT `id`, '{$name}', '{$code}', '', 2, '{$path}', '{$component}', NULL, '{$icon}', {$sort}, '', 2, 2, 2, 2, 2, 0, NULL, 1, '" . self::REMARK . "', 1, 1, NOW(), NOW(), NULL
            FROM `sa_system_menu`
            WHERE `code` = 'Tool'
              AND `delete_time` IS NULL
              AND NOT EXISTS (SELECT 1 FROM `sa_system_menu` WHERE `code` = '{$code}' AND `delete_time` IS NULL)
            LIMIT 1"
        );
    }

    private function insertPermission(string $parentCode, string $name, string $slug): void
    {
        $this->execute(
            "INSERT INTO `sa_system_menu` (`parent_id`, `name`, `code`, `slug`, `type`, `path`, `component`, `method`, `icon`, `sort`, `link_url`, `is_iframe`, `is_keep_alive`, `is_hidden`, `is_fixed_tab`, `is_full_page`, `generate_id`, `generate_key`, `status`, `remark`, `created_by`, `updated_by`, `create_time`, `update_time`, `delete_time`)
            SELECT `id`, '{$name}', '', '{$slug}', 3, '', '', NULL, '', 100, '', 2, 2, 2, 2, 2, 0, NULL, 1, '" . self::REMARK . "', 1, 1, NOW(), NOW(), NULL
            FROM `sa_system_menu`
            WHERE `code` = '{$parentCode}'
              AND `delete_time` IS NULL
              AND NOT EXISTS (SELECT 1 FROM `sa_system_menu` WHERE `slug` = '{$slug}' AND `delete_time` IS NULL)
            LIMIT 1"
        );
    }
}
