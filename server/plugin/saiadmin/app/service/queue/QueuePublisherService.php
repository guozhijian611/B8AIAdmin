<?php
// +----------------------------------------------------------------------
// | saiadmin [ saiadmin快速开发框架 ]
// +----------------------------------------------------------------------
// | Author: sai <1430792918@qq.com>
// +----------------------------------------------------------------------
namespace plugin\saiadmin\app\service\queue;

use plugin\saiadmin\app\model\tool\QueueConfig;
use plugin\saiadmin\app\model\tool\QueueTask;
use plugin\saiadmin\exception\ApiException;
use plugin\saiadmin\process\queue\RabbitmqQueueConsumer;
use Webman\RedisQueue\Redis;
use function Workbunny\WebmanRabbitMQ\publish;

/**
 * 队列任务投递服务
 */
class QueuePublisherService
{
    public function dispatch(
        int $configId,
        object|string $class,
        string $method,
        array $arguments = [],
        int $delay = 0,
        string $source = 'saiadmin'
    ): int {
        $config = QueueConfig::findOrEmpty($configId);
        if ($config->isEmpty() || (int) $config->status !== 1) {
            throw new ApiException('队列配置不存在或未启用');
        }
        if (($config->message_mode ?? 'internal_job') !== 'internal_job') {
            throw new ApiException('外部消息队列不能投递内部任务');
        }

        $className = is_object($class) ? get_class($class) : $class;
        if ($className === '' || !class_exists($className)) {
            throw new ApiException('类不存在：' . $className);
        }
        if ($method === '' || !method_exists($className, $method)) {
            throw new ApiException('类（' . $className . '）不存在方法：' . $method);
        }

        $task = QueueTask::create([
            'config_id' => $config->id,
            'driver' => $config->driver,
            'connections' => $config->connection,
            'name' => $config->queue_name,
            'class_name' => $className,
            'method_name' => $method,
            'routing_key' => $config->routing_key,
            'delay' => $delay,
            'request' => json_encode([
                'class' => $className,
                'method' => $method,
                'arguments' => $arguments,
            ], JSON_UNESCAPED_UNICODE),
            'source' => $source,
            'status' => 0,
        ]);

        try {
            $this->publishTask($task, $config, $delay);
        } catch (\Throwable $e) {
            $task->status = 3;
            $task->response = json_encode([
                'code' => $e->getCode(),
                'msg' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
            $task->save();
            throw $e;
        }

        return (int) $task->id;
    }

    public function retry(int $id): bool
    {
        $task = QueueTask::findOrEmpty($id);
        if ($task->isEmpty()) {
            throw new ApiException('队列任务不存在');
        }
        if ((int) $task->status === 1) {
            throw new ApiException('消费中的任务不能重试');
        }
        $config = QueueConfig::findOrEmpty((int) $task->config_id);
        if ($config->isEmpty() || (int) $config->status !== 1) {
            throw new ApiException('队列配置不存在或未启用');
        }
        if (($config->message_mode ?? 'internal_job') !== 'internal_job') {
            throw new ApiException('外部消息队列不能重试内部任务');
        }

        $task->status = 0;
        $task->response = '';
        $task->io = '';
        $task->save();
        $this->publishTask($task, $config, (int) $task->delay);
        return true;
    }

    private function publishTask(QueueTask $task, QueueConfig $config, int $delay): void
    {
        if ($config->driver === 'redis') {
            Redis::connection((string) $config->connection)->send((string) $config->queue_name, ['id' => (int) $task->id], $delay);
            return;
        }

        if ($config->driver !== 'rabbitmq') {
            throw new ApiException('不支持的队列驱动：' . $config->driver);
        }

        $builder = new RabbitmqQueueConsumer((int) $config->id);
        $headers = $builder->getBuilderConfig()->getHeaders();
        $headers['queue_task_id'] = (int) $task->id;
        if ((int) $config->is_delayed === 1) {
            $headers['x-delay'] = max(1, $delay * 1000);
        } elseif ($delay > 0) {
            throw new ApiException('当前 RabbitMQ 队列不是延迟队列，不能设置延迟时间');
        }

        publish(
            $builder,
            json_encode(['id' => (int) $task->id], JSON_UNESCAPED_UNICODE),
            (string) $config->routing_key,
            $headers
        );
    }
}
