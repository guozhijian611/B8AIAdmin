# 队列管理使用说明

本文档说明 B8AIadmin 后台“工具 / 队列配置”和“工具 / 队列任务”的设计、配置、投递、消费、部署和排错方式。当前队列管理同时支持 Redis 队列与 RabbitMQ 队列，任务统一记录在数据库中，后台可查看任务状态并执行重试、取消、删除和清理。

## 一、功能入口

队列管理由迁移 `Database/migrations/20260603000600_add_queue_management.php` 初始化。

后台菜单位于：

```text
工具
├── 队列配置
└── 队列任务
```

后端路由位于 `server/plugin/saiadmin/config/route.php`：

| 模块 | 路由前缀 | 说明 |
| --- | --- | --- |
| 队列配置 | `/tool/queueConfig` | 管理 Redis/RabbitMQ 队列配置。 |
| 队列任务 | `/tool/queueTask` | 查看任务、重试、取消、删除、清理已完成任务和查看统计。 |

前端页面位于：

| 页面 | 文件 |
| --- | --- |
| 队列配置 | `saiadmin-artd/src/views/tool/queue/config/index.vue` |
| 队列任务 | `saiadmin-artd/src/views/tool/queue/task/index.vue` |

后端控制器位于：

| 控制器 | 文件 |
| --- | --- |
| 队列配置 | `server/plugin/saiadmin/app/controller/tool/QueueConfigController.php` |
| 队列任务 | `server/plugin/saiadmin/app/controller/tool/QueueTaskController.php` |

## 二、核心表结构

### 1. `sa_tool_queue_config`

队列配置表。每一条启用配置会在 Webman 启动或 reload 时生成一个消费者进程配置。

| 字段 | 说明 |
| --- | --- |
| `name` | 配置名称，后台展示使用。 |
| `driver` | 队列驱动：`redis` 或 `rabbitmq`。 |
| `connection` | 连接名，默认 `default`。Redis 对应 `server/config/redis.php`，RabbitMQ 对应 `server/config/plugin/workbunny/webman-rabbitmq/connections.php`。 |
| `queue_name` | 队列名称。 |
| `exchange_name` | RabbitMQ 交换机名称，Redis 不使用。 |
| `exchange_type` | RabbitMQ 交换机类型：`direct`、`fanout`、`topic`、`header`。 |
| `routing_key` | RabbitMQ 路由键。 |
| `is_delayed` | 是否延迟队列：`1` 是，`2` 否。 |
| `delay_mode` | 延迟模式：`none`、`x_delay`、`ttl_dlx`。 |
| `dead_letter_exchange` | 死信交换机。 |
| `dead_letter_routing_key` | 死信路由键。 |
| `prefetch_count` | RabbitMQ QOS 预取数量。慢任务建议设置为 `1`。 |
| `consumer_count` | 消费者进程数量。 |
| `max_attempts` | 最大重试次数配置字段。当前失败任务不会自动按次数重投，主要用于后续扩展和人工判断。 |
| `retry_delay_seconds` | 重试间隔秒数配置字段。当前 `ttl_dlx` 模式会用于队列 TTL 参数。 |
| `arguments` | RabbitMQ 扩展参数 JSON，会合并到队列声明参数。 |
| `status` | `1` 启用，`2` 禁用。 |

迁移默认写入 4 条配置：

| 名称 | 驱动 | 队列 | 默认状态 |
| --- | --- | --- | --- |
| Redis快速队列 | `redis` | `fast_queue` | 启用 |
| Redis慢速队列 | `redis` | `slow_queue` | 启用 |
| RabbitMQ快速队列 | `rabbitmq` | `fast_queue` | 禁用 |
| RabbitMQ慢速队列 | `rabbitmq` | `slow_queue` | 禁用 |

### 2. `sa_tool_queue`

队列任务表。每次投递都会先写入该表，再向 Redis 或 RabbitMQ 发送只包含任务 ID 的消息。

| 字段 | 说明 |
| --- | --- |
| `config_id` | 对应 `sa_tool_queue_config.id`。 |
| `driver` | 任务使用的驱动。 |
| `connections` | 连接名。字段名保留复数是为了兼容旧结构。 |
| `name` | 队列名称。 |
| `class_name` | 待执行类名。 |
| `method_name` | 待执行方法名。 |
| `routing_key` | RabbitMQ 路由键。 |
| `delay` | 延迟秒数。Redis 直接传给 redis-queue；RabbitMQ 延迟队列会转换为毫秒级 `x-delay`。 |
| `request` | JSON 格式任务请求，包含 `class`、`method`、`arguments`。 |
| `response` | 任务执行返回或异常信息。 |
| `io` | 执行期上下文 IO 日志。 |
| `status` | `0` 待消费，`1` 消费中，`2` 已完成，`3` 消费失败，`4` 已取消。 |
| `err_num` | 失败次数。 |
| `source` | 来源，例如 `saiadmin`、`redis`、`rabbitmq`、`codex-test`。 |

