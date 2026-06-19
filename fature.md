# B8 新一代 AI 友好型 Webman 集成框架规划

## 1. 定位

B8 新框架不是简单重做一个后台管理模板，而是基于 Webman 的高性能运行时，参考 SaiAdmin 的分层思想，面向 AI 辅助开发、插件生态、SaaS 多租户和企业级后台业务沉淀的一套集成框架。

核心定位：

- 以 Webman 作为运行时底座，保留常驻进程、高性能、自定义进程、事件和队列能力。
- 参考 SaiAdmin 的 Controller、Logic、Validate、Model 分层，但重新设计 B8 自己的 Service、Repository、Event、Hook、Plugin、Meta、AI Context 能力。
- 面向 AI 开发，让框架能够自解释：模型、字段、路由、权限、Hook、事件、插件依赖、数据字典和迁移状态都能导出为机器可读上下文。
- 面向插件生态，让功能可以通过 Composer 插件、纯前端组件、全栈插件和插件商城进行安装、启用、升级、回滚和治理。
- 面向 SaaS，让租户、权限、套餐、额度、插件授权、数据隔离和审计成为框架级能力。

## 2. 设计原则

- 分层清晰：Controller 不写业务，Logic 负责业务用例，Repository 负责数据访问，Service 负责跨模块能力，Validate 负责场景化校验。
- 事件驱动：业务事实通过事件分发，跨模块联动优先走事件，不在 Logic 中硬编码串联。
- Hook 可扩展：为插件预留明确扩展点，允许插件以 Action 或 Filter 方式插入逻辑，但不允许无边界修改任意运行中方法。
- 元数据驱动：模型注释、字段注解、表关系、字典、表单、列表、权限、模拟数据和 OpenAPI 都从统一元数据生成。
- AI 可理解：框架默认提供 docs、skills 和 `.ai` 结构化上下文，让 AI 能基于真实运行信息开发，而不是猜测。
- 可观测优先：请求、SQL、业务 span、trace_id、队列、任务、插件安装、Hook 执行都要可追踪。
- 数据演进统一：首装可以有基线 SQL，升级必须以 Phinx 迁移为准，不维护多套 SQL patch 流程。
- 插件可治理：安装前做依赖解析、版本兼容、影响分析、权限提示和 dry-run，安装后可审计、可禁用、可升级、可回滚。

## 3. 总体架构

```text
B8 Framework
├── b8-core            核心基类、响应、异常、上下文、helper
├── b8-auth            登录、RBAC、ABAC、Policy、DataScope
├── b8-saas            租户、套餐、额度、域名、租户配置
├── b8-plugin          插件生命周期、依赖解析、Hook 注册、事件监听
├── b8-marketplace     插件商城、授权、下载、签名、版本治理
├── b8-event           领域事件、系统事件、异步事件、事件订阅
├── b8-hook            Action Hook、Filter Hook、Hook 契约和调试
├── b8-aop             事务、trace、缓存、权限等框架级切面
├── b8-meta            模型元数据、字段注解、表关系、数据字典
├── b8-faker           模拟数据、场景数据、演示数据、幂等清理
├── b8-generator       CRUD、迁移、OpenAPI、前端页面、插件骨架生成
├── b8-observability   trace、日志、SQL、队列、任务、运行时诊断
├── b8-admin           管理端 UI、组件体系、菜单、权限页面
├── b8-release         Docker、二进制、静态资源、发布检查
└── b8-ai              AI 上下文导出、AI 技能、AI 任务协议
```

## 4. 后端分层

推荐分层：

```text
Controller
  -> Validate
  -> Logic
      -> Repository
          -> Model / Db
      -> Service
      -> Event / Hook
```

### Controller

只负责请求入口：

- 接收请求参数。
- 调用 Validate。
- 调用 Logic。
- 返回统一响应。
- 标注权限、OpenAPI 和 Hook Point 入口信息。

不在 Controller 中写复杂查询、事务、业务流程、第三方 API 调用。

### Validate

负责参数合法性：

- 支持 `save`、`update`、`delete`、`status`、`import`、`export` 等场景。
- 支持从字段元数据和数据库注释生成默认规则。
- 支持输出 OpenAPI 参数 schema。
- 支持 AI 读取字段要求，减少生成错误。

