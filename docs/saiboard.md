# SAI Board 大屏可视化插件说明（P0 已落地）

本文档说明 `saiboard` 插件在 B8AIadmin 中的功能边界、技术选型、数据库设计、后端分层、前端集成、鉴权模型和后续计划。当前已完成 P0 最小可用版本和部分 P1/P2 能力，实际入口以 `server/plugin/saiboard`、`saiadmin-artd/src/views/plugin/saiboard`、`Database/migrations/20260619000100_add_saiboard_plugin.php`、`Database/migrations/20260619000200_add_saiboard_screen_version.php`、`Database/migrations/20260619000300_add_saiboard_screen_token.php`、`Database/migrations/20260619000400_add_saiboard_market_item.php` 和 `Database/migrations/20260619000500_add_saiboard_generate_from_table_permission.php` 为准。

> 设计第一原则：**尽可能简单**。只用项目已有依赖（Vue 3 + Element Plus + echarts 6），不引入 go-view、naive-ui、DataV 等需要长期 fork 维护的重型前端工程；**编辑器与对外运行时共用同一套图表渲染组件**，保证「编辑所见 = 运行所得」，避免双引擎割裂。

## 功能定位

`saiboard` 是框架内置的数据可视化大屏插件，目标是让管理员在后台**自由拖拽**搭建大屏，绑定本地 MySQL 或远程 HTTP 数据源，并按需对外发布访问。

| 能力 | 入口 | 说明 |
| --- | --- | --- |
| 大屏管理 | `server/plugin/saiboard/app/admin/controller/ScreenController.php` | 维护大屏列表、设计尺寸、背景、对外开关与访问令牌。 |
| 数据源管理 | `server/plugin/saiboard/app/admin/controller/DatasourceController.php` | 维护 MySQL/HTTP 数据源连接配置，支持连接测试。 |
| 查询模板 | `server/plugin/saiboard/app/admin/controller/QueryTemplateController.php` | 维护预置取数模板（原始行、计数、聚合、HTTP 透传），不暴露裸 SQL。 |
| 模板市场 | `server/plugin/saiboard/app/admin/controller/MarketItemController.php` | 维护大屏模板和组件模板，支持公开 / 私有复用。 |
| 拖拽编辑器 | `saiadmin-artd/src/views/plugin/saiboard/editor/` | Element Plus 外壳 + 薄拖拽层，组件拖拽布局、绑定查询模板，画布直接渲染真实图表组件。 |
| 对外运行时 | `saiadmin-artd/src/views/plugin/saiboard/runtime/` | 前端静态公开路由 `/screen/:code`，复用**同一套** `art-*` 图表组件，全屏等比缩放渲染。 |
| 对外取数 | `server/plugin/saiboard/app/api/controller/BoardController.php` | 按组件绑定的查询模板执行数据源，返回脱敏结果。 |
| 快速生成 | `ScreenController::generateFromTable` | 选择已有 MySQL 数据源和数据表，自动创建查询模板与鉴权草稿大屏，生成后进入编辑器微调。 |

接口文档已接入 APIDOC：后台管理接口使用 `/apidoc/openapi/saiboard-admin`，公开运行时接口使用 `/apidoc/openapi/saiboard-api`。这两个 key 独立于移动端自动生成配置，便于验收后台配置接口与公开大屏取数接口。

后台编辑器与数据源管理都在 `saiadmin-artd/src/views/plugin/saiboard/`（Element Plus，主应用内），对外运行时也在该目录下的 `runtime/`，通过 `staticRoutes.ts` 暴露 `/screen/:code` 静态路由，后端插件位于 `server/plugin/saiboard`。

## 整体架构

```
┌──────────── 后台编辑器（主应用内，Element Plus + 薄拖拽层）────────────┐
│ 大屏列表 → 进入编辑器 → 左侧组件面板 → 拖拽到画布 → 右侧绑查询模板 → 保存 │
│ 画布渲染的就是 art-* 真实图表组件（所见即所得）                          │
└──────────────────────────┬──────────────────────────────────────────┘
                           │ layout JSON（仅组件树+绑定关系）入库
                           ▼
┌──────────────── 对外运行时（前端静态公开路由，复用同一套 art-* 组件）──────┐
│ /screen/:code → 后端下发大屏配置（脱敏：不含 DB 密码/token）             │
│   ↓ 组件按 refresh 轮询                                                 │
│ /app/saiboard/api/data?code=xx&cid=w_1 → 后端代执行                     │
│   ├─ MySQL 数据源：按组件绑定的预置模板执行（只读 SELECT）              │
│   └─ HTTP 数据源：带请求头远程 GET / POST JSON（含 SSRF 防护）           │
└────────────────────────────────────────────────────────────────────┘
```

**两条核心原则**：

1. **统一渲染**：编辑器画布和运行时渲染的是**同一组 `art-*` 图表组件**，不存在「编辑用一套、运行用另一套」的割裂，也不需要把任何引擎的 option 重新映射。
2. **后端代理取数**：浏览器无法直连 MySQL，远程 GET 又有跨域与密钥泄露问题，所以数据源的数据库密码、请求头 token 只在后端持有，前端永远拿不到敏感信息。

## 技术选型

| 层 | 选型 | 理由 |
| --- | --- | --- |
| UI 框架 | Element Plus（已有） | 主应用同款，无新增 UI 库，编辑器与列表风格统一。 |
| 拖拽 / 缩放 | `vue3-draggable-resizable`（Vue 3 拖拽+缩放组件，自带对齐线/吸附/父级边界） | 开箱即用、省事，免去自研；只是一个轻量组件库，非整套工程。 |
| 渲染引擎 | echarts 6（已有）+ 现成 `art-*` 图表组件 | 编辑器与运行时共用同一套组件，所见即所得；新增图表只实现一次。 |
| 数据存储 | 自定义 layout JSON（组件树 + 绑定关系） | 结构极简，只存「放了哪些组件、各自绑哪个查询模板」。 |
| SQL 安全 | 预置查询模板，不暴露裸 SQL | 用户选表 + 字段 + 条件，后端拼参数化 SELECT，从源头杜绝注入。 |
| 鉴权粒度 | 大屏级：公开 / token / 登录 | 简单清晰，覆盖展厅公开、客户专属、内部报表三类场景。 |
| 运行时页 | 后台前端项目内，静态公开路由 `/screen/:code` | 复用构建链，一套代码；无需第二个前端工程。 |

> **被刻意放弃的选项**：go-view（需 fork 自维护、naive-ui 污染、编辑/运行双引擎割裂）、DataV-Vue3 装饰组件（社区非官方分支）。装饰边框等纯视觉效果如确有需要，放到 P1 用 CSS/SVG 自实现，不绑第三方分支。