## 三、运行流程

### 1. 投递流程

统一入口是 `server/plugin/saiadmin/app/service/queue/QueuePublisherService.php`。

投递时会先校验：

- 队列配置存在且启用。
- 执行类存在。
- 执行方法存在。

然后创建 `sa_tool_queue` 记录，再发送 broker 消息：

| 驱动 | broker 消息内容 |
| --- | --- |
| Redis | `['id' => <任务ID>]` |
| RabbitMQ | `{"id": <任务ID>}` |

也就是说，真正的业务参数不直接放进 Redis/RabbitMQ 消息体，而是存入数据库任务表。这样后台可以完整审计任务、重试任务和查看执行结果。

### 2. 消费流程

统一执行器是 `server/plugin/saiadmin/app/service/queue/QueueExecutorService.php`。

消费者收到任务 ID 后：

1. 查询 `sa_tool_queue`。
2. 如果任务不存在或状态大于 `0`，直接跳过，避免重复执行。
3. 将任务状态改为 `1` 消费中。
4. 解析 `request` 中的 `class`、`method`、`arguments`。
5. 如果是静态方法，直接调用；如果是实例方法，通过容器创建对象后调用。
6. 执行成功写入 `response`，状态改为 `2`。
7. 普通异常写入异常文件、行号、错误码，状态改为 `3`，`err_num + 1`。
8. 记录 `run_time`、`run_memory`、`io`。

当前 `ApiException` 会按成功处理并写入响应。这是为了兼容部分业务方法用 `ApiException` 表达业务返回的旧用法。

## 四、消费者进程

### 1. 动态进程生成

进程配置由 `server/plugin/saiadmin/app/service/queue/QueueProcessConfigService.php` 从 `sa_tool_queue_config` 动态生成。

Redis 进程配置入口：

```php
server/config/plugin/webman/redis-queue/process.php
```

RabbitMQ 进程配置入口：

```php
server/config/plugin/workbunny/webman-rabbitmq/process.php
```

只有满足以下条件的配置会生成消费者进程：

- `status = 1`
- `delete_time IS NULL`
- `driver` 与入口匹配

进程名格式：

```text
saiadmin_<driver>_queue_<config_id>_<queue_name>
```

`consumer_count` 决定该队列启动多少个消费者进程。

### 2. Webman reload 要求

Webman 是常驻进程。新增队列配置、修改队列名称、调整启用状态、修改消费者数量、修改 RabbitMQ exchange/routing_key 等配置后，需要 reload 或 restart 才能让消费者进程重新生成。

常用命令：

```bash
cd server
php start.php reload
```

如果进程尚未启动，则使用项目运行方式启动 Webman。

## 五、Redis 队列

Redis 连接配置在：

```text
server/config/redis.php
server/.env
server/.env.example
```

环境变量：

```env
REDIS_HOST = 127.0.0.1
REDIS_PORT = 6379
REDIS_PASSWORD = ''
REDIS_DB = 0
```

Redis 投递使用 `Webman\RedisQueue\Redis::connection($connection)->send($queueName, ['id' => $id], $delay)`。

Redis 消费者位于：

```text
server/plugin/saiadmin/process/queue/RedisQueueConsumer.php
```

后台统计中 Redis 支持读取 broker 侧等待数：

| 指标 | Redis key |
| --- | --- |
| waiting | `{redis-queue}-waiting<queue_name>` |
| delayed_total | `{redis-queue}-delayed` |

测试投递示例：

```bash
cd server
php -r 'require "vendor/autoload.php"; require "support/bootstrap.php"; redis_send(\plugin\saiadmin\app\cache\DictCache::class, "getDictAll", [], 0, "fast_queue");'
```

如果投递成功，`sa_tool_queue` 会新增一条待消费任务，并且 Redis waiting 数会增加。

## 六、RabbitMQ 队列

RabbitMQ 使用 `workbunny/webman-rabbitmq`。

连接配置在：

```text
server/config/plugin/workbunny/webman-rabbitmq/connections.php
server/.env
server/.env.example
```

环境变量：

```env
RABBITMQ_HOST = 127.0.0.1
RABBITMQ_PORT = 5672
RABBITMQ_VHOST = /
RABBITMQ_USERNAME = admin
RABBITMQ_PASSWORD = admin
RABBITMQ_DEBUG = false
RABBITMQ_TIMEOUT = 10
RABBITMQ_RESTART_INTERVAL = 5
```

RabbitMQ 动态消费者位于：

```text
server/plugin/saiadmin/process/queue/RabbitmqQueueConsumer.php
```

它继承 `Workbunny\WebmanRabbitMQ\Builders\QueueBuilder`，并从后台队列配置中设置：

