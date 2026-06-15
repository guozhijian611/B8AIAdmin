# SAI Board 大屏可视化插件说明（待开发）

本文档说明 `saiboard` 插件在 B8AIadmin 中的功能边界、技术选型、数据库设计、后端分层、前端集成、鉴权模型和开发计划。**本插件尚未实现，文档作为开发设计依据，落地时以实际代码和迁移为准。**

> 设计第一原则：**尽可能简单**。只用项目已有依赖（Vue 3 + Element Plus + echarts 6），不引入 go-view、naive-ui、DataV 等需要长期 fork 维护的重型前端工程；**编辑器与对外运行时共用同一套图表渲染组件**，保证「编辑所见 = 运行所得」，避免双引擎割裂。

## 功能定位

`saiboard` 是框架内置的数据可视化大屏插件，目标是让管理员在后台**自由拖拽**搭建大屏，绑定本地 MySQL 或远程 HTTP 数据源，并按需对外发布访问。

| 能力 | 入口 | 说明 |
| --- | --- | --- |
| 大屏管理 | `server/plugin/saiboard/app/admin/controller/ScreenController.php` | 维护大屏列表、设计尺寸、背景、对外开关与访问令牌。 |
| 数据源管理 | `server/plugin/saiboard/app/admin/controller/DatasourceController.php` | 维护 MySQL/HTTP 数据源连接配置，支持连接测试。 |
| 查询模板 | `server/plugin/saiboard/app/admin/controller/QueryTemplateController.php` | 维护预置取数模板（原始行、计数、HTTP 透传），不暴露裸 SQL。 |
| 拖拽编辑器 | `saiadmin-artd/src/views/plugin/saiboard/editor/` | Element Plus 外壳 + 薄拖拽层，组件拖拽布局、绑定查询模板，画布直接渲染真实图表组件。 |
| 对外运行时 | `saiadmin-artd/src/views/plugin/saiboard/runtime/` | 无布局菜单路由，复用**同一套** `art-*` 图表组件，全屏等比缩放渲染。 |
| 对外取数 | `server/plugin/saiboard/app/api/controller/BoardController.php` | 按组件绑定的查询模板执行数据源，返回脱敏结果。 |

后台编辑器与数据源管理都在 `saiadmin-artd/src/views/plugin/saiboard/`（Element Plus，主应用内），对外运行时也在该目录下的 `runtime/`，通过后台**无布局菜单**对外访问，后端插件位于 `server/plugin/saiboard`。

## 整体架构

```
┌──────────── 后台编辑器（主应用内，Element Plus + 薄拖拽层）────────────┐
│ 大屏列表 → 进入编辑器 → 左侧组件面板 → 拖拽到画布 → 右侧绑查询模板 → 保存 │
│ 画布渲染的就是 art-* 真实图表组件（所见即所得）                          │
└──────────────────────────┬──────────────────────────────────────────┘
                           │ layout JSON（仅组件树+绑定关系）入库
                           ▼
┌──────────────── 对外运行时（无布局菜单路由，复用同一套 art-* 组件）────────┐
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
| 运行时页 | 后台前端项目内，无布局菜单路由 | 复用构建链，一套代码；后台直接配「无布局菜单」对外，无需第二个前端工程。 |

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
│   ├── route.php         ← 对外路由 + admin 路由
│   └── middleware.php    ← admin: CheckLogin+CheckAuth+SystemLog；api: 空（自鉴权）
```

### 前端

```
saiadmin-artd/src/views/plugin/saiboard/
├── api/            screen.ts, datasource.ts, queryTemplate.ts
├── screen/         大屏列表（标准 CRUD，Element Plus）
├── datasource/     数据源管理（含测试连接 + 查询模板子管理）
├── editor/         拖拽编辑器（Element Plus 外壳 + DraggableItem + art-* 画布）
│   └── [id].vue
├── runtime/        对外运行时（无布局菜单路由，复用 widgets/）
│   └── [code].vue
└── widgets/        大屏组件注册表 + DraggableItem.vue（封装 vue3-draggable-resizable，编辑器与运行时共用）
```