### 渲染层：一套组件，编辑器与运行时共用

这是本方案相对原始设计的最大简化点。

- `art-*` 图表组件（`src/components/core/charts/` 已有 bar/line/ring/radar/scatter/k-line/gauge/funnel/heatmap 等）、时间轴、点位地图和装饰组件封装成统一约定的「大屏组件」：数据组件输入 `{ option/props, data }`，装饰组件仅消费 `option/props` 并在运行时免取数，统一输出渲染。
- **编辑器画布**直接 `<component :is>` 渲染这些组件，拖拽只改外层 `DraggableItem`（封装 `vue3-draggable-resizable`）的 x/y/w/h，组件本身不感知编辑态。
- **运行时**用完全相同的组件，只是外层换成只读容器 + 全屏等比缩放。
- 结果：新增一种图表 = 加一个 `art-*` 组件 + 在组件注册表登记一次，编辑器和运行时**同时生效**，不存在两侧各实现一遍的问题。

## 目录结构

### 后端插件

```
server/plugin/saiboard/
├── app/
│   ├── admin/
│   │   ├── controller/   ScreenController, DatasourceController, QueryTemplateController, MarketItemController
│   │   ├── logic/        对应 Logic
│   │   └── validate/     对应 Validate（save/update 场景）
│   ├── api/controller/   BoardController（对外：getScreen / data，自鉴权）
│   ├── service/
│   │   ├── DataSourceExecutor.php   ← MySQL/HTTP 执行器（核心，含 SSRF 防护）
│   │   └── SqlBuilder.php           ← 预置模板拼装（参数化 SELECT，替代裸 SQL）
│   └── model/            Screen, ScreenToken, ScreenVersion, Datasource, QueryTemplate
├── config/
│   ├── route.php         ← 显式注册 admin 路由 + api 路由
│   └── middleware.php    ← admin: CheckLogin+CheckAuth+SystemLog；api: 空（自鉴权）
```

数据库结构、菜单和权限由 Phinx 迁移 `Database/migrations/20260619000100_add_saiboard_plugin.php` 维护，不使用插件 `install.sql`。

### 前端

```
saiadmin-artd/src/views/plugin/saiboard/
├── api/            screen.ts, datasource.ts, queryTemplate.ts
├── screen/         大屏列表（标准 CRUD，Element Plus）
├── datasource/     数据源管理（含测试连接）
├── market/         模板市场（大屏模板 / 组件模板）
├── editor/         拖拽编辑器（Element Plus 外壳 + DraggableItem + art-* 画布）
│   └── [id].vue
├── runtime/        对外运行时（静态 /screen/:code 路由，复用 widgets/）
│   └── [code].vue
└── widgets/        大屏组件注册表 + fit.ts + DraggableItem.vue（适配计算、拖拽封装，编辑器与运行时共用）
```

`/screen/:code` 在 `saiadmin-artd/src/router/routes/staticRoutes.ts` 中显式注册，静态路由不依赖后台动态菜单和登录态；页面级是否允许取数由后端 `BoardController` 按大屏配置判断。后台动态菜单只负责「大屏管理 / 数据源管理 / 查询模板 / 大屏编辑器」等管理入口。

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
| `code` | varchar(32) | 对外访问编码，唯一索引；留空自动生成，手动填写仅允许字母、数字、下划线和短横线。 |
| `name` | varchar(60) | 大屏名称。 |
| `width` / `height` | int | 画布设计尺寸，如 1920×1080。 |
| `bg_config` | json | 背景、主题与运行时适配：`color`、`theme`、`fit_mode`、`image`、`image_fit` 等。 |
| `is_public` | tinyint unsigned | 1对外公开 2需鉴权。 |
| `access_token` | varchar(64) NULL | 旧单令牌历史入口；后台表单不再新增或编辑，仅在未配置启用且未过期的子令牌时作为历史兼容兜底，空则要求后台登录态。 |
| `draft_layout` | json | **编辑中的**画布尺寸、背景配置与组件树，`saveLayout` 只写这里。 |
| `layout` | json | **已发布的**组件树，运行时只读这里；`publish` 时由 `draft_layout` 拷贝而来。 |
| `status` | tinyint unsigned | 1已发布 2草稿。 |
| 审计字段 | | 同上。 |

> `draft_layout` 与 `layout` 分离：编辑过程不会污染线上已发布大屏，运行时永远拿稳定快照。这是 P0 就要落地的最小版本管理。

### `saiboard_screen_token` 大屏访问令牌表

用于一个大屏下发多个客户专属访问令牌，每个令牌可单独停用、重置或删除。子令牌明文只在创建 / 重置时返回一次，数据库只保存 SHA-256 哈希和前缀；只要存在启用且未过期的子令牌，公开运行时就不再接受旧 `access_token`，避免子令牌吊销被旧入口绕过。

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| `id` | bigint unsigned PK | 主键。 |
| `screen_id` | bigint unsigned | 归属大屏。 |
| `name` | varchar(80) | 令牌名称，通常填写客户名或用途。 |
| `token_prefix` | varchar(16) | 明文令牌前缀，用于后台识别。 |
| `token_hash` | char(64) | 明文令牌的 SHA-256 哈希，唯一索引。 |
| `last_used_time` | datetime NULL | 最近一次公开运行时使用时间。 |
| `expire_time` | datetime NULL | 过期时间，空表示长期有效。 |
| `status` | tinyint unsigned | 1启用 2停用。 |
| 审计字段 | | 同上。 |

### `saiboard_query_template` 查询模板表

预置取数方案的核心：组件不存 SQL、不存连接信息，只存 `query_template_id`。

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| `id` | int unsigned PK | 主键。 |
| `datasource_id` | int unsigned | 归属数据源。 |
| `name` | varchar(60) | 模板名，如「近7天订单」。 |
| `dataset_type` | varchar(20) | 取数类型，见枚举。 |
| `config` | json | 模板参数：表名、字段、条件、排序、limit、http 路径等。 |
| `status` | tinyint unsigned | 1启用 2停用。 |
| 审计字段 | | 同上。 |

`dataset_type` 枚举（按复杂度分期落地）：

| 类型 | 阶段 | 说明 |
| --- | --- | --- |
| `table_raw` | P0 | 表原始：选表 + 字段 + 条件 + 排序 + limit。 |
| `table_count` | P0 | 单值计数。 |
| `http_passthrough` | P0 | HTTP 透传：配置路径 + 参数。 |
| `table_aggregate` | P0 + P1 | 表聚合：维度 + 可选第二维度 + 多指标 + 聚合（count/sum/avg/min/max）+ 条件 + 排序 + limit。 |