### Logic

负责业务用例：

- 一个 Logic 对应一个业务模块或聚合根。
- 处理业务流程、状态流转、事务、数据权限、回滚判断。
- 可以调用 Repository、Service、Event、Hook。
- 默认提供 CRUD 能力，也允许业务覆写。

建议 BaseLogic 标准方法：

```text
init()
search()
getList()
getAll()
read()
add()
edit()
destroy()
restore()
rollback()
transaction()
beforeSave()
afterSave()
beforeDelete()
afterDelete()
```

### Repository

Repository 是数据访问层，负责封装查询和持久化细节。

适合放：

- 复杂查询。
- 多表 join。
- 关联查询。
- 缓存读取。
- 租户过滤。
- 分库分表。
- 统计查询。
- 常用查询条件。

不适合放：

- 业务流程。
- 状态流转。
- 权限决策。
- 第三方调用。
- Controller 响应。

简单 CRUD 可以不强制 Repository，复杂模块如订单、支付、权限、租户、插件商城必须引入 Repository。

### Service

Service 负责跨模块能力：

- 邮件、短信、支付、文件存储、AI、通知、导出、OpenAPI、队列、trace、权限解析。
- 服务可以被多个 Logic 调用。
- 服务不应该绑定某一个 Controller。
- 服务要支持配置、日志、trace、异常和事件。

### Model

Model 表达数据结构：

- 表名、主键、字段注释。
- 字段类型、搜索器、关联关系。
- 字典绑定、表单类型、列表显示规则。
- 租户字段、审计字段、版本字段。
- faker 规则、OpenAPI schema。

模型默认必须带注释和元数据，不允许生成无说明的空模型。

## 5. 统一响应与帮助函数

公开帮助函数只保留稳定、通用、无业务歧义的能力：

```text
ok()
fail()
page()
trace_id()
current_user()
current_admin_id()
tenant_id()
request_context()
mask_secret()
plugin_path()
config_value()
```

内部实现函数使用 B8 前缀：

```text
b8_json_response()
b8_trace_id()
b8_mask_secret()
b8_tenant_context()
```

新业务响应统一为：

```json
{
  "code": 200,
  "message": "success",
  "data": {},
  "trace_id": "optional"
}
```

`trace_id` 只有在当前请求存在有效 trace 上下文时追加。

## 6. Event、Hook 和 AOP

### Event

Event 表示某件事已经发生。

事件分两类：

```text
Domain Event  业务事实，如 UserRegistered、OrderPaid、PluginInstalled
System Event  框架事件，如 request.received、model.created、plugin.enabled
```

适合用 Event 的场景：

- 用户注册后发欢迎邮件。
- 支付成功后开通权益。
- 租户创建后初始化默认配置。
- 插件启用后刷新菜单、权限、Hook 注册表。
- 业务变更后写审计日志。

事件支持同步和异步：

```text
Event::dispatch(new UserRegistered($user));
Event::dispatchAsync(new OrderPaid($order));
```

### Hook

Hook 是插件扩展点，类似 WordPress 的插件注入机制，但必须可声明、可审计、可调试。

Hook 分两类：

```text
Action Hook  只执行副作用，不改变原值
Filter Hook  接收原值，允许插件修改后返回
```

示例：

```php
Hook::do('user.created.after', $user, $context);

$price = Hook::filter('order.price.calculated', $price, $order, $context);
```

插件通过 manifest 注册 Hook：

```json
{
  "hooks": [
    {
      "point": "user.created.after",
      "type": "action",
      "listener": "plugin\\vip\\listener\\GrantTrialVip",
      "priority": 100
    },
    {
      "point": "order.price.calculated",
      "type": "filter",
      "listener": "plugin\\coupon\\listener\\ApplyCoupon",
      "priority": 50
    }
  ]
}
```

Hook Point 必须有契约：

```php
#[HookPoint(
    name: 'order.price.calculated',
    type: 'filter',
    payload: OrderPricePayload::class,
    returns: Money::class
)]
```

框架命令：

```bash
php webman b8:hook:list
php webman b8:hook:inspect order.price.calculated
php webman b8:plugin:hooks coupon
```

