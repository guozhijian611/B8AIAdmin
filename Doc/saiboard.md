# SAI Board 大屏可视化插件说明（待开发）

本文档说明 `saiboard` 插件在 B8AIadmin 中的功能边界、技术选型、数据库设计、后端分层、前端集成、鉴权模型和开发计划。**本插件尚未实现，文档作为开发设计依据，落地时以实际代码和迁移为准。**

## 功能定位

`saiboard` 是框架内置的数据可视化大屏插件，目标是让管理员在后台可视化拖拽搭建大屏，绑定本地 MySQL 或远程 HTTP 数据源，并按需对外发布访问。

| 能力 | 入口 | 说明 |
| --- | --- | --- |
| 大屏管理 | `server/plugin/saiboard/app/admin/controller/ScreenController.php` | 维护大屏列表、设计尺寸、背景、对外开关与访问令牌。 |
| 数据源管理 | `server/plugin/saiboard/app/admin/controller/DatasourceController.php` | 维护 MySQL/HTTP 数据源连接配置，支持连接测试。 |
| 查询模板 | `server/plugin/saiboard/app/admin/controller/QueryTemplateController.php` | 维护预置取数模板（表聚合、原始行、计数、HTTP 透传），不暴露裸 SQL。 |
| 拖拽编辑器 | `saiadmin-artd/src/views/screen-editor/` | 基于 go-view 编辑器，独立懒加载路由，组件拖拽布局、绑定数据源与查询模板。 |
| 对外运行时 | `saiadmin-artd/src/views/screen-runtime/` | 独立路由 + 代码分割，echarts + DataV 装饰组件全屏渲染。 |
| 对外取数 | `server/plugin/saiboard/app/api/controller/BoardController.php` | 按组件查询模板执行数据源，返回脱敏结果。 |

后台前端编辑器位于 `saiadmin-artd/src/views/screen-editor/`，数据源管理位于 `saiadmin-artd/src/views/plugin/saiboard/`，对外运行时位于 `saiadmin-artd/src/views/screen-runtime/`，后端插件位于 `server/plugin/saiboard`。

## 整体架构

```
┌──────────── 后台编辑器（独立懒加载路由，NaiveUI 局部）────────────┐
│ 大屏列表 → go-view 编辑器 → 拖拽布局 → 组件绑定数据源 → 保存  │
└──────────────────────────┬─────────────────────────────────────┘
                           │ layout JSON + 数据源配置入库（全在后端）
                           ▼
┌──────────────── 对外运行时（无 UI 库，echarts+DataV）────────────┐
│ /screen/:code → 后端下发大屏配置（脱敏：不含 DB 密码/token）      │
│   ↓ 组件按 refresh 轮询                                          │
│ /app/saiboard/api/data?widget_id=xx → 后端代执行                │
│   ├─ MySQL 数据源：按预置模板执行（只读 SELECT）                │
│   └─ HTTP 数据源：带请求头远程 GET                              │
└───────────────────────────────────────────────────────────────┘
```

**核心原则**：数据源执行必须在后端代理。浏览器无法直连 MySQL，远程 GET 又有跨域与密钥泄露问题，所以数据源配置中的数据库密码、请求头 token 只在后端持有，前端永远拿不到敏感信息。

## 技术选型

| 层 | 选型 | 理由 |
| --- | --- | --- |
| 编辑器 | go-view（dromara，14.6k★）编辑器，独立路由懒加载，自带 NaiveUI 仅在此 chunk 内 | 改动最小、上线最快，不污染 Element Plus 主应用。 |
| 运行时引擎 | echarts 6（已有）+ DataV-Vue3（jmhh240 装饰组件） | 无 UI 库依赖、可代码分割、复用现有 `art-*` 图表组件。 |
| 数据存储 | go-view 的组件 JSON 模型 | 模型成熟，与编辑器天然对齐。 |
| SQL 安全 | 预置 SQL 模板，不暴露裸 SQL | 用户选表 + 字段 + 条件，后端拼 SQL，从源头杜绝注入。 |
| 鉴权粒度 | 大屏级：公开 / token / 登录 | 简单清晰，覆盖展厅公开、客户专属、内部报表三类场景。 |
| 运行时页 | 后台前端项目内，独立路由 + 代码分割 | 复用构建链，一套代码。 |