> `table_aggregate` 仍保持预置模板模式，不开放裸 SQL。维度、第二维度和指标字段都必须来自目标数据源真实表字段，日期维度支持原始值、按日、按月、按年。

### `saiboard_market_item` 模板市场表

用于沉淀可复用的大屏模板和组件模板。模板保存时会剥离组件里的 `queryTemplateId` / `query_template_id`，只复用视觉布局、组件配置和字段映射；导入后需要按当前大屏重新绑定查询模板，避免跨用户数据源 / 查询模板泄漏。

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| `id` | bigint unsigned PK | 主键。 |
| `type` | varchar(20) | `screen` 大屏模板 / `component` 组件模板。 |
| `name` | varchar(80) | 模板名称。 |
| `category` | varchar(60) | 分类，如订单、运营、装饰。 |
| `description` | varchar(255) | 模板说明。 |
| `cover_image` | varchar(255) | 预留封面图 URL。 |
| `content` | json | `screen` 存 `{layout}`，`component` 存 `{components}`。 |
| `component_count` | int unsigned | 组件数量，便于列表扫描。 |
| `is_public` | tinyint unsigned | 1公开 2私有；列表可见范围为公开模板 + 当前数据权限范围内模板。 |
| `status` | tinyint unsigned | 1启用 2停用。 |
| 审计字段 | | 同上。 |

## layout JSON 模型（自定义，极简）

```json
{
  "canvas": { "width": 1920, "height": 1080 },
  "bg_config": { "color": "#07111f", "theme": "midnight", "fit_mode": "contain" },
  "components": [
    {
      "id": "w_1",
      "type": "art-bar-chart",
      "title": "本月订单",
      "rect": { "x": 40, "y": 40, "w": 600, "h": 360, "z": 1 },
      "dataset": {
        "queryTemplateId": 5,
        "refresh": 30,
        "params": { "tenant": "b8", "range": "7d" },
        "mapping": {
          "labelField": "label",
          "valueField": "value",
          "tableFields": ["label", "value"],
          "tableColumns": [
            { "field": "label", "label": "日期", "width": 120, "align": "left" },
            { "field": "value", "label": "订单数", "width": 100, "align": "right" }
          ]
        }
      },
      "option": {}
    }
  ]
}
```

- `type` 直接对应 `widgets/` 注册表里的组件名，编辑器与运行时都靠它 `<component :is>` 渲染。
- `id`（如 `w_1`）是组件在大屏内的稳定标识，**取数接口以 `code + id` 为键**，不接受前端传任意 `queryTemplateId`（见安全设计）。
- `dataset.mapping` 是组件级字段映射：图表类用 `labelField` / `valueField`，表格用 `tableFields` 控制列顺序，并可用 `tableColumns[]` 配置列显示名、宽度和对齐；未配置时运行时按 `label/value/total` 等常用字段自动兜底。
- `dataset.params` 是组件级运行参数，编辑器预览和公开运行时都会传给查询模板；运行时 URL 参数仍可作为全局参数，同名时组件级参数优先。`code` / `cid` / `token` / `admin_preview` / `draft` 是系统保留字段，不能作为模板运行参数。
- 保存时 `draft_layout` 整体入库（拖拽是原子操作）；发布时拷贝到 `layout`。
- 运行时下发 `layout` 时保留 `queryTemplateId`，但**剥离所有数据源连接信息**，前端拿不到密钥。

## 后端分层

### admin（标准 `AbstractCrudController` 模式）

| 控制器 | 方法 | 权限 slug |
| --- | --- | --- |
| `ScreenController` | index / read / save / update / destroy / changeStatus / saveLayout / publish / copy / generateFromTable | `saiboard:screen:*`；普通保存不会发布，`changeStatus` 仅允许退回草稿，发布必须走 `publish`。 |
| `DatasourceController` | 标准 CRUD + `test`（测连接 / 请求）+ `options` / `schema`（模板配置读取） | `saiboard:datasource:*`，`options` / `schema` 复用 `saiboard:datasource:index` |
| `QueryTemplateController` | 标准 CRUD + `preview`（执行预览） | `saiboard:query_template:*` |
| `MarketItemController` | 标准 CRUD + `options`（编辑器读取可用模板摘要） | `saiboard:market_item:*` |

每个方法挂 `#[Permission('...', 'saiboard:<module>:<action>')]` 注解。写接口调用 `$this->validate('<scene>', $data)`。

### api（对外，自鉴权）— `BoardController`

- `getScreen(code)`：默认下发**已发布** `layout`（脱敏，不含任何密钥）；仅后台用户带 `admin_preview=1&draft=1` 时下发 `draft_layout` 做草稿预览。
- `data(code, cid)`：
  1. 按 `code` 取大屏 → 校验 `is_public` / 多访问令牌 / 旧 `access_token` / 登录态；
  2. 在该大屏当前预览布局的 `components` 中按 `cid` 找到组件 → 取其绑定的 `queryTemplateId`（**服务端解析，不信任前端传入**）；
  3. 取 `query_template` → `DataSourceExecutor` 执行 → 返回 `{rows, total}`。

`middleware.php` 的 `api` 数组留空，鉴权在控制器内按大屏配置自行判定。

### 核心服务

**`DataSourceExecutor`**

- `mysql`：按数据源配置即时创建 PDO 连接，执行 `SqlBuilder` 产出的参数化 SELECT，返回 rows。
- `http`：支持 GET / POST JSON，按数据源 config 和模板 config 拼 URL，携带自定义请求头、查询参数和模板 JSON Body，使用 curl（无 curl 时降级 `file_get_contents`）请求 JSON；**发请求前做 SSRF 校验**。
- 支持 `cache_ttl` 通过 `support\think\Cache` 缓存结果，缓存键含 `query_template_id`、数据源配置、模板配置和运行时白名单参数指纹，降低运行时轮询压力；缓存 miss 时优先使用 Redis token 锁做跨副本互斥，Redis 不可用时降级本机文件锁，并保留短期 stale 缓存作为数据源异常时的公开页兜底。
- 运行时指标通过 `RuntimeMetrics` 写入 Cache，统计请求、限流、缓存命中 / 未命中、stale 命中、回源成功 / 失败、Redis / 文件锁与锁等待；具备大屏列表权限的后台用户可查看单个大屏最近统计窗口内的运行统计。

**`SqlBuilder`**

- 输入：`dataset_type` + `config`（表名、字段、条件、排序、limit）。
- 输出：**参数化** SELECT。
- 表名、字段名走**白名单校验**：只能是指定 datasource 库里真实存在的表 / 列，运行时通过 `SHOW TABLES` / `SHOW COLUMNS` 复核。
- `table_aggregate` 单维聚合输出统一的 `label + 指标列` 行；配置第二维度后输出 `label + series + 指标列`，可直接被热力图识别为 X/Y/value 矩阵。单指标兼容 `{label, value}`，多指标如 `{label, 订单数, 订单金额}` 可被柱状图、折线图自动识别为多系列。
- 强烈建议生产仍给数据源配**只读 MySQL 账号**，作为第二道防线。

