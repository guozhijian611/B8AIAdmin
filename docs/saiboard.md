# SAI Board 大屏可视化插件说明（P0 已落地）

本文档说明 `saiboard` 插件在 B8AIadmin 中的功能边界、技术选型、数据库设计、后端分层、前端集成、鉴权模型和后续计划。当前已完成 P0 最小可用版本，实际入口以 `server/plugin/saiboard`、`saiadmin-artd/src/views/plugin/saiboard` 和 `Database/migrations/20260619000100_add_saiboard_plugin.php` 为准。

> 设计第一原则：**尽可能简单**。只用项目已有依赖（Vue 3 + Element Plus + echarts 6），不引入 go-view、naive-ui、DataV 等需要长期 fork 维护的重型前端工程；**编辑器与对外运行时共用同一套图表渲染组件**，保证「编辑所见 = 运行所得」，避免双引擎割裂。

## 功能定位

`saiboard` 是框架内置的数据可视化大屏插件，目标是让管理员在后台**自由拖拽**搭建大屏，绑定本地 MySQL 或远程 HTTP 数据源，并按需对外发布访问。

| 能力 | 入口 | 说明 |
| --- | --- | --- |
| 大屏管理 | `server/plugin/saiboard/app/admin/controller/ScreenController.php` | 维护大屏列表、设计尺寸、背景、对外开关与访问令牌。 |
| 数据源管理 | `server/plugin/saiboard/app/admin/controller/DatasourceController.php` | 维护 MySQL/HTTP 数据源连接配置，支持连接测试。 |
| 查询模板 | `server/plugin/saiboard/app/admin/controller/QueryTemplateController.php` | 维护预置取数模板（原始行、计数、聚合、HTTP 透传），不暴露裸 SQL。 |
| 拖拽编辑器 | `saiadmin-artd/src/views/plugin/saiboard/editor/` | Element Plus 外壳 + 薄拖拽层，组件拖拽布局、绑定查询模板，画布直接渲染真实图表组件。 |
| 对外运行时 | `saiadmin-artd/src/views/plugin/saiboard/runtime/` | 前端静态公开路由 `/screen/:code`，复用**同一套** `art-*` 图表组件，全屏等比缩放渲染。 |
| 对外取数 | `server/plugin/saiboard/app/api/controller/BoardController.php` | 按组件绑定的查询模板执行数据源，返回脱敏结果。 |

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
│   └─ HTTP 数据源：带请求头远程 GET（含 SSRF 防护）                      │
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

- `art-*` 图表组件（`src/components/core/charts/` 已有 bar/line/ring/radar/scatter/k-line 等）封装成统一约定的「大屏组件」：输入 `{ option/props, data }`，输出渲染。
- **编辑器画布**直接 `<component :is>` 渲染这些组件，拖拽只改外层 `DraggableItem`（封装 `vue3-draggable-resizable`）的 x/y/w/h，组件本身不感知编辑态。
- **运行时**用完全相同的组件，只是外层换成只读容器 + 全屏等比缩放。
- 结果：新增一种图表 = 加一个 `art-*` 组件 + 在组件注册表登记一次，编辑器和运行时**同时生效**，不存在两侧各实现一遍的问题。

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
│   │   ├── DataSourceExecutor.php   ← MySQL/HTTP 执行器（核心，含 SSRF 防护）
│   │   └── SqlBuilder.php           ← 预置模板拼装（参数化 SELECT，替代裸 SQL）
│   └── model/            Screen, Datasource, QueryTemplate
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
├── datasource/     数据源管理（含测试连接 + 查询模板子管理）
├── editor/         拖拽编辑器（Element Plus 外壳 + DraggableItem + art-* 画布）
│   └── [id].vue
├── runtime/        对外运行时（静态 /screen/:code 路由，复用 widgets/）
│   └── [code].vue
└── widgets/        大屏组件注册表 + DraggableItem.vue（封装 vue3-draggable-resizable，编辑器与运行时共用）
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
| `code` | varchar(32) | 对外访问编码，唯一索引。 |
| `name` | varchar(60) | 大屏名称。 |
| `width` / `height` | int | 画布设计尺寸，如 1920×1080。 |
| `bg_config` | json | 背景、主题与运行时适配：`color`、`theme`、`fit_mode`、`image`、`image_fit` 等。 |
| `is_public` | tinyint unsigned | 1对外公开 2需鉴权。 |
| `access_token` | varchar(64) NULL | `is_public=2` 时校验用，空则要求后台登录态。 |
| `draft_layout` | json | **编辑中的**组件树，`saveLayout` 只写这里。 |
| `layout` | json | **已发布的**组件树，运行时只读这里；`publish` 时由 `draft_layout` 拷贝而来。 |
| `status` | tinyint unsigned | 1已发布 2草稿。 |
| 审计字段 | | 同上。 |