### AOP

AOP 用于框架级横切能力，不建议完全开放给普通第三方插件。

适合 AOP 的场景：

- 事务。
- trace。
- 权限检查。
- 缓存。
- 限流。
- 日志。

示例：

```php
#[Transactional]
#[Trace]
#[Permission('order:pay')]
public function pay(int $orderId)
{
}
```

设计边界：

- Event 表示事情已经发生。
- Hook 为插件预留业务扩展点。
- AOP 为框架织入通用横切能力。
- 第三方插件优先使用 Hook，不允许随便拦截任意运行中方法。

## 7. 插件体系和插件商城

### 插件类型

插件分三类：

```text
backend    后端 Composer 插件
frontend   纯前端组件或页面插件
fullstack  后端 + 前端 + 数据库 + 菜单权限的全栈插件
```

### 插件 manifest

每个插件必须提供 manifest：

```json
{
  "name": "b8/coupon",
  "title": "优惠券插件",
  "type": "fullstack",
  "version": "1.2.0",
  "requires": {
    "framework": "^1.0",
    "php": ">=8.3",
    "plugins": {
      "b8/user": "^1.0",
      "b8/order": "^1.0",
      "b8/payment": "^1.1"
    },
    "composer": {
      "moneyphp/money": "^4.0"
    },
    "frontend": {
      "@b8/coupon-admin": "^1.0"
    }
  },
  "conflicts": {
    "b8/legacy-coupon": "*"
  },
  "optional": {
    "b8/notification": "安装后可发送优惠券通知"
  },
  "provides": [
    "coupon.provider",
    "order.discount.filter"
  ],
  "backend": {
    "composer": "b8/coupon",
    "migrations": true,
    "routes": true
  },
  "frontend": {
    "package": "@b8/coupon-admin",
    "components": true,
    "pages": true
  },
  "permissions": true,
  "tenantAware": true
}
```

### 安装预检

安装前必须展示：

```text
将安装：优惠券插件 b8/coupon 1.2.0

必需依赖：
- 用户插件 b8/user >=1.0 已安装
- 订单插件 b8/order >=1.0 已安装
- 支付插件 b8/payment >=1.1 未安装，将自动安装

可选依赖：
- 通知插件 b8/notification 未安装，跳过通知能力

将执行：
- composer require b8/coupon
- pnpm add @b8/coupon-admin
- 执行 3 个迁移
- 注册 2 个菜单
- 注册 8 个权限
- 注册 4 个 Hook
- 注册 2 个事件监听器
- Webman reload
```

### 插件命令

```bash
php webman b8:plugin:list
php webman b8:plugin:deps b8/coupon
php webman b8:plugin:graph
php webman b8:plugin:install b8/coupon --dry-run
php webman b8:plugin:install b8/coupon
php webman b8:plugin:enable coupon
php webman b8:plugin:disable coupon
php webman b8:plugin:upgrade coupon
php webman b8:plugin:rollback coupon
php webman b8:plugin:remove coupon
```

### 插件安全

插件商城必须支持：

- 插件包签名。
- 来源校验。
- Composer 包白名单。
- 前端包完整性校验。
- 危险权限声明。
- 安装前权限提示。
- 插件审计日志。
- 离线授权。
- 按租户授权。
- 按域名授权。
- 企业版授权。

### Composer 后端安装

后端插件安装不依赖脆弱的 Composer post-install 魔法，核心流程由 B8 命令接管：

```text
1. 获取插件信息
2. 检查版本兼容
3. 检查许可证和购买状态
4. composer require
5. 读取插件 manifest
6. 执行迁移 dry-run
7. 执行迁移
8. 注册菜单
9. 注册权限
10. 注册 Hook
11. 注册事件监听器
12. 发布配置
13. 发布静态资源
14. 清缓存
15. reload 或 restart Webman
16. 写入插件安装记录
```

### 纯前端组件安装

纯前端组件安装命令：

```bash
pnpm b8 add table-pro
pnpm b8 add upload-plus
pnpm b8 add dashboard-sales
```

组件类型：

```text
component  组件
block      页面区块
page       完整页面
theme      主题
schema     低代码表单 schema
```