## 安全设计

| 风险 | 防护 |
| --- | --- |
| SQL 注入 | 预置模板 + 参数化 SELECT + 表/字段白名单；生产只读账号兜底。 |
| 越权取数（IDOR） | `data` 接口以 `code + cid` 为键，组件绑定的 `queryTemplateId` 由服务端从该大屏 `layout` 解析，**前端不能指定任意模板/数据源 id**；运行时还会校验模板、数据源与大屏创建者一致，兜底拦截历史异常 layout。 |
| 后台数据越权 | 大屏、数据源、查询模板 Logic 显式开启 `scope`，按 `created_by` 与角色数据权限过滤；数据源测试 / 表结构、查询模板预览、layout 绑定模板、运行统计等自定义入口也走归属校验。 |
| SSRF（HTTP 数据源） | 后端代发 HTTP 请求前解析目标域名 → 拒绝内网 / 环回 / 链路本地地址（`127.0.0.0/8`、`10/8`、`172.16/12`、`192.168/16`、`169.254/16`、`::1` 等）与云元数据地址；支持 `SAIBOARD_HTTP_ALLOWED_HOSTS` 出网域名白名单，并在 curl 请求中固定已校验 DNS 结果，降低 DNS 重绑定风险。 |
| 密钥泄露 | 数据源 `config`（DB 密码、请求头 token）只在后端持有，`getScreen` 下发时剥离；大屏子令牌只保存哈希，明文仅创建 / 重置后显示一次。 |
| 公开大屏被刷 | `cache_ttl` 通过 Webman Cache 缓存结果；公开运行时下发 layout 时强制 `dataset.refresh` 不低于 10 秒；按 IP / 大屏 / 创建人维度做固定窗口限流；缓存 miss 优先使用 Redis 原子锁互斥回源，锁竞争时返回“数据缓存刷新中”，异常时可回退 stale 缓存。 |
| 日志脱敏 | 连接配置、token、Bearer 在日志/调试页脱敏，仅 `last_error` 存非敏感错误摘要。 |

## 鉴权模型

```
getScreen / data 接口入口：
  if screen.is_public == 1:    放行（公开大屏，展厅 / 投屏场景）
  if screen.is_public == 2:
      if 请求 token 命中启用且未过期的 screen_token: 放行，并节流刷新 last_used_time
      else if admin_preview=1 且后台 JWT 具备大屏读取权限和数据范围: 放行（后台预览）
      else if 存在启用且未过期的 screen_token:        拒绝访问
      else if access_token 非空:                      校验旧单 token 字段（仅兼容未迁移的历史已发布链接）
      else:                                           要求后台 JWT 登录态（复用 CheckLogin 逻辑）
```

覆盖三种场景：完全公开大屏、带令牌的对外大屏（客户专属 / 可独立吊销）、仅内部登录可看的报表。

后台管理端额外启用 SaiAdmin 数据权限：普通角色只能管理自己 `created_by` 范围内的大屏、数据源和查询模板；大屏保存 / 发布时会校验 layout 中绑定的查询模板归属，查询模板保存 / 预览时会校验数据源归属。若具备数据范围权限的用户复制他人可见大屏，副本会保留视觉布局但清空查询模板绑定，避免跨归属数据依赖。

> 旧 `access_token` 字段仅作为无子令牌时的历史兼容兜底存在，后台表单不会再新增或编辑它；新建客户级令牌必须使用 `saiboard_screen_token` 子表，避免继续在数据库保存明文 token，并获得独立吊销能力。后台列表「发布预览」使用 `admin_preview=1` 和当前后台 JWT 放行，只查看已发布快照；编辑器「预览草稿」会额外携带 `draft=1`，仅后台有大屏读取权限且通过数据范围校验时可查看草稿快照。

## 前端关键点

### 拖拽编辑器 `#/saiboard/editor/:id`（主应用内，Element Plus）

- 三栏布局：左侧组件面板（拖出组件）、中间画布（`DraggableItem` 包裹真实 `art-*` 组件）、右侧属性面板（标题、样式、绑定查询模板、字段映射、`refresh`）。
- `DraggableItem.vue` 封装 `vue3-draggable-resizable`，负责 x/y/w/h/z 的拖拽、缩放与对齐吸附，**不感知图表内容**；组件本身就是运行时同款，天然所见即所得。
- 保存写 `draft_layout`；点「发布」才拷贝到 `layout` 上线。新增和普通编辑都会保持草稿或原发布状态，不接受表单直接把草稿改成已发布。
- 「预览草稿」会先保存当前 `draft_layout`，再打开 `/screen/:code?admin_preview=1&draft=1`；后台列表的「发布预览」只打开已发布 `layout`，草稿未发布时需进入编辑器预览。
- 编辑器绑定查询模板后通过 `QueryTemplate/preview` 取真实数据预览，并从首行数据生成字段映射下拉；未绑定模板时才使用示例数据占位。

### 对外运行时页 `/screen/:code`（静态公开路由，复用 widgets/）

- 在 `staticRoutes.ts` 显式注册，不进后台布局、不依赖动态菜单；页面级是否放行交给后端 `getScreen` / `data` 接口按大屏配置判定。
- 管理端当前使用 `createWebHashHistory()`；对外裸入口 `/screen/:code` 会在前端启动时归一化为 `/#/screen/:code`，后台列表和编辑器预览也使用 hash 入口打开，避免未登录用户被后台首页守卫误导到登录页。
- 复用 `widgets/` 同一套组件，外层只读容器；按 `screen.width/height` 设计稿做运行时适配（监听 resize）。
- `bg_config.fit_mode` 支持 `contain` / `cover` / `stretch`：`contain` 完整显示设计稿并居中留边，`cover` 等比铺满视口并允许边缘裁切，`stretch` 按视口宽高分别拉伸，适合固定比例投屏。
- `bg_config` 支持 `theme` 主题预设、背景色、背景图 URL 和 `image_fit`（铺满裁切 / 完整显示 / 拉伸 / 平铺）；编辑器和运行时复用同一套样式生成逻辑。
- 按各数据组件 `dataset.refresh` 轮询 `/data`，公开运行时最小 10 秒；运行时默认用 POST 传递组件级复杂参数，后端保留 GET 兼容；纯装饰组件不绑定查询模板、不触发运行时取数。
- `draft=1` 只作为后台草稿预览开关使用，运行时和后端都会把它作为系统参数过滤，避免误传给查询模板。