> `draft_layout` 与 `layout` 分离：编辑过程不会污染线上已发布大屏，运行时永远拿稳定快照。这是 P0 就要落地的最小版本管理。

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
| `table_aggregate` | P0 + P1 | 表聚合：维度（x 轴）+ 多指标（y 轴）+ 聚合（count/sum/avg/min/max）+ 条件 + 排序 + limit。 |

> `table_aggregate` 仍保持预置模板模式，不开放裸 SQL。维度和指标字段都必须来自目标数据源真实表字段，日期维度支持原始值、按日、按月、按年。

## layout JSON 模型（自定义，极简）

```json
{
  "canvas": { "width": 1920, "height": 1080 },
  "components": [
    {
      "id": "w_1",
      "type": "art-bar-chart",
      "title": "本月订单",
      "rect": { "x": 40, "y": 40, "w": 600, "h": 360, "z": 1 },
      "dataset": {
        "queryTemplateId": 5,
        "refresh": 30,
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
- 保存时 `draft_layout` 整体入库（拖拽是原子操作）；发布时拷贝到 `layout`。
- 运行时下发 `layout` 时保留 `queryTemplateId`，但**剥离所有数据源连接信息**，前端拿不到密钥。

## 后端分层

### admin（标准 `AbstractCrudController` 模式）

| 控制器 | 方法 | 权限 slug |
| --- | --- | --- |
| `ScreenController` | index / read / save / update / destroy / changeStatus / saveLayout / publish / copy | `saiboard:screen:*` |
| `DatasourceController` | 标准 CRUD + `test`（测连接 / 请求）+ `options` / `schema`（模板配置读取） | `saiboard:datasource:*`，`options` / `schema` 复用 `saiboard:datasource:index` |
| `QueryTemplateController` | 标准 CRUD + `preview`（执行预览） | `saiboard:query_template:*` |

每个方法挂 `#[Permission('...', 'saiboard:<module>:<action>')]` 注解。写接口调用 `$this->validate('<scene>', $data)`。

### api（对外，自鉴权）— `BoardController`

- `getScreen(code)`：下发**已发布** `layout`（脱敏，不含任何密钥）。
- `data(code, cid)`：
  1. 按 `code` 取大屏 → 校验 `is_public` / `access_token` / 登录态；
  2. 在该大屏 `layout.components` 中按 `cid` 找到组件 → 取其绑定的 `queryTemplateId`（**服务端解析，不信任前端传入**）；
  3. 取 `query_template` → `DataSourceExecutor` 执行 → 返回 `{rows, total}`。

`middleware.php` 的 `api` 数组留空，鉴权在控制器内按大屏配置自行判定。

### 核心服务

**`DataSourceExecutor`**

- `mysql`：按数据源配置即时创建 PDO 连接，执行 `SqlBuilder` 产出的参数化 SELECT，返回 rows。
- `http`：只支持 GET，按数据源 config 和模板 config 拼 URL，携带自定义请求头，使用 curl（无 curl 时降级 `file_get_contents`）请求 JSON；**发请求前做 SSRF 校验**。
- 支持 `cache_ttl` 通过 `support\think\Cache` 缓存结果，缓存键含 `query_template_id`、数据源配置、模板配置和运行时白名单参数指纹，降低运行时轮询压力；缓存 miss 时优先使用 Redis token 锁做跨副本互斥，Redis 不可用时降级本机文件锁，并保留短期 stale 缓存作为数据源异常时的公开页兜底。
- 运行时指标通过 `RuntimeMetrics` 写入 Cache，统计请求、限流、缓存命中 / 未命中、stale 命中、回源成功 / 失败、Redis / 文件锁与锁等待；具备大屏列表权限的后台用户可查看单个大屏最近统计窗口内的运行统计。

