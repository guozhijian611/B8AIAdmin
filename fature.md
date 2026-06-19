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
├── b8-test            AI 测试计划、测试生成、执行、诊断、鉴权身份
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

模拟数据生成要作为框架一级能力，而不是临时写 seed 脚本。目标是让 AI、开发者和测试流程都能基于同一套模型元数据生成可用、可复现、可清理、可迁移的业务数据。

### 12.1 核心目标

模拟数据系统需要解决：

- 新模块生成后没有数据，后台页面、移动端和接口无法验证。
- AI 开发时不知道字段语义，只能随便填假值。
- 多表关联数据难以手工构造。
- SaaS 租户、权限、套餐、插件授权等复杂场景缺少演示数据。
- 临时插入的数据不可追踪，换环境后无法复现。

框架应提供 `b8-faker` 模块，统一处理：

```text
字段级 faker 规则
模型级 mock profile
场景级 mock scenario
关联数据生成
租户隔离
幂等标记
数据清理
迁移沉淀
AI 生成建议
后台可视化预览
```

### 12.2 字段级生成规则

模拟数据和模型元数据绑定：

```php
#[Field(label: '手机号', faker: 'phoneNumber')]
#[Field(label: '邮箱', faker: 'safeEmail')]
#[Field(label: '状态', dict: 'user_status', faker: 'dict')]
#[Field(label: '租户ID', faker: 'tenant')]
#[Field(label: '金额', faker: 'money:CNY,10,5000')]
#[Field(label: '头像', faker: 'image:avatar')]
#[Field(label: '介绍', faker: 'paragraph:zh_CN')]
#[Field(label: '排序', faker: 'sequence:10')]
```

字段 faker 支持：

```text
name                 姓名
mobile               手机号
email                邮箱
url                  URL
image                图片
paragraph            文本段落
dict                 从字典随机选择
enum                 从枚举随机选择
money                金额
date                 日期
datetime             时间
boolean              布尔
json                 JSON 模板
tenant               当前租户
user                 当前用户或指定角色用户
relation             关联模型
sequence             顺序递增
constant             固定值
expression           表达式
ai                   由 AI 按上下文生成
```

### 12.3 模型级 Mock Profile

每个模型可以定义默认 mock profile：

```php
#[MockProfile(
    name: 'default',
    locale: 'zh_CN',
    count: 50,
    marker: 'demo:user',
    fields: [
        'status' => 'dict:user_status',
        'nickname' => 'name',
        'avatar' => 'image:avatar',
        'tenant_id' => 'tenant',
    ]
)]
class User extends BaseModel
{
}
```

一个模型可以有多个 profile：

```text
default       默认演示数据
minimal       最小 smoke test 数据
stress        压测数据
edge          边界值数据
invalid       表单校验反例，只用于测试，不入正式库
```

### 12.4 场景级 Mock Scenario

复杂业务不要逐表生成，而要通过场景生成完整数据集。

示例场景：

```yaml
name: mall-demo
title: 商城演示数据
tenant: demo
marker: demo:mall
steps:
  - model: User
    profile: buyer
    count: 20
  - model: ProductCategory
    profile: tree
    count: 8
  - model: Product
    profile: published
    count: 100
    depends_on: ProductCategory
  - model: Order
    profile: paid
    count: 60
    depends_on:
      - User
      - Product
  - model: OrderItem
    profile: by_order
    depends_on: Order
```

场景必须支持：

- 依赖顺序。
- 外键引用。
- 树形数据。
- 一对多和多对多。
- 状态流转。
- 指定租户。
- 指定语言。
- 固定随机种子。
- 重复执行幂等。
- 按 marker 清理。

### 12.5 关联数据生成

框架根据模型关系自动生成关联数据：

```php
#[BelongsTo(User::class, foreignKey: 'user_id', label: '下单用户')]
#[MockRelation(strategy: 'existing_or_create', profile: 'buyer')]
public function user() {}

#[HasMany(OrderItem::class, foreignKey: 'order_id', label: '订单明细')]
#[MockRelation(strategy: 'create_many', min: 1, max: 5)]
public function items() {}
```

关联策略：

```text
existing           只使用已有记录
create             总是新建
existing_or_create 优先已有，没有则创建
create_many        创建多条子记录
none               不自动生成
```

### 12.6 命令设计