### 数据源管理页 `#/saiboard/datasource`

- 标准 CRUD（Element Plus）+ 测试连接按钮；查询模板在独立页面 `#/saiboard/query-template` 维护。

### 从数据表生成大屏

- 大屏管理页提供「从数据表生成」入口，复用已启用的 MySQL 数据源和 `Datasource/schema` 表结构读取能力。
- 弹窗选定数据表后会读取真实字段结构，自动推荐生成模块、时间字段、指标字段、排行维度、分布维度、状态字段和明细字段；管理员可在生成前手动调整，明细字段最多 8 个。
- 后端会按真实表字段白名单校验配置，在同一事务中按所选模块创建 `table_count`、`table_raw` 明细 / 状态矩阵、`table_aggregate` 趋势 / 排行 / 分布查询模板；随后创建一个 `status=2`、`is_public=2` 的鉴权草稿大屏。
- 自动生成布局会根据最终字段配置生成指标卡、折线趋势、横向排行、环形分布、状态矩阵和明细表；识别不到或被禁用的模块不会生成。
- 生成结果只是草稿，不会自动发布；生成后进入编辑器继续调整字段映射、组件位置、标题、访问方式，确认后通过发布按钮上线。

### 查询模板页 `#/saiboard/query-template`

- 支持按数据源读取 MySQL 表和字段，表单化配置 `table_raw` / `table_count` / `table_aggregate`。
- `table_raw` 可选返回字段、字段别名、计算字段、条件、排序和 limit。
- `table_count` 可选条件，统一返回 `{rows, total}`，其中 `total` 是计数值。
- `table_aggregate` 可选维度字段、第二维度字段、日期粒度、多个聚合指标、条件、排序和 limit，统一返回 `{rows, total}`；单维每行结构为 `label + 指标列`，二维每行结构为 `label + series + 指标列`，指标最多 8 项，非 `count` 指标必须选择数值字段。
- 条件支持 `= / != / > / >= / < / <= / like / in / between / time_range`，并支持条件组内 `AND / OR` 组合；顶层条件按 `AND` 合并，老的平铺条件数组继续兼容。`time_range` 只允许日期 / 时间字段，`value` 可用 `today`、`yesterday`、`last_7_days`、`last_30_days`、`this_week`、`this_month`、`last_month`、`this_year`。
- MySQL 查询模板支持 `params[]` 参数白名单；条件值可写 `:param_name`，预览和公开运行时传入的同名参数会按 `string` / `number` / `date` / `datetime` / `time_range` 类型清洗后再进入参数绑定。未声明参数、非法参数名、类型不匹配或必填参数缺失都会被拒绝；URL 上的未知参数会被忽略。
- `table_raw.field_aliases` 用真实字段名映射输出字段名；`computed_fields` 支持数值字段、数字、括号、`+ - * /` 四则运算，以及 `round` / `abs` / `ceil` / `floor` 安全函数白名单，不开放裸 SQL、任意函数、子查询或条件表达式。
- HTTP 数据源使用 `http_passthrough`，支持配置 GET / POST JSON、路径、请求参数 JSON、JSON Body、响应数据路径和总数路径；路径、请求参数和 JSON Body 里的 `:param_name` 会按运行时同名参数替换。
- 查询模板保存 / 更新会按数据源类型做深度配置校验：MySQL 模板会连接目标数据源并复用 `SqlBuilder` 校验表、字段、条件、排序、别名、计算字段、聚合指标和参数定义；HTTP 模板会校验方法、请求参数、JSON Body、响应路径、总数路径和最终公开 URL。无效配置会在保存阶段直接返回业务错误，不再等到预览或运行时才暴露。

### 模板市场 `#/saiboard/market`

- 支持大屏模板和组件模板两类市场项，后台可维护名称、分类、说明、公开状态、状态和模板 JSON。
- 大屏编辑器顶部提供「模板市场」和「保存为模板」入口；左侧组件面板提供「组件市场」入口；右侧属性 / 批量操作区支持把当前选中组件保存为组件模板。
- `index` / `options` 只返回模板摘要；完整 `content` 必须通过 `read` 权限读取，避免列表权限直接暴露模板 JSON。
- 套用大屏模板会替换当前草稿画布、背景和组件，并进入撤销历史；插入组件模板会复用当前复制 / 粘贴链路的 ID 重建、组 ID 重映射、边界钳制和数据预览刷新。
- 模板内容只保留组件视觉和字段映射，不保留查询模板绑定或组件级运行参数；插入后需重新选择当前用户可访问的查询模板并按需重新配置参数。

时间范围条件示例：

```json
{
  "table": "saipay_order",
  "params": [
    {
      "name": "pay_method",
      "label": "支付方式",
      "type": "string",
      "default": "wechat",
      "required": false
    },
    {
      "name": "keyword",
      "label": "订单关键词",
      "type": "string",
      "default": "",
      "required": false
    }
  ],
  "conditions": [
    { "field": "create_time", "op": "time_range", "value": "last_7_days" },
    {
      "type": "group",
      "logic": "or",
      "conditions": [
        { "field": "pay_method", "op": "=", "value": ":pay_method" },
        { "field": "order_name", "op": "like", "value": ":keyword" }
      ]
    }
  ]
}
```

公开运行时可通过 URL 传入白名单参数，例如 `/screen/order_demo?pay_method=alipay`；后端仍以大屏 `code + cid` 解析组件绑定模板，不接受前端直接传任意模板 ID。

字段别名和计算字段示例：

```json
{
  "table": "saipay_order",
  "fields": ["order_no", "order_price"],
  "field_aliases": {
    "order_no": "订单号",
    "order_price": "订单金额"
  },
  "computed_fields": [
    {
      "alias": "订单金额含税",
      "expression": "round(order_price * 1.2, 2)"
    }
  ],
  "limit": 100
}
```

多指标聚合示例：

```json
{
  "table": "saipay_order",
  "dimension": "pay_method",
  "dimension_type": "raw",
  "metrics": [
    { "alias": "订单数", "aggregate": "count" },
    { "alias": "订单金额", "aggregate": "sum", "field": "order_price" }
  ],
  "order_by": "订单金额",
  "order_type": "desc",
  "limit": 20
}
```

二维热力图聚合示例：

```json
{
  "table": "saipay_order",
  "dimension": "create_time",
  "dimension_type": "day",
  "secondary_dimension": "pay_method",
  "secondary_dimension_type": "raw",
  "metrics": [{ "alias": "数量", "aggregate": "count" }],
  "order_by": "label",
  "order_type": "asc",
  "limit": 200
}
```

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

