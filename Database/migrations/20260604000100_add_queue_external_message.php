<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddQueueExternalMessage extends AbstractMigration
{
    private const REMARK = 'phinx:20260604000100_add_queue_external_message';

    public function up(): void
    {
        if ($this->hasTable('sa_tool_queue_config') && !$this->table('sa_tool_queue_config')->hasColumn('message_mode')) {
            $this->execute(
                "ALTER TABLE `sa_tool_queue_config`
                ADD COLUMN `message_mode` varchar(32) NOT NULL DEFAULT 'internal_job' COMMENT '消息模式 internal_job内部任务 external_message外部消息' AFTER `driver`"
            );
        }

        $this->execute(
            "CREATE TABLE IF NOT EXISTS `sa_tool_queue_message` (
                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
                `config_id` bigint(20) unsigned DEFAULT NULL COMMENT '队列配置ID',
                `message_id` varchar(64) NOT NULL DEFAULT '' COMMENT '消息ID',
                `driver` varchar(16) NOT NULL DEFAULT 'redis' COMMENT '队列驱动',
                `connections` varchar(64) NOT NULL DEFAULT 'default' COMMENT '连接名',
                `name` varchar(128) NOT NULL DEFAULT '' COMMENT '队列名称',
                `exchange_name` varchar(128) NOT NULL DEFAULT '' COMMENT 'RabbitMQ交换机名称',
                `routing_key` varchar(128) NOT NULL DEFAULT '' COMMENT 'RabbitMQ路由键',
                `event_name` varchar(128) NOT NULL DEFAULT '' COMMENT '事件名称',
                `message_key` varchar(128) NOT NULL DEFAULT '' COMMENT '业务消息键',
                `content_type` varchar(64) NOT NULL DEFAULT 'application/json' COMMENT '内容类型',
                `delay` int(11) NOT NULL DEFAULT 0 COMMENT '延迟时间秒',
                `payload` longtext COMMENT '消息载荷JSON',
                `headers` text COMMENT '消息头JSON',
                `response` text COMMENT '发布结果',
                `status` smallint(1) NOT NULL DEFAULT 0 COMMENT '0待发布 1发布中 2已发布 3发布失败 4已取消',
                `err_num` tinyint(4) unsigned NOT NULL DEFAULT 0 COMMENT '失败次数',
                `publish_time` datetime DEFAULT NULL COMMENT '发布时间',
                `source` varchar(128) NOT NULL DEFAULT '' COMMENT '来源',
                `created_by` int(11) DEFAULT NULL COMMENT '创建者',
                `updated_by` int(11) DEFAULT NULL COMMENT '更新者',
                `create_time` datetime DEFAULT NULL COMMENT '创建时间',
                `update_time` datetime DEFAULT NULL COMMENT '修改时间',
                `delete_time` datetime DEFAULT NULL COMMENT '删除时间',
                PRIMARY KEY (`id`) USING BTREE,
                KEY `idx_config_status` (`config_id`, `status`) USING BTREE,
                KEY `idx_driver_queue_status` (`driver`, `connections`, `name`, `status`) USING BTREE,
                KEY `idx_event_name` (`event_name`) USING BTREE,
                KEY `idx_message_key` (`message_key`) USING BTREE,
                KEY `idx_create_time` (`create_time`) USING BTREE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='队列外部消息表' ROW_FORMAT=DYNAMIC"
        );

        $this->insertMenu('队列消息', 'QueueMessage', 'queue/message', '/tool/queue/message', 'ri:message-3-line', 96);
        $this->insertPermission('QueueMessage', '数据列表', 'tool:queue-message:index');
        $this->insertPermission('QueueMessage', '读取', 'tool:queue-message:read');
        $this->insertPermission('QueueMessage', '发布', 'tool:queue-message:publish');
        $this->insertPermission('QueueMessage', '重试', 'tool:queue-message:retry');
        $this->insertPermission('QueueMessage', '取消', 'tool:queue-message:cancel');
        $this->insertPermission('QueueMessage', '删除', 'tool:queue-message:destroy');
        $this->insertPermission('QueueMessage', '清理', 'tool:queue-message:clear');
    }

    public function down(): void
    {
        $this->execute("DELETE FROM `sa_system_menu` WHERE `remark` = '" . self::REMARK . "'");
        $this->execute('DROP TABLE IF EXISTS `sa_tool_queue_message`');

        if ($this->hasTable('sa_tool_queue_config') && $this->table('sa_tool_queue_config')->hasColumn('message_mode')) {
            $this->execute('ALTER TABLE `sa_tool_queue_config` DROP COLUMN `message_mode`');
        }
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