```bash
php webman b8:mock:generate user --count=100
php webman b8:mock:generate order --profile=paid --tenant=demo
php webman b8:mock:scenario mall-demo
php webman b8:mock:scenario mall-demo --seed=20260620
php webman b8:mock:preview mall-demo
php webman b8:mock:validate mall-demo
php webman b8:mock:clear --marker=demo:mall
php webman b8:mock:export mall-demo --format=migration
php webman b8:mock:export mall-demo --format=json
php webman b8:mock:list
php webman b8:mock:profiles user
```

命令行为：

- `generate` 直接生成数据，默认只允许本地和测试环境。
- `preview` 只预览将生成哪些表、多少行、依赖关系和危险操作。
- `validate` 检查模型、字段、字典、关联、租户和权限是否满足生成条件。
- `clear` 只清理带 marker 的模拟数据，不允许无条件清库。
- `export --format=migration` 将模拟数据沉淀为 Phinx seed 迁移。
- `export --format=json` 输出 AI 或测试可消费的数据包。

### 12.7 安全和环境防护

模拟数据必须内置安全边界：

- 支持租户隔离。
- 支持关联数据。
- 支持场景化生成。
- 支持幂等标记。
- 支持可清理。
- 默认禁止生产环境误执行。
- 生成后可以同步写入 Phinx seed 迁移或生成独立 demo migration。
- 生产环境执行必须显式 `--force --i-know-this-is-production`，并记录审计日志。
- 所有 mock 数据必须写入 `mock_marker`、`mock_scenario` 或等价追踪字段。
- 不允许覆盖真实用户数据，除非场景声明 `mode: update_demo_only`。
- 清理时必须按 marker、scenario、tenant 限定范围。

### 12.8 数据持久化和迁移沉淀

模拟数据有三种输出模式：

```text
runtime      只写当前数据库，用于本地调试
migration    生成 Phinx 迁移，用于可复现演示数据
fixture      生成 JSON/YAML fixture，用于测试和 AI
```

重要规则：

- 一次性本地验证可以用 runtime。
- 可交付演示数据必须导出 migration。
- 自动化测试建议使用 fixture。
- 迁移必须幂等，优先使用 `INSERT ... SELECT ... WHERE NOT EXISTS` 或唯一 marker。
- 结构迁移和默认数据迁移要分开，避免回滚时误删用户真实数据。

### 12.9 后台可视化

后台应提供“模拟数据中心”：

```text
场景列表
模型 profile 列表
生成预览
依赖图
生成记录
清理记录
失败日志
导出迁移
```

生成前展示：

```text
将生成 mall-demo：
- 租户：demo
- 用户：20 条
- 商品分类：8 条
- 商品：100 条
- 订单：60 条
- 订单明细：约 180 条
- marker：demo:mall
- 可清理：是
- 可导出迁移：是
```

### 12.10 AI 生成模拟数据

面向 AI 开发时，模拟数据系统还要支持 AI 辅助生成：

```bash
php webman b8:mock:ai-suggest mall/order
php webman b8:mock:ai-scenario "生成一个包含下单、支付、退款的商城演示场景"
```

AI 只能生成 scenario 草稿，最终必须经过：

```text
模型元数据校验
字典校验
外键校验
租户校验
权限校验
dry-run 预览
人工确认或测试环境自动确认
```

AI Context 需要包含：

```text
mock_profiles.json
mock_scenarios.json
faker_rules.json
field_semantics.json
relations.json
dicts.json
```

### 12.11 和测试体系集成

Mock 数据应服务测试：

```bash
php webman b8:test:seed smoke
php webman b8:test:seed permission
php webman b8:test:seed tenant-isolation
```

典型测试场景：

- CRUD smoke test。
- 权限按钮可见性。
- ABAC 资源访问。
- 租户隔离。
- 插件安装后演示数据。
- OpenAPI 示例响应。
- 前端表格和表单预览。

### 12.12 插件提供模拟数据

插件 manifest 可以声明自带 mock 场景：

```json
{
  "mock": {
    "profiles": "mock/profiles.php",
    "scenarios": [
      "mock/scenarios/mall-demo.yaml",
      "mock/scenarios/mall-smoke.yaml"
    ],
    "defaultScenario": "mall-smoke"
  }
}
```

插件安装预检时应提示：