数据源 config 只保存基础 URL、请求头和默认查询参数；具体 GET / POST、业务路径、JSON Body 和响应提取方式优先放在查询模板 config 里，便于同一个 HTTP 数据源复用多个取数模板。

### HTTP 查询模板 config

```json
{
  "path": "/orders/summary",
  "method": "POST",
  "params": {
    "tenant": "b8"
  },
  "body": {
    "range": "7d",
    "status": "paid"
  },
  "response_path": "data.items",
  "total_path": "data.total"
}
```

- `method` 只支持 `GET` / `POST`；`POST` 会用 `body` 作为 JSON 请求体，并自动补 `Content-Type: application/json`。
- `params` 会和数据源默认参数合并后拼到 URL 查询串，模板同名参数覆盖数据源默认参数。
- `response_path` / `total_path` 使用点号路径，例如 `data.items`、`result.total`；`response_path` 留空时优先识别根对象 `rows`，其次识别 `data`，最后把根对象当单行。
- 响应路径命中数组时转为多行；命中对象时转为单行；命中标量时转为 `{ "value": 标量 }`。

### HTTP 出网白名单

默认 `SAIBOARD_HTTP_ALLOWED_HOSTS` 留空时，HTTP 数据源仍会拒绝 localhost、内网、链路本地、保留地址和无法 DNS 解析的域名，但不限制公网域名。生产环境建议按实际第三方接口收紧，例如：

```env
SAIBOARD_HTTP_ALLOWED_HOSTS=api.example.com,*.trusted.example
```

- `api.example.com` 只允许精确域名。
- `*.trusted.example` 只允许子域名，例如 `order.trusted.example`，不包含根域 `trusted.example`。
- 自定义 `Host` 请求头会被忽略，后端会使用 URL 中的目标域名；curl 可用时会通过 `CURLOPT_RESOLVE` 固定到已校验公网 IP，避免校验后再次解析到内网地址。

### 数据源测试返回

后台「数据源管理」新增或编辑时可以直接点击测试。测试接口会使用当前表单里的未保存配置，不要求先保存。

- MySQL 测试执行 `SELECT 1 AS ok`，成功返回 `rows` / `total` 和脱敏诊断信息 `diagnostics.type=mysql`、`host`、`port`、`database`。
- HTTP 测试只允许公网 `http/https`，拒绝 localhost、内网地址、保留地址和无法 DNS 解析的域名；配置出网白名单后，域名还必须命中白名单；成功返回 `rows` / `total` 和 `diagnostics.host`、`diagnostics.method`、`diagnostics.status`。
- 新增 / 编辑弹窗内点击测试后，会在表单底部保留本次成功或失败结果；失败不会关闭弹窗，方便继续调整连接信息。
- HTTP `headers`、`params` 必须是 JSON 对象，例如 `{ "Authorization": "Bearer xxx" }`，不能填数组。
- HTTP 数据源表单里的「测试配置」只用于当前测试请求，不会保存到数据源；可临时填写 `path`、`method`、`params`、`body`、`response_path`、`total_path` 来模拟后续查询模板的真实请求。
- 常见 MySQL 连接错误会转成可读提示：数据库不存在、用户名或密码不正确、主机或端口无法连接。
- 数据源保存 / 更新会复用同一套连接配置校验：MySQL 必须具备主机、端口、数据库、用户名和合法字符集；HTTP 必须是 `http/https` 基础 URL，且请求头 / 默认参数必须是 JSON 对象。连接是否真实可用仍以「测试」为准。

### 查询模板取值类型

查询模板把数据源的结果统一转换成大屏组件可消费的 `rows` / `total` 结构。组件绑定查询模板后，编辑器预览和运行页都会按这个结构取数。

| 取值类型 | 数据源 | 返回结构 | 适用组件 |
| --- | --- | --- | --- |
| `table_raw` 表原始行 | MySQL | 返回明细 `rows`，字段来自「返回字段」和「计算字段」。 | 表格、排行榜、进度排行、状态矩阵、折线图、柱状图、散点图、漏斗图、热力图、K线图、仪表盘、图片轮播、时间轴、告警列表。 |
| `table_count` 表计数 | MySQL | 返回 `rows[0].total`，`total` 同步为计数值。 | 指标卡、仪表盘、总量统计、告警数量。 |
| `table_aggregate` 表聚合 | MySQL | 按维度字段分组，固定输出 `label`；配置第二维度时额外输出 `series`；再输出一个或多个聚合指标。 | 柱状图、漏斗图、环图、雷达图、趋势图、多指标对比、仪表盘、热力图、进度排行、状态矩阵。 |
| `http_passthrough` HTTP 透传 | HTTP | 支持 GET / POST JSON；若配置 `response_path` 则从指定路径提取数据，否则优先使用 `rows` / `data`；数组转多行，对象转单行，标量转 `value`。 | 外部系统指标、第三方接口、已聚合好的业务数据、进度排行、状态矩阵、漏斗图、热力图、K线图、仪表盘、时间轴、告警列表。 |

K线图需要把数据行映射为 `time`、`open`、`close`、`high`、`low` 五类字段；编辑器属性面板支持分别选择时间、开盘、收盘、最高、最低字段。字段名命中 `time/date/open/close/high/low` 等常见命名时会自动识别，未命中时手动选择即可。

仪表盘读取绑定查询模板首行的数值字段，可在属性面板配置显示名称、最小值、最大值、单位、小数位、指针和进度环；字段映射沿用通用 `valueField`，未手动选择时优先识别 `value/count/total/amount`。

漏斗图读取多行 `label/value` 数据，字段映射沿用通用 `labelField` / `valueField`；适合转化路径、销售阶段、流程流失等按阶段递减或对比的场景。属性面板支持标签、排序、图例位置、块间距和宽度范围配置。

热力图读取多行 `x/y/value` 数据，`value` 字段映射沿用通用 `valueField`，`xField` / `yField` 在组件属性面板配置；适合按时间、状态、渠道、类型组成二维矩阵的活跃度、订单量、告警密度等场景。同一个 `x/y` 组合出现多行时前端会累加数值。MySQL 查询模板可用 `table_aggregate` 的第二维度直接输出 `label/series/指标列`，热力图会默认识别 `label` 为 X 轴、`series` 为 Y 轴；也可继续使用 `table_raw` 返回明细或 `http_passthrough` 透传已聚合结果。

时间轴读取多行 `time/title/content/status` 事件数据，字段在组件属性面板配置；适合订单流转、内容发布、告警记录、任务进度等按时间展示的事件流。组件支持正序 / 倒序、最大条数、显示时间、显示内容和强调色配置；状态字段会按成功、告警、异常等常见值自动切换节点颜色。