> 编辑器与运行时都是主应用里的普通 Element Plus 页面：编辑器是带后台布局的菜单页，运行时配成**无布局菜单**（并加入免登录白名单，供对外公开访问），渲染组件全部复用 `widgets/`，无独立工程、无特殊构建处理。

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
| `table_aggregate` | P1 | 表聚合：维度（x 轴）+ 指标（y 轴）+ 聚合（sum/count/avg）+ 时间范围 + 分组。 |

> `table_aggregate` 本质是个 mini 取数引擎，刻意推到 P1。P0 用 `table_raw`/`table_count` 已能跑通「数据源 → 模板 → 图表」全链路。

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
      "dataset": { "queryTemplateId": 5, "refresh": 30 },
      "option": {}
    }
  ]
}
```

- `type` 直接对应 `widgets/` 注册表里的组件名，编辑器与运行时都靠它 `<component :is>` 渲染。
- `id`（如 `w_1`）是组件在大屏内的稳定标识，**取数接口以 `code + id` 为键**，不接受前端传任意 `queryTemplateId`（见安全设计）。
- 保存时 `draft_layout` 整体入库（拖拽是原子操作）；发布时拷贝到 `layout`。
- 运行时下发 `layout` 时保留 `queryTemplateId`，但**剥离所有数据源连接信息**，前端拿不到密钥。

## 后端分层

### admin（标准 `AbstractCrudController` 模式）

| 控制器 | 方法 | 权限 slug |
| --- | --- | --- |
| `ScreenController` | index / read / save / update / destroy / changeStatus / saveLayout / publish / copy | `saiboard:screen:*` |
| `DatasourceController` | 标准 CRUD + `test`（测连接 / 请求） | `saiboard:datasource:*` |
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

- `mysql`：`Db::connect($config)` 临时连接（按数据源配置即时建连、用完释放），执行 `SqlBuilder` 产出的 SELECT，返回 rows。
- `http`：Workerman HTTP 客户端，按 config（含自定义请求头）发请求，解析 JSON；**发请求前做 SSRF 校验**。
- 带 `cache_ttl` **redis** 缓存（Webman 多进程下不用文件缓存），缓存键含 `query_template_id` + 参数指纹，防高频轮询打爆源库，并发回源加互斥锁防击穿。

**`SqlBuilder`**

- 输入：`dataset_type` + `config`（表名、字段、条件、排序、limit）。
- 输出：**参数化** SELECT。
- 表名、字段名走**白名单校验**：只能是指定 datasource 库里真实存在的表 / 列（启动时按数据源缓存一份 `information_schema` 表列清单）。
- 强烈建议生产仍给数据源配**只读 MySQL 账号**，作为第二道防线。

## 安全设计

| 风险 | 防护 |
| --- | --- |
| SQL 注入 | 预置模板 + 参数化 SELECT + 表/字段白名单；生产只读账号兜底。 |
| 越权取数（IDOR） | `data` 接口以 `code + cid` 为键，组件绑定的 `queryTemplateId` 由服务端从该大屏 `layout` 解析，**前端不能指定任意模板/数据源 id**。 |
| SSRF（HTTP 数据源） | 后端代发 GET 前解析目标域名 → 拒绝内网 / 环回 / 链路本地地址（`127.0.0.0/8`、`10/8`、`172.16/12`、`192.168/16`、`169.254/16`、`::1` 等）与云元数据地址；可选出网域名白名单。 |
| 密钥泄露 | 数据源 `config`（DB 密码、请求头 token）只在后端持有，`getScreen` 下发时剥离。 |
| 公开大屏被刷 | redis 缓存 + 互斥回源；轮询频率下限在后端兜底，避免前端配过低 `refresh`。 |
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

- 三栏布局：左侧组件面板（拖出组件）、中间画布（`DraggableItem` 包裹真实 `art-*` 组件）、右侧属性面板（标题、样式、绑定查询模板、`refresh`）。
- `DraggableItem.vue` 封装 `vue3-draggable-resizable`，负责 x/y/w/h/z 的拖拽、缩放与对齐吸附，**不感知图表内容**；组件本身就是运行时同款，天然所见即所得。
- 保存写 `draft_layout`；点「发布」才拷贝到 `layout` 上线。
- 编辑器内不取真实数据可用占位/示例数据预览，避免编辑态频繁回源。

### 对外运行时页 `/screen/:code`（无布局菜单路由，复用 widgets/）

- 后台配成**无布局菜单**并加入免登录白名单（对外公开访问），不进后台布局；页面级是否放行交给后端 `data` 接口按大屏配置判定。
- 复用 `widgets/` 同一套组件，外层只读容器；按 `screen.width/height` 设计稿做**整屏等比缩放**（`transform: scale`，监听 resize），适配任意投屏分辨率。
- 按各组件 `dataset.refresh` 轮询 `/data`；深色科技风默认主题，配色在 `screen.bg_config`。

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

前端只新增**一个轻量组件库** `vue3-draggable-resizable`：图表用已有 echarts 6 + `art-*` 组件，UI 用已有 Element Plus，拖拽/缩放/对齐线由该库提供。

```bash
cd server
composer install   # 如缺 Workerman HTTP 客户端，补该依赖；其余无新增