### go-view 集成方式

go-view 编辑器使用 NaiveUI，与本框架 Element Plus 后台不同。采用**隔离子模块**方案：把 go-view 编辑器作为后台一个独立路由懒加载，它自己的 NaiveUI 只在这个路由 chunk 内加载，不污染主应用。

- 编辑器外壳改动小、上线快，代价是编辑器视觉风格与其他后台页略不一致。
- go-view 官方文档站已下线，但 dromara 基金会托管、芋道分支持续维护，源码可用。集成后需要 **fork 自行维护编辑器**，不再追上游。

## 目录结构

### 后端插件

```
server/plugin/saiboard/
├── app/
│   ├── admin/
│   │   ├── controller/   ScreenController, DatasourceController, QueryTemplateController
│   │   ├── logic/        对应 Logic
│   │   └── validate/     对应 Validate（save/update 场景）
│   ├── api/controller/   BoardController（对外：getScreen / data，自鉴权）
│   ├── service/
│   │   ├── DataSourceExecutor.php   ← MySQL/HTTP 执行器（核心）
│   │   └── SqlBuilder.php           ← 预置模板拼装（替代裸 SQL）
│   └── model/            Screen, Datasource, QueryTemplate
├── config/
│   ├── route.php         ← 对外路由 + admin 路由
│   └── middleware.php    ← admin: CheckLogin+CheckAuth+SystemLog；api: 空（自鉴权）
```

### 前端

```
saiadmin-artd/src/views/plugin/saiboard/
├── api/            screen.ts, datasource.ts, queryTemplate.ts
├── screen/         大屏列表（标准 CRUD，Element Plus）
└── datasource/     数据源管理（含测试连接 + 查询模板子管理）

saiadmin-artd/src/views/screen-editor/    ← go-view 编辑器（独立懒加载，NaiveUI 局部）
└── [id]/index.vue

saiadmin-artd/src/views/screen-runtime/   ← 对外运行时（独立路由，无 UI 库）
└── [code]/index.vue
```

## 数据库设计

按框架规范：小写蛇形命名、含审计字段、`status` 用 1正常2停用、`is_*` 用 1是2否、Phinx 迁移幂等。

### `saiboard_datasource` 数据源表

可跨大屏复用的数据源连接配置。

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| `id` | int unsigned PK | 主键。 |
| `name` | varchar(60) | 数据源名称。 |
| `type` | varchar(10) | `mysql` / `http`。 |
| `config` | json | mysql：`{host,port,database,username,password,charset}`；http：`{url,method,headers{},params{}}`。 |
| `cache_ttl` | int | 结果缓存秒数，0 不缓存。 |
| `last_error` | text NULL | 最近一次执行错误，调试用。 |
| `status` | tinyint unsigned | 1启用 2停用。 |
| `created_by` / `updated_by` | int NULL | 审计。 |
| `create_time` / `update_time` | datetime NULL | 审计。 |
| `delete_time` | datetime NULL | 软删除。 |

### `saiboard_screen` 大屏表

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| `id` | int unsigned PK | 主键。 |
| `code` | varchar(32) | 对外访问编码，唯一索引。 |
| `name` | varchar(60) | 大屏名称。 |
| `width` / `height` | int | 画布设计尺寸，如 1920×1080。 |
| `bg_config` | json | 背景：颜色 / 图片 / 网格。 |
| `is_public` | tinyint unsigned | 1对外公开 2需鉴权。 |
| `access_token` | varchar(64) NULL | `is_public=2` 时校验用，空则要求后台登录态。 |
| `layout` | json | go-view 组件树，见 layout 模型。 |
| `status` | tinyint unsigned | 1已发布 2草稿。 |
| 审计字段 | | 同上。 |

### `saiboard_query_template` 查询模板表