```text
该插件提供 2 个模拟数据场景：
- mall-smoke：最小验证数据
- mall-demo：完整演示数据

是否安装后生成 smoke 数据：否，需手动执行。
```

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
Mock Profile
Mock Scenario
Fixture 示例
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
  testing/
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
  b8-test/
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
  mock_profiles.json
  mock_scenarios.json
  faker_rules.json
  fixtures.json
  tests.json
  test_identities.json
  auth_flows.json
  test_runs.json
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
php webman b8:ai:mock
php webman b8:ai:tests
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
Mock Profile -> mock_profiles.json
Mock Scenario -> mock_scenarios.json
Faker 规则 -> faker_rules.json
测试计划 -> tests.json
测试身份 -> test_identities.json
鉴权流程 -> auth_flows.json
```

不要让 AI 依赖过期文档猜测当前系统。

## 16. AI 测试体系

AI 测试不是让 AI 凭感觉判断页面是否正确，而是让 AI 基于框架事实生成测试计划、测试数据和测试代码，再交给确定性的测试执行器、断言和 trace 诊断来判定结果。

核心原则：

```text
AI 负责生成测试计划、生成测试用例、选择模拟数据、分析失败原因。
程序负责执行测试、断言结果、判定通过失败。
```

### 16.1 b8-test 模块定位

`b8-test` 是框架级测试系统，负责：

- 从 `.ai` 上下文、OpenAPI、模型元数据、权限、Hook、事件和插件 manifest 生成测试计划。
- 调用 `b8-faker` 生成测试数据和场景数据。
- 生成可执行测试，而不是只生成自然语言说明。
- 执行 API、CRUD、权限、SaaS、插件、Hook/Event、Revision、前端 E2E 测试。
- 将失败结果与 trace、SQL、Hook、Event、队列、路由、迁移状态关联。
- 输出 AI 可读的失败诊断和修复建议。

### 16.2 测试上下文

AI 测试前必须读取真实上下文：

```text
.ai/routes.json
.ai/models.json
.ai/relations.json
.ai/permissions.json
.ai/hooks.json
.ai/events.json
.ai/plugins.json
.ai/openapi.json
.ai/migrations.json
.ai/mock_profiles.json
.ai/mock_scenarios.json
.ai/test_identities.json
.ai/auth_flows.json
```

这样 AI 能知道：

- 有哪些接口。
- 每个接口需要什么参数。
- 哪些接口需要 token。
- token 来自哪个登录入口。
- 哪些模型字段必填。
- 哪些权限点控制菜单、按钮和 API。
- 哪些租户、角色、套餐、插件授权会影响结果。
- 哪个 mock 场景适合当前测试。

### 16.3 测试计划生成

命令：

```bash
php webman b8:test:plan mall/order
php webman b8:test:plan plugin b8/coupon
php webman b8:test:plan --from-openapi
php webman b8:test:plan --changed-only
php webman b8:test:plan --with-auth-matrix
```

生成计划示例：

```text
订单模块测试计划：
1. CRUD smoke test
2. OpenAPI contract test
3. RBAC 权限测试
4. ABAC 资源权限测试
5. 租户隔离测试
6. Token 鉴权矩阵测试
7. Hook 注入测试
8. Event 分发测试
9. 历史版本和回退测试
10. 前端后台 E2E 测试
```

### 16.4 测试代码生成

AI 生成的不是临时脚本，而是可维护测试文件：

```bash
php webman b8:test:generate mall/order --type=api
php webman b8:test:generate mall/order --type=crud
php webman b8:test:generate mall/order --type=permission
php webman b8:test:generate mall/order --type=tenant
php webman b8:test:generate mall/order --type=e2e
```

测试类型：

```text
PHPUnit/Pest API 测试
HTTP contract 测试
数据库断言测试
权限矩阵测试
租户隔离测试
Hook/Event 测试
Revision 回退测试
Playwright 后台 E2E 测试
```

### 16.5 测试执行命令

```bash
php webman b8:test:seed smoke
php webman b8:test:run smoke
php webman b8:test:run api
php webman b8:test:run crud
php webman b8:test:run permission
php webman b8:test:run tenant
php webman b8:test:run plugin
php webman b8:test:run hook
php webman b8:test:run e2e
php webman b8:test:run --changed-only
php webman b8:test:diagnose <run-id>
php webman b8:test:clear --marker=test:smoke
```

测试执行前自动检查：

```text
php -l
php webman route:list
php webman b8:migrate:status
php webman b8:migrate --dry-run
测试数据库连接
测试租户上下文
测试身份和 token 可用性
```

### 16.6 鉴权和 Token 测试

需要 token 鉴权的场景不能手工复制真实用户 token，也不能在测试代码中硬编码长期 token。框架应该提供测试身份和临时 token 机制。

测试身份声明：

```yaml
identities:
  super_admin:
    type: admin
    roles: [super_admin]
    tenant: system
  tenant_admin:
    type: admin
    roles: [tenant_admin]
    tenant: demo
  operator:
    type: admin
    roles: [order_operator]
    tenant: demo
  readonly:
    type: admin
    roles: [readonly]
    tenant: demo
  member:
    type: api
    roles: [member]
    tenant: demo
  denied:
    type: admin
    roles: []
    tenant: demo