纯前端组件不能偷偷改后端、不能偷偷加菜单权限。需要菜单、权限、接口、迁移时，应升级为 fullstack 插件。

## 8. RBAC + ABAC + DataScope 权限

权限设计分三层：

```text
RBAC       决定有没有入口权限
ABAC       决定在什么条件下能不能操作资源
DataScope  决定能看到哪些数据
```

RBAC：

```text
用户 -> 角色 -> 权限
角色 -> 菜单
角色 -> 按钮
角色 -> API
```

ABAC：

```text
主体属性：用户、角色、部门、岗位、租户
资源属性：所属人、部门、租户、状态
动作属性：read、create、update、delete、audit
环境属性：时间、IP、设备、来源、应用
```

权限判断：

```php
$auth->can($user, 'order:update', $order, [
    'tenant_id' => tenant_id(),
    'ip' => $request->getRealIp(),
]);
```

权限点命名：

```text
插件:模块:功能:动作
```

例如：

```text
mall:order:list
mall:order:update
mall:order:revision
mall:order:rollback
```

## 9. SaaS 多租户

基础表：

```text
b8_tenant
b8_tenant_user
b8_tenant_role
b8_tenant_config
b8_tenant_package
b8_tenant_plugin
b8_tenant_domain
b8_tenant_quota
b8_tenant_bill
```

业务表默认字段：

```text
tenant_id
created_by
updated_by
created_at
updated_at
deleted_at
revision_no
```

租户隔离模式：

```text
共享库共享表  tenant_id 隔离，适合 MVP
共享库分表    适合大客户或高数据量
独立库        适合私有化和强隔离
```

框架能力：

```text
TenantContext::id()
TenantContext::current()
TenantService
TenantMiddleware
TenantAwareRepository
TenantAwareMigration
```

BaseLogic 和 BaseRepository 默认注入租户过滤，避免业务开发者忘记加 `tenant_id`。

## 10. 模型元数据、注释和关联查询

模型默认带注释和字段元数据：

```php
/**
 * 用户表
 *
 * @property int $id 主键
 * @property string $mobile 手机号
 * @property string $nickname 昵称
 * @property int $status 状态：1启用 2禁用
 * @property int $tenant_id 租户ID
 */
#[ModelMeta(title: '用户', table: 'b8_user')]
class User extends BaseModel
{
    #[Field(label: '手机号', type: 'string', searchable: true, faker: 'phoneNumber')]
    protected string $mobile;

    #[Field(label: '状态', type: 'int', dict: 'user_status', table: true, form: 'radio')]
    protected int $status;
}
```

关联关系必须可声明：

```php
#[ModelMeta(title: '订单', table: 'b8_order')]
class Order extends BaseModel
{
    #[BelongsTo(User::class, foreignKey: 'user_id', label: '下单用户')]
    public function user() {}

    #[HasMany(OrderItem::class, foreignKey: 'order_id', label: '订单明细')]
    public function items() {}
}
```

基于关系元数据自动生成：

- 关联查询。
- 详情页关联面板。
- 下拉选择器。
- 数据字典。
- OpenAPI schema。
- AI 上下文。
- 低代码表单字段。

## 11. 数据字典

框架支持从数据库、迁移、模型注解、Validate、字典表合并生成数据字典。

命令：

```bash
php webman b8:dict:scan
php webman b8:dict:build
php webman b8:dict:export --format=md
php webman b8:dict:export --format=json
```

数据字典内容：

```text
表名
表说明
字段名
字段类型
字段注释
是否必填
是否搜索
是否列表显示
字典编码
关联模型
租户字段
权限字段
模拟数据规则
```

数据字典不是手写文档，而是从真实源头生成，避免文档过期。

## 12. 模拟数据和演示场景

模拟数据和模型元数据绑定：

```php
#[Field(label: '手机号', faker: 'phoneNumber')]
#[Field(label: '邮箱', faker: 'safeEmail')]
#[Field(label: '状态', dict: 'user_status', faker: 'dict')]
#[Field(label: '租户ID', faker: 'tenant')]
```

命令：

```bash
php webman b8:mock:generate user --count=100
php webman b8:mock:scenario mall-demo
php webman b8:mock:clear --marker=demo:mall
```