告警列表读取多行 `time/title/content/level` 告警数据，字段在组件属性面板配置；适合监控告警、异常订单、库存预警、工单 SLA 等需要高亮风险状态的场景。标题字段必需，时间、内容和级别字段可选；级别字段命中 `critical/high/warning/low` 或「严重 / 高 / 警告 / 低」等常见值时会自动切换告警颜色。

进度排行读取多行 `label/value/target/status` 数据，字段在组件属性面板配置；适合任务完成率、库存水位、渠道目标达成、工单 SLA 等需要比较进度和排名的场景。`target` 可选，存在时按 `value / target` 计算进度；不存在时把 `value` 当作进度值，`value` 在 0 到 1 之间会自动按百分比展示。组件支持进度正序 / 倒序 / 原始顺序、最大条数、单位、小数位、显示排名、显示数值和强调色配置。

状态矩阵读取多行 `label/status/value/group` 数据，字段在组件属性面板配置；适合设备在线状态、服务健康度、区域运营状态、库存/工单/支付链路状态总览等需要快速扫描状态分布的场景。状态字段命中 `success/normal/warning/error/critical` 或「正常 / 告警 / 异常 / 严重」等常见值时会自动切换颜色；组件支持最大条数、列数、单位、小数位、显示状态、显示数值和强调色配置。

#### 查询参数类型

MySQL 模板支持 `params[]` 定义运行时参数，条件值里写 `:参数名` 即可绑定。运行时传入值优先，没有传入时使用默认值；必填参数没有值会拒绝执行。

HTTP 模板不自动透传所有 URL 参数，只替换配置里明确写出的 `:参数名`。例如路径 `/metrics/:tenant/orders`、数据源默认参数 / 模板请求参数 `{ "range": ":range" }`、JSON Body `{ "tenant": ":tenant" }` 会读取运行时 `/screen/demo?tenant=b8&range=7d` 或查询模板页「预览参数」里的同名值；未写成占位符的 URL 参数会被忽略。路径占位符只接受标量值并会 URL 编码，数组或对象只能用于请求参数 / JSON Body 的整值占位。

| 参数类型 | 说明 | 示例 |
| --- | --- | --- |
| `string` 文本 | 按字符串绑定，最长按后端限制截断。 | `pay_method=wechat` |
| `number` 数字 | 必须是数字，绑定为 int 或 float。 | `status=1`、`amount=99.5` |
| `date` 日期 | 必须是 `YYYY-MM-DD`。 | `2026-06-19` |
| `datetime` 日期时间 | 必须是 `YYYY-MM-DD HH:mm:ss`。 | `2026-06-19 00:00:00` |
| `time_range` 时间范围 | 用于时间范围条件，支持预设值。 | `today`、`yesterday`、`last_7_days`、`last_30_days`、`this_week`、`this_month`、`last_month`、`this_year` |

### 大屏适配逻辑

编辑器和运行页的适配职责不同：

- 统一缩放公式在 `widgets/fit.ts`。编辑器和运行页都通过 `resolveBoardFit()` 计算设计稿与容器之间的 scale、偏移和缩放后尺寸。
- 编辑器「适应窗口」固定使用 `contain`，按容器宽高和 32px 操作留边等比缩放，最大不超过 100%。画布内部仍保留设计稿坐标，例如 1920 × 1080；外层使用缩放后的宽高占位，避免缩小后滚动区域仍按原尺寸计算。
- 编辑器画布用了 CSS `transform: scale(...)`，`DraggableItem.vue` 和多选框会把拖拽 / 缩放事件的屏幕像素差值除以 `effectiveZoom`，再写回设计稿坐标，保证在 auto / 50% / 75% 下编辑后发布不发生坐标漂移。
- 运行页使用大屏背景配置里的 `fit_mode`：`contain` 完整显示设计稿并居中留边，`cover` 等比铺满视口并允许上下或左右裁切，`stretch` 按视口宽高分别拉伸。
- 编辑器是设计态，需要保留滚动和操作空间；运行页是展示态，会把画布绝对定位到窗口中。若顶部或底部被裁掉，优先检查该大屏是否设置了 `cover`。
- 修改 `widgets/fit.ts` 后需在 `saiadmin-artd/` 执行 `pnpm verify:saiboard-fit`，覆盖 `contain` / `cover` / `stretch` / `maxScale` / `padding` / 异常尺寸的数值回归。

## 安装和迁移（已落地）

前端已新增**一个轻量组件库** `vue3-draggable-resizable@1.6.5`：图表用已有 echarts 6 + `art-*` 组件，UI 用已有 Element Plus，拖拽/缩放由该库提供。

```bash
cd server
composer install

cd ../saiadmin-artd
pnpm install
```

数据库结构和预设数据由 Phinx 迁移维护：

```bash
cd server
php webman b8:migrate:status
php webman b8:migrate --dry-run
php webman b8:migrate
```

迁移包含：

- `20260619000100_add_saiboard_plugin.php`：建表 `saiboard_datasource` / `saiboard_screen` / `saiboard_query_template`，幂等；回滚前会检查表内业务数据，非空时拒绝删除。
- `20260619000200_add_saiboard_screen_version.php`：建表 `saiboard_screen_version`，增加版本权限；回滚前会检查版本快照数据，非空时拒绝删除。
- `20260619000300_add_saiboard_screen_token.php`：建表 `saiboard_screen_token`，增加访问令牌权限；回滚前会检查令牌数据，非空时拒绝删除。
- `20260619000400_add_saiboard_market_item.php`：建表 `saiboard_market_item`，增加模板市场菜单和权限；回滚只会删除带本迁移标记且无模板数据的表，避免误删已有模板。
- `20260619000500_add_saiboard_generate_from_table_permission.php`：增加「从数据表生成大屏」按钮权限。
- 后台菜单「大屏管理 / 数据源管理 / 查询模板 / 模板市场 / 大屏编辑器」，权限 slug 见后端分层表。
- 初始化只读账号使用说明（文档，不写入迁移）。

## 开发计划

### P0 最小可用（已完成）