```

测试 token 签发命令：

```bash
php webman b8:test:auth:issue tenant_admin --ttl=15m
php webman b8:test:auth:issue member --guard=api --ttl=15m
php webman b8:test:auth:matrix mall/order
php webman b8:test:auth:revoke --run-id=20260620-001
```

签发策略：

- 优先走真实登录接口，验证登录链路和 token 格式。
- 对后台 E2E 测试可生成 Playwright `storageState`，避免每个用例重复登录。
- 对 API 测试可由 `TestTokenBroker` 签发短期测试 token。
- token 只写入 `runtime/test-runs/<run-id>/secrets.json` 或等价临时存储。
- 日志、trace、报告必须脱敏 token，只显示 token hash 或后 6 位。
- 测试结束自动 revoke 或过期。
- 生产环境默认禁止签发测试 token。

测试请求示例：

```text
Authorization: Bearer ${token:tenant_admin}
X-B8-Test-Run: 20260620-001
X-B8-Tenant: demo
```

### 16.7 Token 鉴权矩阵

每个需要鉴权的接口至少测试：

```text
无 token                  应返回 401
伪造 token                应返回 401
过期 token                应返回 401
错误 guard token          应返回 401 或 403
有 token 无权限           应返回 403
有 token 有菜单无按钮权限  按钮不可见，API 拒绝
有 API 权限无数据权限      返回空列表或 403
跨租户 token              不得读取其他租户数据
超级管理员 token          可访问但仍记录审计
普通用户 token            只访问自己的资源
```

对于刷新 token 或多端登录，还要测试：

```text
refresh token 正常刷新
refresh token 过期
logout 后 token 失效
修改密码后 token 失效
角色权限变更后缓存刷新
租户禁用后 token 失效
```

### 16.8 前端 E2E 鉴权

后台 E2E 测试支持两种模式：

```text
login-flow      通过真实登录页面登录，验证登录 UI 和接口。
storage-state   使用测试 token 生成浏览器状态，加速页面测试。
```

命令：

```bash
php webman b8:test:e2e:auth tenant_admin --mode=login-flow
php webman b8:test:e2e:auth tenant_admin --mode=storage-state
php webman b8:test:run e2e --identity=tenant_admin
```

E2E 权限测试应覆盖：

- 不同角色菜单是否正确显示。
- 按钮权限是否正确显示。
- 页面直输 URL 是否被拦截。
- token 过期后是否跳转登录。
- 切换租户后数据是否隔离。

### 16.9 测试数据和 token 绑定

测试数据必须和测试身份绑定：

```text
tenant_admin -> demo 租户管理数据
operator     -> demo 租户订单操作数据
readonly     -> demo 租户只读数据
member       -> demo 租户前台用户数据
denied       -> 无授权数据
```

`b8:test:seed permission` 应同时生成：

- 测试租户。
- 测试用户。
- 测试角色。
- 测试权限。
- 测试 token 身份。
- 测试业务数据。
- 可清理 marker。

### 16.10 AI 失败诊断

测试失败后，AI 自动读取：

```text
测试输出
HTTP 请求和响应
trace_id
/__trace 详情
SQL 日志
Hook 执行日志
Event 日志
队列日志
迁移状态
route:list
权限缓存状态
token 签发记录
当前测试身份
```

命令：

```bash
php webman b8:test:diagnose runtime/test-runs/20260620-001
php webman b8:test:diagnose --trace-id=xxx
php webman b8:test:explain-failure --case=OrderCreateRequiresPermission
```

诊断输出：

```text
失败接口：POST /app/mall/admin/order/save
测试身份：operator
期望结果：200
实际结果：403
失败原因：operator 角色缺少 mall:order:save 权限
证据：
- route:list 中接口存在
- token 有效
- UserAuthCache 未包含 mall:order:save
- trace_id=xxx
建议：
1. 检查插件迁移是否注册按钮/API 权限
2. 检查角色授权是否包含 mall:order:save
3. 清理权限缓存后重试
```

### 16.11 AI 测试安全边界

AI 测试必须受限制：

- AI 不能在生产库自动生成测试数据。
- AI 不能自动删除无 marker 数据。
- AI 不能读取或输出完整 token。
- AI 不能使用真实管理员长期 token。
- AI 不能绕过登录和权限直接改业务数据。
- AI 生成测试后必须经过 dry-run 或人工确认。
- 所有测试 token 必须短期有效、可撤销、可审计。
- 所有测试数据必须带 `test_marker` 或 `mock_marker`。

### 16.12 测试报告

测试报告应输出给人和 AI 两种格式：

```text
runtime/test-runs/<run-id>/report.md
runtime/test-runs/<run-id>/report.json
runtime/test-runs/<run-id>/trace-map.json
runtime/test-runs/<run-id>/auth-map.json
runtime/test-runs/<run-id>/screenshots/
runtime/test-runs/<run-id>/videos/
```

报告内容：

- 测试计划。
- 执行命令。
- 测试身份。
- token hash。
- mock 场景。
- 通过和失败用例。
- 失败 trace。
- 截图和视频。
- 建议修复。
- 可复现命令。

## 17. 可观测和运行时诊断

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
php webman b8:test:diagnose <run-id>
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
- 测试身份。
- token 签发和撤销摘要。
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

## 18. 数据迁移和安装

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

## 19. 发布和部署

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

## 20. 阶段路线图

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
- Mock Profile 和 Mock Scenario。
- 模拟数据生成、预览和清理。
- AI Context 导出。
- docs/skills/.ai 目录规范。
- OpenAPI 生成。
- CRUD 生成器升级。

### Phase 3：B8 Test

- AI 测试计划生成。
- API、CRUD、权限、租户、插件、E2E 测试生成。
- smoke 测试数据和场景数据。
- 测试身份和短期 token。
- Token 鉴权矩阵。
- 测试执行报告。
- trace 关联诊断。
- 失败原因解释和修复建议。

### Phase 4：B8 Event + Hook

- 领域事件。
- 系统事件。
- 异步事件。
- Action Hook。
- Filter Hook。
- Hook Point 契约。
- Hook 调试和可观测。
- AOP 基础能力。

### Phase 5：B8 Plugin + Marketplace

- 插件 manifest。
- 依赖解析。
- 安装 dry-run。
- Composer 插件安装。
- 前端组件安装。
- 插件商城。
- 插件授权。
- 插件安全审计。

### Phase 6：B8 SaaS + Permission

- RBAC。
- ABAC。
- DataScope。
- TenantContext。
- 租户套餐。
- 租户插件授权。
- 租户数据隔离。
- 租户审计和额度。

### Phase 7：B8 Revision + Release

- CRUD 历史版本。
- 差异对比。
- 回退。
- 回收站。
- Docker release。
- 二进制发布。
- 环境检查。
- 运维面板。

## 21. 最小可行版本建议

第一版不要一次性做全插件商城和完整 SaaS，建议先做一个能真实落地的最小闭环：

```text
1. Base 分层
2. 统一响应
3. Phinx 迁移
4. 模型元数据
5. 数据字典生成
6. 模拟数据生成
7. CRUD 生成器
8. AI Context 导出
9. AI 测试 smoke
10. Token 鉴权测试矩阵
11. Event
12. Hook
13. 插件 manifest 和 dry-run 安装预检
```

这个闭环完成后，AI 就可以基于真实结构生成业务模块，插件也可以安全地声明依赖和扩展点。

## 22. 关键判断

B8 新框架和 SaiAdmin 的区别不应该是换 UI 或换目录，而应该是：

```text
SaiAdmin 强在后台 CRUD 和基础分层。
B8 新框架要强在 AI 可理解、AI 可测试、插件可治理、事件可扩展、Hook 可注入、数据可追溯、SaaS 可运营。
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
- 如何生成测试计划？
- 某个需要 token 的接口应该用哪个测试身份？
- 某次测试失败对应哪个 trace_id？
- AI 开发这个模块应该遵循什么规范？

做到这些，B8 才不是另一个后台模板，而是面向 AI 时代的 Webman 集成开发平台。