预置 SQL 模板方案的核心：组件不存 SQL，只存 `query_template_id`。

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| `id` | int unsigned PK | 主键。 |
| `datasource_id` | int unsigned | 归属数据源。 |
| `name` | varchar(60) | 模板名，如「近7天销售额」。 |
| `dataset_type` | varchar(20) | 取数类型，见枚举。 |
| `config` | json | 模板参数：表名、字段、条件、聚合、http 路径等。 |
| `status` | tinyint unsigned | 1启用 2停用。 |
| 审计字段 | | 同上。 |

`dataset_type` 枚举（覆盖大屏 90% 场景）：

| 类型 | 说明 |
| --- | --- |
| `table_aggregate` | 表聚合：选表 + 维度字段（x 轴）+ 指标字段（y 轴）+ 聚合方式（sum/count/avg）+ 时间范围 + 分组。 |
| `table_raw` | 表原始：选表 + 字段 + 条件 + 排序 + limit。 |
| `table_count` | 单值计数。 |
| `http_passthrough` | HTTP 透传：配置路径 + 参数。 |

## layout JSON 模型（go-view 组件模型）

```json
{
  "editCanvas": { "width": 1920, "height": 1080 },
  "componentList": [
    {
      "id": "w_1",
      "chartConfig": {
        "key": "BarCommon",
        "title": "本月销售",
        "chartFrame": "echarts",
        "dataset": { "queryTemplateId": 5, "refresh": 30 },
        "option": {}
      },
      "attr": { "x": 40, "y": 40, "w": 600, "h": 360, "zIndex": 1 }
    }
  ]
}
```

- 保存时 `layout` 整体入库（拖拽是原子操作）。
- 运行时下发时保留 `queryTemplateId`，但 **剥离所有 datasource.config**，前端拿不到密钥。

## 后端分层

### admin（标准 `AbstractCrudController` 模式）

| 控制器 | 方法 | 权限 slug |
| --- | --- | --- |
| `ScreenController` | index / read / save / update / destroy / changeStatus / saveLayout / publish / copy | `saiboard:screen:*` |
| `DatasourceController` | 标准 CRUD + `test`（测连接 / 请求） | `saiboard:datasource:*` |
| `QueryTemplateController` | 标准 CRUD + `preview`（执行预览） | `saiboard:queryTemplate:*` |

每个方法挂 `#[Permission('...', 'saiboard:<module>:<action>')]` 注解。写接口调用 `$this->validate('<scene>', $data)`。

### api（对外，自鉴权）— `BoardController`

- `getScreen(code)`：下发大屏配置（脱敏，不含任何密钥）。
- `data(code, widget_id)`：校验 `is_public` / `access_token` / 登录态 → 取 widget → 取 `query_template` → `DataSourceExecutor` 执行 → 返回 `{rows, total}`。

`middleware.php` 的 `api` 数组留空，鉴权在控制器内按大屏配置自行判定。

### 核心服务

**`DataSourceExecutor`**

- `mysql`：`Db::connect($config)` 临时连接，执行 `SqlBuilder` 产出的 SELECT，返回 rows。
- `http`：Workerman HTTP 客户端，按 config（含自定义请求头）发请求，解析 JSON。
- 带 `cache_ttl` 文件 / redis 缓存，防止高频轮询打爆源库。

**`SqlBuilder`**

- 输入：`dataset_type` + `config`（表名、字段、条件）。
- 输出：参数化 SELECT。
- 表名、字段名走 **白名单校验**：只能是指定 datasource 库里真实存在的表 / 列。
- 强烈建议生产仍给数据源配 **只读 MySQL 账号**，作为第二道防线。

## 鉴权模型

```
getScreen / data 接口入口：
  if screen.is_public == 1:    放行（公开大屏，展厅 / 投屏场景）
  if screen.is_public == 2:
      if access_token 非空:    校验请求中的 token（query 或 header）
      else:                    要求后台 JWT 登录态（复用 CheckLogin 逻辑）
```