- connection
- exchange type
- exchange name
- queue name
- routing key
- delayed
- prefetch count
- arguments
- dead-letter exchange
- dead-letter routing key
- x-message-ttl

RabbitMQ 投递使用 Workbunny helper：

```php
use function Workbunny\WebmanRabbitMQ\publish;

publish($builder, json_encode(['id' => $taskId], JSON_UNESCAPED_UNICODE), $routingKey, $headers);
```

### RabbitMQ 多队列与交换机

多队列、多交换机、多 routing key 可以通过多条“队列配置”表达。

示例：

| 场景 | 配置方式 |
| --- | --- |
| 一个 direct exchange 下多个业务队列 | 多条配置使用相同 `exchange_name`，不同 `queue_name` 和 `routing_key`。 |
| topic 模式按业务分发 | `exchange_type = topic`，不同队列配置不同 `routing_key`，例如 `order.*`、`user.registered`。 |
| fanout 广播 | `exchange_type = fanout`，多个队列配置相同 `exchange_name`，routing key 可留空或按实际兼容值填写。 |
| 不同 RabbitMQ 连接 | 在 `connections.php` 增加命名连接，然后配置表 `connection` 填对应连接名。 |

当前后台配置覆盖的是常用拓扑。更复杂的 exchange、queue、binding、header matching 或插件级策略，建议先在 RabbitMQ 管理台确认拓扑，再把能固化的参数写入配置表 `arguments` 或扩展专用 Builder。

### RabbitMQ 延迟

当前投递服务对 RabbitMQ 延迟的处理规则：

- 当 `is_delayed = 1` 时，投递会带 `x-delay` header，单位为毫秒。
- 当 `is_delayed != 1` 且传入 `delay > 0` 时，会拒绝投递。
- Workbunny 会校验：延迟 Builder 必须有 `x-delay`，普通 Builder 不能有 `x-delay`。

因此，如果要使用 `x_delay`，RabbitMQ 服务端必须安装并启用 `rabbitmq_delayed_message_exchange` 插件。

`ttl_dlx` 当前主要用于队列声明参数，会写入：

- `x-message-ttl`
- `x-dead-letter-exchange`
- `x-dead-letter-routing-key`

这适合构建 TTL + 死信交换机拓扑，但不是完整的“任意单条消息延迟”替代方案。使用前建议单独设计延迟队列、死信队列和回投队列，并在 RabbitMQ 管理台确认消息流向。

## 七、业务投递函数

全局函数位于：

```text
server/plugin/saiadmin/app/functions.php
```

### 1. 按配置 ID 投递

```php
queue_send(
    int $configId,
    object|string $class,
    string $method,
    array $arguments = [],
    int $delay = 0,
    string $source = 'saiadmin'
): bool
```

示例：

```php
queue_send(
    1,
    \plugin\saiadmin\app\cache\DictCache::class,
    'getDictAll',
    [],
    0,
    'demo'
);
```

### 2. 投递到 Redis 队列

```php
redis_send(
    object|string|null $class = null,
    string $method = '',
    array $arguments = [],
    int $delay = 0,
    string $queueName = 'fast_queue',
    string $connection = 'default'
): bool
```

示例：

```php
redis_send(
    \plugin\saiadmin\app\cache\DictCache::class,
    'getDictAll',
    [],
    0,
    'fast_queue',
    'default'
);
```

### 3. 投递到 RabbitMQ 队列

```php
rabbitmq_send(
    object|string|null $class = null,
    string $method = '',
    array $arguments = [],
    int $delay = 0,
    string $queueName = 'fast_queue',
    string $connection = 'default'
): bool
```

示例：

```php
rabbitmq_send(
    \plugin\saiadmin\app\cache\DictCache::class,
    'getDictAll',
    [],
    0,
    'fast_queue',
    'default'
);
```

注意：`rabbitmq_send()` 只会查找已启用的 RabbitMQ 队列配置。默认 RabbitMQ 配置是禁用状态，使用前需要在后台启用并 reload Webman。

## 八、任务管理操作

后台“队列任务”支持：

| 操作 | 说明 |
| --- | --- |
| 查看列表 | 按配置、驱动、连接、队列名、状态、类名、方法名、来源和创建时间筛选。 |
| 查看详情 | 查看 request、response、io、运行时间、内存和错误次数。 |
| 重试 | 将任务状态重置为待消费并重新投递。消费中的任务不能重试。 |
| 取消 | 将待消费或失败任务标记为已取消。消费中和已完成任务不能取消。 |
| 删除 | 软删除任务记录。 |
| 清理已完成 | 按配置或全局清理已完成任务。 |
| 统计 | 查看各状态数量和每个队列的任务数量。Redis 额外显示 broker waiting/delayed。 |

任务状态：