**`SqlBuilder`**

- 输入：`dataset_type` + `config`（表名、字段、条件、排序、limit）。
- 输出：**参数化** SELECT。
- 表名、字段名走**白名单校验**：只能是指定 datasource 库里真实存在的表 / 列，运行时通过 `SHOW TABLES` / `SHOW COLUMNS` 复核。
- `table_aggregate` 输出统一的 `label + 指标列` 行；单指标兼容 `{label, value}`，多指标如 `{label, 订单数, 订单金额}` 可被柱状图、折线图自动识别为多系列。
- 强烈建议生产仍给数据源配**只读 MySQL 账号**，作为第二道防线。

## 安全设计

| 风险 | 防护 |
| --- | --- |
| SQL 注入 | 预置模板 + 参数化 SELECT + 表/字段白名单；生产只读账号兜底。 |
| 越权取数（IDOR） | `data` 接口以 `code + cid` 为键，组件绑定的 `queryTemplateId` 由服务端从该大屏 `layout` 解析，**前端不能指定任意模板/数据源 id**。 |
| SSRF（HTTP 数据源） | 后端代发 GET 前解析目标域名 → 拒绝内网 / 环回 / 链路本地地址（`127.0.0.0/8`、`10/8`、`172.16/12`、`192.168/16`、`169.254/16`、`::1` 等）与云元数据地址；可选出网域名白名单。 |
| 密钥泄露 | 数据源 `config`（DB 密码、请求头 token）只在后端持有，`getScreen` 下发时剥离。 |
| 公开大屏被刷 | `cache_ttl` 通过 Webman Cache 缓存结果；公开运行时下发 layout 时强制 `dataset.refresh` 不低于 10 秒；按 IP / 大屏 / 创建人维度做固定窗口限流；缓存 miss 优先使用 Redis 原子锁互斥回源，锁竞争时返回“数据缓存刷新中”，异常时可回退 stale 缓存。 |
| 日志脱敏 | 连接配置、token、Bearer 在日志/调试页脱敏，仅 `last_error` 存非敏感错误摘要。 |

## 鉴权模型

```
getScreen / data 接口入口：
  if screen.is_public == 1:    放行（公开大屏，展厅 / 投屏场景）
  if screen.is_public == 2:
      if access_token 非空:    校验请求中的 token（query 或 header）
      else:                    要求后台 JWT 登录态（复用 CheckLogin 逻辑）
```

覆盖三种场景：完全公开大屏、带令牌的对外大屏（客户专属）、仅内部登录可看的报表。

> P0 用单 `access_token` 字段（最简）。若后续需要「按客户分发多个可独立吊销的 token」，再加 `saiboard_screen_token` 子表，放 P2。

## 前端关键点

### 拖拽编辑器 `/plugin/saiboard/editor/:id`（主应用内，Element Plus）

- 三栏布局：左侧组件面板（拖出组件）、中间画布（`DraggableItem` 包裹真实 `art-*` 组件）、右侧属性面板（标题、样式、绑定查询模板、字段映射、`refresh`）。
- `DraggableItem.vue` 封装 `vue3-draggable-resizable`，负责 x/y/w/h/z 的拖拽、缩放与对齐吸附，**不感知图表内容**；组件本身就是运行时同款，天然所见即所得。
- 保存写 `draft_layout`；点「发布」才拷贝到 `layout` 上线。
- 编辑器绑定查询模板后通过 `QueryTemplate/preview` 取真实数据预览，并从首行数据生成字段映射下拉；未绑定模板时才使用示例数据占位。

### 对外运行时页 `/screen/:code`（静态公开路由，复用 widgets/）