覆盖三种场景：完全公开大屏、带令牌的对外大屏（客户专属）、仅内部登录可看的报表。

## 前端关键点

### go-view 编辑器（独立懒加载路由 `/plugin/saiboard/editor/:id`）

- 放 `views/screen-editor/`，路由 `meta` 标记独立布局，不进后台 Layout。
- NaiveUI 只在此 chunk 内 import，主 bundle 不受污染。
- 改造点：把 go-view 默认的「请求 URL 配置」替换为「选数据源 + 选查询模板」，保存时后端校验并落库。

### 对外运行时页 `/screen/:code`

- 放 `views/screen-runtime/`，独立路由 + 代码分割，不进后台布局。
- 只引 echarts + DataV-Vue3 装饰组件，全屏渲染，按 `refresh` 轮询 `/data`。
- 深色科技风（大屏惯例），配色在 `screen.bg_config` 配置。

### 数据源管理页 `/plugin/saiboard/datasource`

- 标准 CRUD（Element Plus）+ 测试连接按钮 + 查询模板子管理。

## 数据源配置示例

### MySQL 数据源 config

```json
{
  "host": "127.0.0.1",
  "port": 3306,
  "database": "business",
  "username": "readonly_user",
  "password": "secret",
  "charset": "utf8mb4"
}
```

### HTTP 数据源 config

```json
{
  "url": "https://api.example.com/v1/metrics",
  "method": "GET",
  "headers": {
    "Authorization": "Bearer xxx",
    "X-Tenant": "b8"
  },
  "params": {
    "range": "7d"
  }
}
```

## 安装和迁移（计划）

依赖通过 `composer` 与 `pnpm` 安装：

```bash
cd server
composer install   # 预计新增 Workerman HTTP 客户端依赖（如尚未具备）

cd ../saiadmin-artd
pnpm install       # 预计新增 go-view 编辑器源码、@datav-view/datav-vue3
```

数据库结构和预设数据由 Phinx 迁移维护，文件放在 `Database/migrations/`：

```bash
cd server
php webman b8:migrate:status
php webman b8:migrate --dry-run
php webman b8:migrate
```

迁移需包含：

- 建表 `saiboard_datasource` / `saiboard_screen` / `saiboard_query_template`，幂等。
- 后台菜单「大屏管理 / 数据源管理 / 查询模板 / 大屏编辑器」，权限 slug 见后端分层表。
- 初始化只读账号使用说明（文档，不写入迁移）。

## 开发计划

### P0 最小可用

| 模块 | 内容 |
| --- | --- |
| 数据库 | 3 张表 + Phinx 迁移（含菜单权限）。 |
| 后端 | `SqlBuilder`（table_aggregate / table_raw）+ `DataSourceExecutor`（mysql / http）。 |
| 后端 | `ScreenController` 标准 CRUD + `saveLayout` / `publish`。 |
| 前端 | go-view 编辑器集成（隔离子模块）。 |
| 前端 | 3~4 种图表 + 对外运行时页（is_public / token 鉴权）。 |

### P1 能力增强

- `SqlBuilder` 补充 `table_count` / `http_passthrough`。
- DataV-Vue3 装饰组件（边框、装饰、标题）。
- `DataSourceExecutor` 缓存。
- 图表组件补全：地图、表格、翻牌、轮播。
- 大屏主题。

### P2 进阶

- 组件 / 模板市场、克隆。
- 大屏版本管理。
- 数据权限 `scope`（按 `created_by` 隔离大屏归属，在对应 Logic 显式 `protected bool $scope = true;`）。

## 待确认风险点

1. **go-view 上游维护**：官方文档站已下线，dromara 基金会托管、芋道分支持续维护，源码可用。集成后需 fork 自行维护编辑器，不再追上游。
2. **生产数据源只读账号**：预置模板已能防注入，但强烈建议生产 MySQL 数据源配只读账号作为第二道防线，需在文档和部署指引中强制说明。

## 排障（待补充）

落地后按实际接入情况补充：连接测试失败、SQL 白名单拦截、对外访问 401、缓存命中、轮询超载等。
