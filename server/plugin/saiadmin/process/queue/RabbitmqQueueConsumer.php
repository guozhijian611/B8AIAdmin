<?php
// +----------------------------------------------------------------------
// | saiadmin [ saiadmin快速开发框架 ]
// +----------------------------------------------------------------------
// | Author: sai <1430792918@qq.com>
// +----------------------------------------------------------------------
namespace plugin\saiadmin\process\queue;

use Bunny\Message as BunnyMessage;
use plugin\saiadmin\app\model\tool\QueueConfig;
use plugin\saiadmin\app\service\queue\QueueExecutorService;
use plugin\saiadmin\app\service\queue\QueueProcessConfigService;
use plugin\saiadmin\exception\ApiException;
use Workbunny\WebmanRabbitMQ\Builders\QueueBuilder;
use Workbunny\WebmanRabbitMQ\Connection\Channel;
use Workbunny\WebmanRabbitMQ\Connection\ConnectionInterface;
use Workbunny\WebmanRabbitMQ\Constants;

/**
 * Workbunny RabbitMQ 动态队列消费者
 */
class RabbitmqQueueConsumer extends QueueBuilder
{
    protected int $configId = 0;

    protected array $queueArguments = [];

    public function __construct(int $configId)
    {
        QueueProcessConfigService::initializeOpenTelemetryContextStorage();

        $this->configId = $configId;
        $config = QueueConfig::findOrEmpty($configId);
        if ($config->isEmpty() || $config->driver !== 'rabbitmq') {
            throw new ApiException('RabbitMQ队列配置不存在');
        }

        $this->connection = (string) $config->connection;
        $this->exchangeType = $this->normalizeExchangeType((string) $config->exchange_type);
        $this->exchangeName = $config->exchange_name ?: $config->queue_name;
        $this->queueConfig = [
            'name' => (string) $config->queue_name,
            'delayed' => (int) $config->is_delayed === 1,
            'prefetch_count' => (int) $config->prefetch_count,
            'prefetch_size' => 0,
            'is_global' => false,
            'routing_key' => (string) $config->routing_key,
        ];
        $this->queueArguments = $this->buildArguments($config->toArray());

        parent::__construct();

        $arguments = array_merge($this->getBuilderConfig()->getArguments(), $this->queueArguments);
        $this->getBuilderConfig()->setArguments($arguments);
    }

    public function handler(BunnyMessage $message, Channel $channel, ConnectionInterface $connection): string
    {
        $data = json_decode((string) $message->content, true) ?: [];
        (new QueueExecutorService())->consume((int) ($data['id'] ?? 0));
        return Constants::ACK;
    }

    public static function classContent(string $namespace, string $className, bool $isDelay, string $connection = 'default'): string
    {
        return '';
    }

    private function normalizeExchangeType(string $exchangeType): string
    {
        return match ($exchangeType) {
            'fanout' => Constants::FANOUT,
            'topic' => Constants::TOPIC,
            'header', 'headers' => Constants::HEADER,
            default => Constants::DIRECT,
        };
    }

    private function buildArguments(array $config): array
    {
        $arguments = $config['arguments'] ?? [];
        if (is_string($arguments)) {
            $arguments = json_decode($arguments, true) ?: [];
        }
        if (!empty($config['dead_letter_exchange'])) {
            $arguments['x-dead-letter-exchange'] = $config['dead_letter_exchange'];
        }
        if (!empty($config['dead_letter_routing_key'])) {
            $arguments['x-dead-letter-routing-key'] = $config['dead_letter_routing_key'];
        }
        if (($config['delay_mode'] ?? '') === 'ttl_dlx' && !empty($config['retry_delay_seconds'])) {
            $arguments['x-message-ttl'] = max(1, (int) $config['retry_delay_seconds']) * 1000;
        }
        return $arguments;
    }
}