cd ../saiadmin-artd
pnpm add vue3-draggable-resizable   # 唯一新增前端依赖（轻量，无 go-view / naive-ui / DataV）
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

### P0 最小可用（打通全链路）

| 模块 | 内容 |
| --- | --- |
| 数据库 | 3 张表（含 `draft_layout`/`layout` 分离）+ Phinx 迁移（含菜单权限）。 |
| 后端 | `SqlBuilder`（`table_raw` / `table_count`）+ `DataSourceExecutor`（mysql / http + SSRF 防护 + redis 缓存）。 |
| 后端 | `ScreenController` 标准 CRUD + `saveLayout` / `publish`；`BoardController`（`getScreen` / `data`，IDOR 绑定校验）。 |
| 前端 | `DraggableItem.vue`（封装 `vue3-draggable-resizable`）+ `widgets/` 注册表，复用 3~4 个 `art-*` 图表（柱/折线/环形 + 单值翻牌 / 表格）。 |
| 前端 | 拖拽编辑器 + 对外运行时页（无布局菜单、等比缩放、is_public / token 鉴权）。 |

### P1 能力增强

- `SqlBuilder` 补 `table_aggregate`（维度/指标/聚合/时间范围/分组）。
- 图表组件补全：地图、表格增强、翻牌、轮播；纯 CSS/SVG 装饰边框（不引第三方分支）。
- 大屏主题与背景增强。

### P2 进阶

- 组件 / 模板市场、克隆。
- 大屏版本管理（多快照，扩展 `layout` 历史）。
- 多 token 子表（`saiboard_screen_token`，按客户分发可独立吊销）。
- 数据权限 `scope`（按 `created_by` 隔离大屏归属，在对应 Logic 显式 `protected bool $scope = true;`）。

## 待确认风险点

1. **拖拽交互完善度**：`vue3-draggable-resizable` 已提供拖动 + 缩放 + 对齐线 + 父级边界，P0 直接用其能力即可；多选、组合等增量在 P1 视需要补，避免一开始过度设计。
2. **生产数据源只读账号**：预置模板已能防注入，但强烈建议生产 MySQL 数据源配只读账号作为第二道防线，需在文档和部署指引中强制说明。
3. **SSRF 防护清单**：HTTP 数据源的内网/元数据地址拦截规则需随实际部署网络环境复核，必要时加出网域名白名单。

## 排障（待补充）

落地后按实际接入情况补充：连接测试失败、SQL 白名单拦截、对外访问 401、SSRF 拦截、缓存命中、轮询超载等。