- 在 `staticRoutes.ts` 显式注册，不进后台布局、不依赖动态菜单；页面级是否放行交给后端 `getScreen` / `data` 接口按大屏配置判定。
- 复用 `widgets/` 同一套组件，外层只读容器；按 `screen.width/height` 设计稿做运行时适配（监听 resize）。
- `bg_config.fit_mode` 支持 `contain` / `cover` / `stretch`：`contain` 完整显示设计稿并居中留边，`cover` 等比铺满视口并允许边缘裁切，`stretch` 按视口宽高分别拉伸，适合固定比例投屏。
- `bg_config` 支持 `theme` 主题预设、背景色、背景图 URL 和 `image_fit`（铺满裁切 / 完整显示 / 拉伸 / 平铺）；编辑器和运行时复用同一套样式生成逻辑。
- 按各数据组件 `dataset.refresh` 轮询 `/data`，公开运行时最小 10 秒；纯装饰组件不绑定查询模板、不触发运行时取数。

### 数据源管理页 `/plugin/saiboard/datasource`

- 标准 CRUD（Element Plus）+ 测试连接按钮 + 查询模板子管理。

### 查询模板页 `/plugin/saiboard/query-template`

- 支持按数据源读取 MySQL 表和字段，表单化配置 `table_raw` / `table_count` / `table_aggregate`。
- `table_raw` 可选返回字段、字段别名、计算字段、条件、排序和 limit。
- `table_count` 可选条件，统一返回 `{rows, total}`，其中 `total` 是计数值。
- `table_aggregate` 可选维度字段、日期粒度、多个聚合指标、条件、排序和 limit，统一返回 `{rows, total}`；每行结构为 `label + 指标列`，指标最多 8 项，非 `count` 指标必须选择数值字段。
- 条件支持 `= / != / > / >= / < / <= / like / in / between / time_range`，并支持条件组内 `AND / OR` 组合；顶层条件按 `AND` 合并，老的平铺条件数组继续兼容。`time_range` 只允许日期 / 时间字段，`value` 可用 `today`、`yesterday`、`last_7_days`、`last_30_days`、`this_week`、`this_month`、`last_month`、`this_year`。
- MySQL 查询模板支持 `params[]` 参数白名单；条件值可写 `:param_name`，预览和公开运行时传入的同名参数会按 `string` / `number` / `date` / `datetime` / `time_range` 类型清洗后再进入参数绑定。未声明参数、非法参数名、类型不匹配或必填参数缺失都会被拒绝；URL 上的未知参数会被忽略。
- `table_raw.field_aliases` 用真实字段名映射输出字段名；`computed_fields` 支持数值字段、数字、括号、`+ - * /` 四则运算，以及 `round` / `abs` / `ceil` / `floor` 安全函数白名单，不开放裸 SQL、任意函数、子查询或条件表达式。
- HTTP 数据源使用 `http_passthrough`，配置路径和请求参数 JSON。

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

## 安装和迁移（已落地）

前端已新增**一个轻量组件库** `vue3-draggable-resizable@1.6.5`：图表用已有 echarts 6 + `art-*` 组件，UI 用已有 Element Plus，拖拽/缩放由该库提供。

```bash
cd server
composer install

cd ../saiadmin-artd
pnpm install
```

数据库结构和预设数据由 Phinx 迁移 `Database/migrations/20260619000100_add_saiboard_plugin.php` 维护：

```bash
cd server
php webman b8:migrate:status
php webman b8:migrate --dry-run
php webman b8:migrate
```

迁移包含：

- 建表 `saiboard_datasource` / `saiboard_screen` / `saiboard_query_template`，幂等。
- 后台菜单「大屏管理 / 数据源管理 / 查询模板 / 大屏编辑器」，权限 slug 见后端分层表。
- 初始化只读账号使用说明（文档，不写入迁移）。

## 开发计划

### P0 最小可用（已完成）