| 值 | 状态 |
| --- | --- |
| `0` | 待消费 |
| `1` | 消费中 |
| `2` | 已完成 |
| `3` | 消费失败 |
| `4` | 已取消 |

## 九、部署和升级

### 1. 数据库迁移

首次安装会导入基线并执行迁移。后续升级执行：

```bash
cd server
php webman b8:migrate:status
php webman b8:migrate --dry-run
php webman b8:migrate
```

队列管理迁移：

```text
20260603000600_add_queue_management.php
```

### 2. 环境变量

部署前确认：

- Redis 环境变量已配置。
- RabbitMQ 环境变量已配置。
- RabbitMQ 用户、vhost、权限正确。
- 如果使用 x-delay，RabbitMQ 已启用 `rabbitmq_delayed_message_exchange`。

### 3. 进程重载

数据库迁移和环境变量配置完成后，重载 Webman：

```bash
cd server
php start.php reload
```

如果修改了 `.env`，常驻进程必须重启或 reload 才能读取新值。

## 十、验证命令

### 后端语法

```bash
cd server
php -l plugin/saiadmin/app/service/queue/QueuePublisherService.php
php -l plugin/saiadmin/process/queue/RedisQueueConsumer.php
php -l plugin/saiadmin/process/queue/RabbitmqQueueConsumer.php
php -l config/plugin/workbunny/webman-rabbitmq/connections.php
```

### 路由

```bash
cd server
php webman route:list | rg "queueConfig|queueTask"
```

### 迁移状态

```bash
cd server
php webman b8:migrate:status | rg "20260603000600|AddQueueManagement"
```

### RabbitMQ 插件命令

```bash
cd server
php webman workbunny:rabbitmq-builder -h
php webman workbunny:rabbitmq-list -h
php webman workbunny:rabbitmq-remove -h
```

注意：本项目队列管理使用数据库动态生成 RabbitMQ 消费者，`workbunny:rabbitmq-list` 主要用于查看 Workbunny 生成器生成的静态 Builder，不一定会列出后台配置表中的动态队列。

### 前端类型检查

```bash
cd saiadmin-artd
pnpm -s exec vue-tsc --noEmit
```

## 十一、常见问题

### 1. 后台看不到菜单

先确认迁移已执行，再检查当前用户角色是否拥有菜单权限。必要时清理 SaiAdmin 用户菜单/权限缓存，并重新登录后台。

### 2. 投递成功但任务没有消费

检查：

- 队列配置是否启用。
- Webman 是否已 reload/restart。
- Redis 或 RabbitMQ 服务是否可连接。
- 消费者进程是否生成。
- 任务是否已被标记为取消、失败或完成。

### 3. Redis waiting 增加但状态一直是待消费

说明消息已进入 Redis broker，但消费者可能没有启动或没有订阅该队列。检查 `server/config/plugin/webman/redis-queue/process.php` 是否返回动态进程，确认 Webman reload 后进程已生效。

### 4. RabbitMQ 延迟投递失败

常见原因：

- 队列配置不是延迟队列但传入了 `delay > 0`。
- 启用了延迟队列，但 RabbitMQ 服务端未安装 `rabbitmq_delayed_message_exchange`。
- exchange 已存在但类型与当前配置不一致，RabbitMQ 不允许用不同类型重复声明同名 exchange。

### 5. 修改 RabbitMQ exchange 后仍旧报旧配置

Webman 是常驻进程，配置修改后需要 reload/restart。RabbitMQ broker 中已存在的 exchange/queue 如果参数不同，可能还需要在 RabbitMQ 管理台处理旧拓扑。

### 6. 任务失败后没有自动重试

当前实现会记录失败状态和 `err_num`，后台支持人工重试。`max_attempts` 和 `retry_delay_seconds` 已保存在配置表中，但自动按次数重试需要后续扩展调度逻辑。

### 7. 业务方法应该怎么写

队列任务可能重复消费，业务方法应满足：

- 可幂等执行。
- 参数可 JSON 序列化。
- 不依赖当前 HTTP Request。
- 对外部接口调用做好超时、重试和日志。
- 对敏感参数不要写入明文日志或 `response`。

## 十二、当前边界

- 队列配置和任务记录在数据库中，Redis/RabbitMQ 消息只保存任务 ID。
- Redis 支持后台展示 broker waiting/delayed 数量；RabbitMQ 当前后台不直接读取 broker 管理接口。
- RabbitMQ 默认配置为禁用，需要配置环境变量、启用后台队列配置并 reload Webman 后使用。
- RabbitMQ 复杂拓扑可以通过多条配置和 `arguments` 表达，超出后台表单能力的场景建议增加专用 Builder 或扩展配置字段。
- 生产环境启用新队列前，应先确认 Redis/RabbitMQ 服务、权限、vhost、延迟插件、死信拓扑和 Webman 进程重载窗口。