| 模块 | 已落地内容 |
| --- | --- |
| 数据库 | 基础 3 张表（含 `draft_layout`/`layout` 分离）+ 版本 / 令牌 / 模板市场扩展表，共 6 张核心表；全部由 Phinx 迁移维护（含菜单权限）。 |
| 后端 | `SqlBuilder`（`table_raw` / `table_count` / `table_aggregate`）+ `DataSourceExecutor`（mysql / http GET / POST JSON + SSRF 防护 + Cache 缓存）。 |
| 后端 | `ScreenController` 标准 CRUD + `saveLayout` / `publish`；`BoardController`（`getScreen` / `data`，IDOR 绑定校验）。 |
| 后端 | `DatasourceController::test` 支持新增态 payload 测试校验，连接失败写入 `last_error` 并返回稳定错误消息；测试成功返回脱敏诊断信息。 |
| 后端 | `table_aggregate` 支持 `metrics[]` 多指标聚合和可选第二维度聚合，指标 alias 白名单化、最多 8 项，排序只允许维度、第二维度或已校验指标。 |
| 后端 | `table_raw.computed_fields` 支持 `round` / `abs` / `ceil` / `floor` 安全函数白名单，仍禁止裸 SQL、任意函数、子查询和条件表达式。 |
| 后端/前端 | MySQL 查询模板支持 `params[]` 参数白名单、`:param_name` 条件占位符、条件分组和组内 `AND / OR`；预览与公开运行时按白名单参数清洗后执行。 |
| 后端/前端 | HTTP 查询模板支持路径、请求参数和 JSON Body 中的 `:param_name` 运行时占位符替换，缓存键只包含被模板实际引用的参数。 |
| 后端/前端 | 大屏管理支持从已有 MySQL 数据源和数据表生成查询模板与鉴权草稿大屏，生成前可调整模块、维度、指标、状态和明细字段，生成后直接进入编辑器继续微调。 |
| 前端 | `DraggableItem.vue`（封装 `vue3-draggable-resizable`）+ `widgets/` 注册表，复用 `art-*` 图表（柱/折线/横向柱/K线/仪表盘/漏斗/热力图/环形/雷达/散点 + 单值指标 / 表格 / 进度排行 / 状态矩阵 / 时间轴 / 告警列表 / 点位地图）并提供 CSS 装饰边框 / 流光边框 / 扫描线 / 标题装饰 / 分割线。 |
| 前端 | 拖拽编辑器 + 草稿预览 / 编辑态真实数据预览 / 字段映射 / 组件级运行参数 + 查询模板表单化配置 + 对外运行时页（静态 `/screen/:code`、适配模式、is_public / token 鉴权）。 |
| 前端 | 数据源新增/编辑态测试前先做表单校验；查询模板支持多指标聚合配置、HTTP GET / POST JSON 配置和取值类型说明；指标组件支持前缀 / 小数位 / 单位，表格支持最大行数 / 序号列 / 斑马纹，图表组件缩放后自动触发 resize。 |

### P1 能力增强（部分完成）

- 已完成：主题预设、背景图与图片适配；横向柱图 / 双向对比柱图 / K线图 / 仪表盘 / 漏斗图 / 热力图 / 雷达图 / 散点图；时间轴；告警列表；进度排行；状态矩阵；图片轮播；点位地图；CSS 装饰边框 / 流光边框 / 扫描线装饰 / 标题装饰 / 分割线装饰；新增图表字段映射；表格列宽 / 对齐 / 字段别名展示；编辑器复制 / 粘贴 / 撤销 / 重做基础操作；编辑器基础多选、批量复制 / 删除 / 置顶 / 置底 / 对齐 / 分布 / 组合 / 取消组合 / 整体拖拽 / 整体缩放；编辑器缩放占位自适应与缩放后拖拽坐标校正；图层面板基础排序；装饰组件运行时免取数；查询模板条件分组 / OR 组合 / 二维热力图聚合；公开运行时轮询下限、IP / 大屏 / 创建人限流、Redis 原子锁优先的互斥回源、stale 缓存兜底、运行指标与缓存命中率观测。
- 未完成：更多行业专用组件仍需按实际大屏场景继续扩展；本轮已补充告警列表、进度排行、状态矩阵和流光边框，覆盖监控、异常订单、工单 SLA、目标达成、设备/服务健康度以及重点区域动态高亮等场景。

### P2 进阶

- 已完成：大屏克隆（保留草稿 / 发布布局，重置访问编码、清空旧单令牌和状态）。
- 已完成：大屏版本管理（保存 / 发布自动快照、最近 50 个版本列表、恢复到草稿、删除快照）。
- 已完成：数据权限 `scope`（按 `created_by` 隔离大屏 / 数据源 / 查询模板，并覆盖自定义数据源、预览、layout 绑定和运行统计入口）。
- 已完成：多 token 子表（`saiboard_screen_token`，按客户分发、哈希存储、可独立停用 / 重置 / 删除）。
- 已完成：组件 / 模板市场（大屏模板、组件模板、公开 / 私有可见范围、编辑器保存 / 插入 / 套用）。

## 已知边界与后续风险

1. **拖拽交互完善度**：`vue3-draggable-resizable` 已提供拖动 + 缩放 + 对齐线 + 父级边界；图表缩放后已通过组件容器 `ResizeObserver` 触发 resize。多选、组合等增量在 P1 视需要补，避免一开始过度设计。
2. **生产数据源只读账号**：预置模板已能防注入，但强烈建议生产 MySQL 数据源配只读账号作为第二道防线，需在文档和部署指引中强制说明。
3. **SSRF 防护清单**：HTTP 数据源已拒绝 localhost、内网和保留地址；生产可通过 `SAIBOARD_HTTP_ALLOWED_HOSTS` 收紧公网出网域名。curl 可用时会固定已校验 DNS 结果；若运行环境没有 curl，会使用 stream fallback 固定到首个已校验公网 IP 并设置原始 Host / SNI。
4. **缓存与限流边界**：P1 已支持运行时轮询下限、IP / 大屏 / 创建人限流、Redis 原子锁优先的互斥回源、单机文件锁兜底、stale 缓存兜底和后台运行统计；多副本生产部署应配置 Redis，Redis 不可用时的 Cache 降级限流和指标递增是弱原子语义，`CACHE_MODE=file` 只适合单机或开发环境。

## 排障

- 运行时 401：检查大屏是否公开；如为 token 模式，对外访问 `/screen/:code?token=...` 或请求头传递 `X-Saiboard-Token`；多访问令牌只在创建 / 重置时显示一次，后台列表只能看到前缀。后台管理端发布预览私有大屏会自动追加 `admin_preview=1`；草稿预览还会追加 `draft=1`，两者都需要当前后台登录态具备大屏读取权限并通过数据范围校验。
- SQL 白名单拦截：确认查询模板里的 `table`、`fields`、`conditions.field`、`order.field` 都是目标数据源真实存在的表和字段。
- HTTP 数据源失败：确认 URL 是公网 `http/https` 地址；localhost、内网 IP、保留地址和无法 DNS 解析的域名会被 SSRF 防护拦截；若配置了 `SAIBOARD_HTTP_ALLOWED_HOSTS`，还需确认目标域名命中白名单。
- 数据不刷新：检查数据源 `cache_ttl` 和组件 `dataset.refresh`；预览接口会强制绕过缓存，运行时接口会按 `cache_ttl` 复用结果，公开运行时轮询下限为 10 秒。