要求：

- 支持租户隔离。
- 支持关联数据。
- 支持场景化生成。
- 支持幂等标记。
- 支持可清理。
- 默认禁止生产环境误执行。
- 生成后可以同步写入 Phinx seed 迁移或生成独立 demo migration。

## 13. CRUD 历史版本、回退和回收站

后台 CRUD 默认支持版本能力，但允许按模型关闭。

三类能力分开：

```text
Audit Log  审计日志，记录谁在什么时候做了什么，不可修改
Revision   历史版本，记录快照，可对比和回退
Trash      回收站，处理软删除和恢复
```

版本表：

```text
b8_revision
- id
- tenant_id
- plugin
- model_class
- table_name
- record_id
- action: create/update/delete/restore/rollback
- version_no
- before_snapshot JSON
- after_snapshot JSON
- changed_fields JSON
- schema_version
- operator_id
- operator_name
- ip
- user_agent
- reason
- created_at
```

模型声明：

```php
#[Versionable(
    enabled: true,
    mode: 'snapshot',
    ignore: ['updated_at', 'last_login_ip'],
    sensitive: ['password', 'token']
)]
class Article extends BaseModel
{
}
```

CRUD 生成器自动生成：

- 历史版本按钮。
- 版本详情弹窗。
- 差异对比页面。
- 回退接口。
- 恢复删除接口。
- `revision`、`rollback`、`restore` 权限点。

回退不是抹掉历史，而是生成新的回退版本。

## 14. OpenAPI、代码生成和低代码

OpenAPI 不只是文档，应当驱动：

- 后端注解。
- 前端 API。
- uni-app SDK。
- TypeScript 类型。
- 权限清单。
- 接口测试。
- 版本差异检查。

生成器一次性生成：

```text
Controller
Logic
Repository
Service
Validate
Model
Migration
Menu
Permission
OpenAPI 注解
Hook Point
Event
前端 API
前端表格页
前端弹窗表单
数据字典
模拟数据规则
AI Context
```

生成后自动检查：

```bash
php -l
php webman route:list
php webman b8:migrate --dry-run
pnpm exec vue-tsc --noEmit
```

## 15. AI 友好目录和知识层

框架默认目录：

```text
docs/
  architecture/
  database/
  api/
  plugin/
  hook/
  event/
  deploy/
  ai/
  changelog/

skills/
  b8-crud/
  b8-plugin/
  b8-hook/
  b8-migration/
  b8-saas/
  b8-permission/
  b8-release/

.ai/
  context.json
  routes.json
  models.json
  relations.json
  hooks.json
  events.json
  permissions.json
  plugins.json
  migrations.json
```

职责：

- `docs/` 给人看。
- `skills/` 给 AI Agent 执行任务时用。
- `.ai/` 给机器直接读取。

AI 命令：

```bash
php webman b8:ai:context
php webman b8:ai:models
php webman b8:ai:routes
php webman b8:ai:hooks
php webman b8:ai:events
php webman b8:ai:plugins
php webman b8:ai:explain b8/coupon
```

AI Context 必须来自真实来源：

```text
路由表 -> routes.json
模型元数据 -> models.json
Hook 注册表 -> hooks.json
事件注册表 -> events.json
插件 manifest -> plugins.json
权限表 -> permissions.json
迁移状态 -> migrations.json
```

不要让 AI 依赖过期文档猜测当前系统。

## 16. 可观测和运行时诊断

内置诊断能力：

```bash
php webman b8:doctor
php webman b8:route:check
php webman b8:permission:check
php webman b8:migrate:status
php webman b8:plugin:list
php webman b8:openapi:build
php webman b8:hook:list
php webman b8:event:list
```

本地调试优先使用内置 trace：

```text
request -> x-trace-id -> /__trace
```

trace 应覆盖：

- 请求参数。
- 响应摘要。
- SQL。
- 业务 span。
- Hook 执行。
- 事件分发。
- 队列任务。
- 插件安装。
- 异常。

敏感信息默认脱敏：

```text
Bearer
Cookie
token
secret
password
authorization
```

## 17. 数据迁移和安装

首次安装：