| 模块 | 已落地内容 |
| --- | --- |
| 数据库 | 3 张表（含 `draft_layout`/`layout` 分离）+ Phinx 迁移（含菜单权限）。 |
| 后端 | `SqlBuilder`（`table_raw` / `table_count` / `table_aggregate`）+ `DataSourceExecutor`（mysql / http + SSRF 防护 + Cache 缓存）。 |
| 后端 | `ScreenController` 标准 CRUD + `saveLayout` / `publish`；`BoardController`（`getScreen` / `data`，IDOR 绑定校验）。 |
| 后端 | `DatasourceController::test` 支持新增态 payload 测试校验，连接失败写入 `last_error` 并返回稳定错误消息。 |
| 后端 | `table_aggregate` 支持 `metrics[]` 多指标聚合，指标 alias 白名单化、最多 8 项，排序只允许维度或已校验指标。 |
| 后端 | `table_raw.computed_fields` 支持 `round` / `abs` / `ceil` / `floor` 安全函数白名单，仍禁止裸 SQL、任意函数、子查询和条件表达式。 |
| 后端/前端 | MySQL 查询模板支持 `params[]` 参数白名单、`:param_name` 条件占位符、条件分组和组内 `AND / OR`；预览与公开运行时按白名单参数清洗后执行。 |
| 前端 | `DraggableItem.vue`（封装 `vue3-draggable-resizable`）+ `widgets/` 注册表，复用 `art-*` 图表（柱/折线/横向柱/环形/雷达/散点 + 单值指标 / 表格）并提供 CSS 装饰边框。 |
| 前端 | 拖拽编辑器 + 编辑态真实数据预览 / 字段映射 + 查询模板表单化配置 + 对外运行时页（静态 `/screen/:code`、适配模式、is_public / token 鉴权）。 |
| 前端 | 数据源新增/编辑态测试前先做表单校验；查询模板支持多指标聚合配置；指标组件支持前缀 / 小数位 / 单位，表格支持最大行数 / 序号列 / 斑马纹，图表组件缩放后自动触发 resize。 |

### P1 能力增强（部分完成）

- 已完成：主题预设、背景图与图片适配；横向柱图 / 雷达图 / 散点图；图片轮播；点位地图；CSS 装饰边框；新增图表字段映射；表格列宽 / 对齐 / 字段别名展示；编辑器复制 / 粘贴 / 撤销 / 重做基础操作；图层面板基础排序；装饰组件运行时免取数；查询模板条件分组 / OR 组合；公开运行时轮询下限、IP / 大屏 / 创建人限流、Redis 原子锁优先的互斥回源、stale 缓存兜底、运行指标与缓存命中率观测。
- 未完成：更多图表样式和更多装饰组件。
- 未完成：编辑器多选、组合等高级编排能力。

### P2 进阶

- 组件 / 模板市场、克隆。
- 大屏版本管理（多快照，扩展 `layout` 历史）。
- 多 token 子表（`saiboard_screen_token`，按客户分发可独立吊销）。
- 数据权限 `scope`（按 `created_by` 隔离大屏归属，在对应 Logic 显式 `protected bool $scope = true;`）。

## 已知边界与后续风险

1. **拖拽交互完善度**：`vue3-draggable-resizable` 已提供拖动 + 缩放 + 对齐线 + 父级边界；图表缩放后已通过组件容器 `ResizeObserver` 触发 resize。多选、组合等增量在 P1 视需要补，避免一开始过度设计。
2. **生产数据源只读账号**：预置模板已能防注入，但强烈建议生产 MySQL 数据源配只读账号作为第二道防线，需在文档和部署指引中强制说明。
3. **SSRF 防护清单**：HTTP 数据源已拒绝 localhost、内网和保留地址；实际部署如需进一步收紧，可加出网域名白名单。
4. **缓存与限流边界**：P1 已支持运行时轮询下限、IP / 大屏 / 创建人限流、Redis 原子锁优先的互斥回源、单机文件锁兜底、stale 缓存兜底和后台运行统计；多副本生产部署应配置 Redis，Redis 不可用时的 Cache 降级限流和指标递增是弱原子语义，`CACHE_MODE=file` 只适合单机或开发环境。

## 排障

- 运行时 401：检查大屏是否公开；如为 token 模式，访问 `/screen/:code?token=...` 或请求头传递 `X-Saiboard-Token`。
- SQL 白名单拦截：确认查询模板里的 `table`、`fields`、`conditions.field`、`order.field` 都是目标数据源真实存在的表和字段。
- HTTP 数据源失败：确认 URL 是公网 `http/https` 地址；localhost、内网 IP、保留地址和无法 DNS 解析的域名会被 SSRF 防护拦截。
- 数据不刷新：检查数据源 `cache_ttl` 和组件 `dataset.refresh`；预览接口会强制绕过缓存，运行时接口会按 `cache_ttl` 复用结果，公开运行时轮询下限为 10 秒。
