<?php
// +----------------------------------------------------------------------
// | saiadmin [ saiadmin快速开发框架 ]
// +----------------------------------------------------------------------
// | Author: sai <1430792918@qq.com>
// +----------------------------------------------------------------------
namespace plugin\saiadmin\app\service\queue;

use plugin\saiadmin\app\model\tool\QueueConfig;
use plugin\saiadmin\app\model\tool\QueueMessage;
use plugin\saiadmin\exception\ApiException;
use plugin\saiadmin\process\queue\RabbitmqQueueConsumer;
use Webman\RedisQueue\Redis;
use function Workbunny\WebmanRabbitMQ\publish;

/**
 * 外部消息投递服务
 */
class QueueMessagePublisherService
{
    public function publish(
        int $configId,
        string $eventName,
        array $payload,
        array $headers = [],
        int $delay = 0,
        string $messageKey = '',
        string $source = 'saiadmin',
        string $contentType = 'application/json'
    ): int {
        $config = $this->loadExternalConfig($configId);
        $message = QueueMessage::create([
            'config_id' => $config->id,
            'message_id' => bin2hex(random_bytes(16)),
            'driver' => $config->driver,
            'connections' => $config->connection,
            'name' => $config->queue_name,
            'exchange_name' => $config->exchange_name,
            'routing_key' => $config->routing_key,
            'event_name' => $eventName,
            'message_key' => $messageKey,
            'content_type' => $contentType ?: 'application/json',
            'delay' => $delay,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'headers' => json_encode($headers, JSON_UNESCAPED_UNICODE),
            'source' => $source,
            'status' => 0,
        ]);

        $this->publishMessage($message, $config);
        return (int) $message->id;
    }

    public function retry(int $id): bool
    {
        $message = QueueMessage::findOrEmpty($id);
        if ($message->isEmpty()) {
            throw new ApiException('队列消息不存在');
        }
        if ((int) $message->status === 1) {
            throw new ApiException('发布中的消息不能重试');
        }

        $config = $this->loadExternalConfig((int) $message->config_id);
        $message->status = 0;
        $message->response = '';
        $message->save();
        $this->publishMessage($message, $config);
        return true;
    }

    private function loadExternalConfig(int $configId): QueueConfig
    {
        $config = QueueConfig::findOrEmpty($configId);
        if ($config->isEmpty() || (int) $config->status !== 1) {
            throw new ApiException('队列配置不存在或未启用');
        }
        if (($config->message_mode ?? 'internal_job') !== 'external_message') {
            throw new ApiException('请选择外部消息队列配置');
        }
        return $config;
    }

    private function publishMessage(QueueMessage $message, QueueConfig $config): void
    {
        $message->status = 1;
        $message->save();

        try {
            $body = $this->buildBody($message);
            if ($config->driver === 'redis') {
                Redis::connection((string) $config->connection)
                    ->send((string) $config->queue_name, $body, (int) $message->delay);
            } elseif ($config->driver === 'rabbitmq') {
                $this->publishRabbitmq($message, $config, $body);
            } else {
                throw new ApiException('不支持的队列驱动：' . $config->driver);
            }

            $message->status = 2;
            $message->publish_time = date('Y-m-d H:i:s');
            $message->response = json_encode([
                'code' => 0,
                'msg' => '发布成功',
                'message_id' => $message->message_id,
            ], JSON_UNESCAPED_UNICODE);
            $message->save();
        } catch (\Throwable $e) {
            $message->status = 3;
            $message->err_num = (int) $message->err_num + 1;
            $message->response = json_encode([
                'code' => $e->getCode(),
                'msg' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
            $message->save();
            throw $e;
        }
    }

    private function publishRabbitmq(QueueMessage $message, QueueConfig $config, array $body): void
    {
        $builder = new RabbitmqQueueConsumer((int) $config->id);
        $headers = array_merge(
            $builder->getBuilderConfig()->getHeaders(),
            $this->decodeJson((string) $message->headers)
        );
        $headers['content-type'] = $message->content_type ?: 'application/json';
        $headers['queue_message_id'] = (int) $message->id;
        $headers['message_id'] = (string) $message->message_id;
        $headers['event_name'] = (string) $message->event_name;
        if ((string) $message->message_key !== '') {
            $headers['message_key'] = (string) $message->message_key;
        }

        if ((int) $config->is_delayed === 1) {
            $headers['x-delay'] = max(1, (int) $message->delay * 1000);
        } elseif ((int) $message->delay > 0) {
            throw new ApiException('当前 RabbitMQ 队列不是延迟队列，不能设置延迟时间');
        }

        publish(
            $builder,
            json_encode($body, JSON_UNESCAPED_UNICODE),
            (string) $config->routing_key,
            $headers
        );
    }

    private function buildBody(QueueMessage $message): array
    {
        return [
            'event' => (string) $message->event_name,
            'message_id' => (string) $message->message_id,
            'message_key' => (string) $message->message_key,
            'data' => $this->decodeJson((string) $message->payload),
            'headers' => $this->decodeJson((string) $message->headers),
            'source' => (string) $message->source,
            'timestamp' => date(DATE_ATOM),
        ];
    }

    private function decodeJson(string $json): array
    {
        if ($json === '') {
            return [];
        }
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }
}