```bash
cd server
php webman b8:install
```

数据库策略：

- 基线 SQL 用于首装。
- 后续升级只走 Phinx。
- 不再新增并行 SQL patch 目录。
- 迁移必须支持回滚或说明不可逆原因。
- 菜单、权限、初始化数据必须幂等。

常用命令：

```bash
php webman b8:migrate:status
php webman b8:migrate --dry-run
php webman b8:migrate
php webman b8:migrate:rollback
php webman b8:migrate:create <Name>
```

插件安装、SaaS 初始化、模拟数据生成都要优先生成迁移或可追踪的 seed 记录，避免只留下本地数据库状态。

## 18. 发布和部署

发布分层：

```text
build:bin      只负责后端二进制或 PHAR 包装
release        负责后端、前端、Database、public、env template
deploy         负责目标环境同步、备份、迁移开关、健康检查
```

生产自动迁移默认关闭，必须显式开关启用。

发布检查：

```bash
bash -n docker.sh
php -l server/app/command/B8Install.php
php webman b8:migrate:status
php webman b8:migrate --dry-run
php webman route:list
```

Webman 是常驻进程：

- 修改 PHP、路由、插件配置后需要 reload。
- 安装新的 Composer 包后需要 restart。
- 启用已安装插件且只刷新 Hook 注册表时，可以不重启，但必须可诊断。

## 19. 阶段路线图

### Phase 1：B8 Core

- BaseController、BaseLogic、BaseValidate、BaseModel。
- Repository 规范。
- ok/fail/page 响应。
- trace_id。
- Phinx wrapper。
- b8:install。
- b8:doctor。
- 模型注释和基础元数据。

### Phase 2：B8 Meta + AI

- 模型字段注解。
- 关联关系注解。
- 数据字典生成。
- AI Context 导出。
- docs/skills/.ai 目录规范。
- OpenAPI 生成。
- CRUD 生成器升级。

### Phase 3：B8 Event + Hook

- 领域事件。
- 系统事件。
- 异步事件。
- Action Hook。
- Filter Hook。
- Hook Point 契约。
- Hook 调试和可观测。
- AOP 基础能力。

### Phase 4：B8 Plugin + Marketplace

- 插件 manifest。
- 依赖解析。
- 安装 dry-run。
- Composer 插件安装。
- 前端组件安装。
- 插件商城。
- 插件授权。
- 插件安全审计。

### Phase 5：B8 SaaS + Permission

- RBAC。
- ABAC。
- DataScope。
- TenantContext。
- 租户套餐。
- 租户插件授权。
- 租户数据隔离。
- 租户审计和额度。

### Phase 6：B8 Revision + Release

- CRUD 历史版本。
- 差异对比。
- 回退。
- 回收站。
- Docker release。
- 二进制发布。
- 环境检查。
- 运维面板。

## 20. 最小可行版本建议

第一版不要一次性做全插件商城和完整 SaaS，建议先做一个能真实落地的最小闭环：

```text
1. Base 分层
2. 统一响应
3. Phinx 迁移
4. 模型元数据
5. 数据字典生成
6. CRUD 生成器
7. AI Context 导出
8. Event
9. Hook
10. 插件 manifest 和 dry-run 安装预检
```

这个闭环完成后，AI 就可以基于真实结构生成业务模块，插件也可以安全地声明依赖和扩展点。

## 21. 关键判断

B8 新框架和 SaiAdmin 的区别不应该是换 UI 或换目录，而应该是：

```text
SaiAdmin 强在后台 CRUD 和基础分层。
B8 新框架要强在 AI 可理解、插件可治理、事件可扩展、Hook 可注入、数据可追溯、SaaS 可运营。
```

最终目标是让框架自己能回答：

- 当前有哪些模型？
- 字段是什么意思？
- 有哪些路由？
- 有哪些权限？
- 有哪些 Hook 可以注入？
- 有哪些事件可以监听？
- 某插件依赖什么？
- 安装插件会改什么？
- 某条数据历史版本是什么？
- 如何生成模拟数据？
- AI 开发这个模块应该遵循什么规范？

做到这些，B8 才不是另一个后台模板，而是面向 AI 时代的 Webman 集成开发平台。
