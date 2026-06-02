---
name: SaiCode 低代码生成器
description: SaiAdmin 低代码生成插件使用指南，覆盖 Web 端装载、配置、表单设计、搜索设计、预览、生成到项目、ZIP 下载，以及 Agent 辅助脚本流程。
---

# SaiCode 低代码生成器

SaiCode 是 SaiAdmin 的 Web 端低代码生成插件，主要入口在管理后台页面，CLI 脚本只是给 Agent 做批量、复现和自动化辅助。处理 SaiCode 任务时，优先以真实 Web 端流程和插件运行时代码为准，不要只根据脚本推断能力。

## 需求到 CRUD 的自动全流程

当用户只输入“我想做一个 XXX 管理”这类业务想法时，不能直接调用 SaiCode。SaiCode 的前提是数据库表已经存在，因此自动全流程必须按下面顺序推进：

```
需求澄清 -> 表结构方案 -> Phinx 建表迁移 -> dry-run -> migrate -> SaiCode 装载表 -> Web/CLI 配置 -> 预览 -> 生成到项目 -> 验证
```

高风险边界：

- 可以自动整理表结构方案、生成迁移文件、运行 `php -l`、运行 `php webman b8:migrate:status` 和 `php webman b8:migrate --dry-run`。
- 真正执行 `php webman b8:migrate`、覆盖已有表、生产库迁移、生成到项目文件前，必须确认目标环境、数据库备份和用户意图。
- 用户没有指定插件名、菜单归属、是否需要数据权限、是否需要移动端 API 时，先根据项目上下文推断；推断风险高时问用户。

### 1. 从需求拆表

先把自然语言需求整理为一份表结构方案，至少包含：

| 项目 | 说明 |
| --- | --- |
| 业务实体 | 例如反馈、工单、分类、标签、订单 |
| 表名 | 小写蛇形，避免和 `sa_system_*`、`saicode_*`、`sa_tool_*` 冲突 |
| 表注释 | 中文业务名称，用于 SaiCode 菜单和页面理解 |
| 字段 | 字段名、类型、是否必填、默认值、注释 |
| 关系 | 一对多、多对多、外键字段、关联显示字段 |
| 权限 | 是否需要 `created_by` 数据权限 |
| 索引 | 外键、状态、时间、唯一约束 |
| SaiCode 目标 | `namespace`、`package_name`、`business_name`、上级菜单 |

默认建表约定参考 `Database/Readme.md` 的「需求驱动建表约定」和 `Doc/database-schema-standard.md` 的全局约定。

### 2. 建表默认规则

常规后台 CRUD 表默认包含：

| 字段 | 规则 |
| --- | --- |
| `id` | `int unsigned auto_increment` 主键 |
| `created_by` | `int DEFAULT NULL`，用于审计和“仅本人”数据权限 |
| `updated_by` | `int DEFAULT NULL` |
| `create_time` | `datetime DEFAULT NULL` |
| `update_time` | `datetime DEFAULT NULL` |
| `delete_time` | `datetime DEFAULT NULL`，软删除 |

字段命名要服务 SaiCode 推断：

- 名称/标题字段用 `name`、`title`、`*_name`，方便自动设置 `like` 搜索。
- 图片字段包含 `image`，文件字段包含 `file` 或 `attach`，方便自动设置上传组件。
- `status` 默认 `tinyint unsigned NOT NULL DEFAULT 1`，注释写清 `1正常 2停用` 等枚举。
- `is_*` 字段默认 `tinyint unsigned NOT NULL DEFAULT 2`，约定 `1是 2否`。
- `sort` 默认 `int unsigned NOT NULL DEFAULT 100`。
- 金额用 `decimal`，不要用 float。
- 外键字段用 `<entity>_id`，并在 SaiCode 关联配置中配合 `table_field` 显示关联名称。

可例外的表：

- 纯关联表可以只保留 `id`、两侧外键和必要唯一索引，不一定需要软删除。
- 日志/流水表通常不更新，不一定需要 `updated_by`，但要明确归属字段和时间字段。
- 外部同步镜像表按第三方数据结构建模，但必须在说明中标注来源和同步策略。

### 3. 表规格 JSON

Agent 可先生成一份 JSON 表规格，再由脚本生成 Phinx 迁移。示例：

```
.codex/skills/saicode/templates/table_spec.example.json
```

核心字段：

| 字段 | 说明 |
| --- | --- |
| `table` | 表名，小写蛇形 |
| `comment` | 表注释 |
| `class` | 迁移类名，可省略 |
| `audit` | 是否自动补 `created_by`、`updated_by`、`create_time`、`update_time`，默认 true |
| `soft_delete` | 是否自动补 `delete_time`，默认 true |
| `fields` | 业务字段数组 |
| `indexes` | 索引数组 |

字段类型使用 Phinx 类型或常见别名：`string`/`varchar`、`text`、`integer`/`int`、`tinyint`、`biginteger`/`bigint`、`decimal`、`datetime`、`date`、`boolean`。

### 4. 生成 Phinx 迁移

脚本位置：

```
.codex/skills/saicode/scripts/create_table_migration.php
```

预览迁移内容：

```bash
php .codex/skills/saicode/scripts/create_table_migration.php \
  --spec=.codex/skills/saicode/templates/table_spec.example.json \
  --dry-run
```

生成迁移文件：

```bash
php .codex/skills/saicode/scripts/create_table_migration.php \
  --spec=/path/to/table-spec.json
```

脚本会写入 `Database/migrations/`，生成的迁移具备：

- 表存在时 `up()` 自动跳过。
- 新建成功后写入 `phinx_migration_meta` 标记。
- `down()` 只在确认该迁移实际创建过表时删除，避免误删已有表。

迁移验证：

```bash
cd server
php webman b8:migrate:status
php webman b8:migrate --dry-run
```

确认后再执行：

```bash
php webman b8:migrate
```

### 5. 迁移后进入 SaiCode

表创建完成后才进入 SaiCode：

1. 用 Web 端「装载」或 CLI `load --table=<table>` 装载表。
2. 配置 `template`、`namespace`、`package_name`、`business_name`、`belong_menu_id`。
3. 根据表设计配置字段、表单、搜索、关联。
4. 先 `preview` 或 ZIP，确认生成代码。
5. 再生成到项目并执行后端/前端验证。

## 真实入口

后端插件目录：

```
server/plugin/saicode/
```

管理端页面：

```
saiadmin-artd/src/views/plugin/saicode/index.vue
saiadmin-artd/src/views/plugin/saicode/components/
saiadmin-artd/src/views/plugin/saicode/api/table.ts
```

Web API 使用 Webman 插件默认路由，当前 `server/plugin/saicode/config/route.php` 没有显式注册业务路由。前端实际调用：

| 功能 | 方法 | 路径 |
| --- | --- | --- |
| 已装载表列表 | GET | `/app/saicode/table/index` |
| 数据源列表 | GET | `/app/saicode/table/source` |
| 数据源数据表 | GET | `/app/saicode/table/sourceTable` |
| 装载数据表 | POST | `/app/saicode/table/loadTable` |
| 读取生成配置 | GET | `/app/saicode/table/read?id=<id>` |
| 保存生成配置 | PUT | `/app/saicode/table/update?id=<id>` |
| 字段同步 | POST | `/app/saicode/table/sync?id=<id>` |
| 代码预览 | GET | `/app/saicode/table/preview?id=<id>` |
| ZIP 下载 | POST | `/app/saicode/table/generate` |
| 生成到项目 | POST | `/app/saicode/table/generateFile` |
| 字段列表 | GET | `/app/saicode/table/getTableColumns` |
| 保存表单设计 | POST | `/app/saicode/table/saveDesign` |
| 保存搜索设计 | POST | `/app/saicode/table/saveSearchDesign` |
| 删除装载记录 | DELETE | `/app/saicode/table/realDestroy` |

核心后端逻辑：

```
server/plugin/saicode/app/controller/TableController.php
server/plugin/saicode/app/logic/TableLogic.php
server/plugin/saicode/app/logic/ColumnLogic.php
server/plugin/saicode/app/logic/DbLogic.php
server/plugin/saicode/utils/code/CodeEngine.php
server/plugin/saicode/utils/code/CodeZip.php
server/plugin/saicode/utils/code/stub/saiadmin/
```

数据表：

```
saicode_table
saicode_column
```

字段结构以 `Database/b8aiadmin.sql` 中的 `saicode_table`、`saicode_column` 为准；如果涉及线上或本地现有库，优先用 `SHOW CREATE TABLE` / `information_schema` 复核。

## Web 端配置主流程

### 1. 打开低代码页面并装载数据表

在管理后台进入 SaiCode 页面，点击「装载」：

1. 选择数据源，来源于 `config('think-orm.connections')`。
2. 查询数据源中的表。
3. 选择目标表并提交。
4. 后端读取表注释和字段结构，写入 `saicode_table` / `saicode_column`。

装载默认值由 `TableLogic::loadTable()` 决定：

| 字段 | 默认值 |
| --- | --- |
| `template` | `app` |
| `stub` | `think` |
| `tpl_category` | `single` |
| `generate_path` | `saiadmin-artd` |
| `belong_menu_id` | `80` |
| `generate_menus` | `index,save,update,read,destroy` |
| `span` | `24` |

注意：插件模式不是装载后自动创建插件骨架。若目标是新插件，先在 `server/` 执行：

```bash
php webman sai:plugin <插件名>
```

然后再把 SaiCode 的「应用类型」设为 `plugin`，「应用名称」设为插件名。

### 2. 编辑生成信息

表格操作栏点击编辑按钮，进入「编辑生成信息」。该抽屉包含 4 个 Tab。

「配置信息」字段：

| 字段 | 说明 |
| --- | --- |
| `table_name` | 数据库表名，只读 |
| `table_comment` | 表描述，可改为更适合菜单/页面的中文名 |
| `class_name` | 实体类名，装载时自动去前缀并转 PascalCase |
| `business_name` | 业务名，生成前端目录和 API 文件名 |
| `source` | 数据源连接名 |
| `template` | `app` 或 `plugin` |
| `namespace` | app 应用名或插件名，Web 端禁止 `saiadmin` |
| `package_name` | 后端 controller/logic/validate/model 的模块分组 |
| `tpl_category` | `single` 单表 CRUD；`tree` 树表 CRUD |
| `generate_path` | 前端项目根目录名，通常为 `saiadmin-artd` |
| `stub` | `think` 或 `eloquent` |
| `belong_menu_id` | 所属菜单，Web 端通过菜单级联选择 |
| `menu_name` | 生成菜单显示名 |
| `component_type` | `1` 模态框；`2` 抽屉 |
| `form_width` | 表单宽度 |
| `is_full` | `1` 否；`2` 是 |

树表配置只在 `tpl_category=tree` 时显示，并写入 `saicode_table.options`：

| 字段 | 说明 |
| --- | --- |
| `tree_id` | 树主 ID，一般是主键 |
| `tree_parent_id` | 父级 ID，如 `parent_id` |
| `tree_name` | 树节点名称字段，如 `name` |

「字段配置」用于配置列表和基础字段属性：

| 字段 | 说明 |
| --- | --- |
| `column_comment` | 字段描述 |
| `table_field` | 关联显示，如 `category.category_name` |
| `column_width` | 列表宽度 |
| `is_edit` | 查看详情是否显示 |
| `is_list` | 列表是否显示 |
| `is_sort` | 列表是否允许排序 |
| `is_insert` | 表单是否显示 |
| `is_query` | 搜索是否显示 |
| `list_sort` | 拖拽后的列表排序 |

「菜单功能」只展示扩展功能：

| 值 | 说明 |
| --- | --- |
| `import` | 生成导入功能 |
| `export` | 生成导出功能 |

基础 CRUD 权限 `index,save,update,read,destroy` 由装载默认值带入，保存时会随 `generate_menus` 写入数据库。勾选导入/导出时要确认业务表和模板字段适合导入导出。

「关联配置」写入 `saicode_table.options.relations`：

| 字段 | 说明 |
| --- | --- |
| `type` | `hasOne` / `hasMany` / `belongsTo` / `belongsToMany` |
| `name` | 关联名称，生成代码中用于 `with()` 和 `table_field` 前缀 |
| `model` | 关联模型类名 |
| `localKey` | 本地键 |
| `foreignKey` | 外键或关联主键 |
| `table` | 多对多中间模型 |

关联显示需要两步配合：先在「关联配置」定义 relation，再在「字段配置」里把外键字段的 `table_field` 设为 `relationName.displayField`。

### 3. 表单设计

点击操作栏的表单设计按钮。表单设计会读取 `getTableColumns`，保存到 `saveDesign`。

表单设计负责：

- 拖拽字段顺序，保存为 `form_sort`。
- 在右侧「字段操作」勾选字段是否进入表单，保存为 `is_insert`。
- 设置字段标题、默认值、必填、栅格宽度 `span`。
- 设置表单全局宽度、全屏、弹窗/抽屉、默认栅格。
- 配置控件类型和控件 options。

可用表单控件来自 `saiadmin-artd/src/views/plugin/saicode/ts/vars.ts`：

| 值 | 控件 |
| --- | --- |
| `input` | 输入框 |
| `password` | 密码框 |
| `textarea` | 文本域 |
| `inputNumber` | 数字输入框 |
| `inputTag` | 标签输入框 |
| `switch` | 开关 |
| `slider` | 滑块 |
| `select` | 数据下拉框 |
| `saSelect` | 字典下拉框 |
| `treeSelect` | 树形下拉框 |
| `radio` | 字典单选框 |
| `checkbox` | 字典复选框 |
| `date` | 日期选择器 |
| `time` | 时间选择器 |
| `rate` | 评分器 |
| `cascader` | 级联选择器 |
| `userSelect` | 用户选择器 |
| `uploadImage` | 图片上传 |
| `imagePicker` | 图片选择 |
| `uploadFile` | 文件上传 |
| `chunkUpload` | 大文件切片 |
| `editor` | 富文本编辑器 |

常见 `options`：

| 控件 | options |
| --- | --- |
| `inputNumber` / `slider` / `rate` | `min`、`max`、`step` |
| `select` / `treeSelect` / `cascader` | `field_label`、`field_value`、`url`；`cascader` 还支持 `check_strictly` |
| `uploadImage` / `imagePicker` / `uploadFile` / `chunkUpload` | `multiple`、`limit` |
| `editor` | `height` |
| `date` | `mode=date/datetime` |

### 4. 搜索设计

点击操作栏的搜索设计按钮。搜索设计保存到 `saveSearchDesign`。

搜索设计负责：

- 在右侧「字段操作」勾选字段是否作为搜索条件，保存为 `is_query`。
- 拖拽搜索字段顺序，保存为 `query_sort`。
- 设置搜索栅格 `query_span`。
- 设置查询方式 `query_type`。
- 设置搜索控件 `query_component`、字典 `query_dict`、远程数据 `query_options`。

搜索控件：

| 值 | 控件 |
| --- | --- |
| `input` | 输入框 |
| `radio` | 字典单选框 |
| `saSelect` | 字典下拉框 |
| `date` | 日期选择器 |
| `select` | 数据下拉框 |
| `treeSelect` | 树形下拉框 |
| `cascader` | 级联选择器 |

查询方式：

```
eq, neq, gt, gte, lt, lte, like, in, notin, between
```

### 5. 预览、下载 ZIP、生成到项目

操作栏能力：

| 操作 | 说明 |
| --- | --- |
| 预览 | 在线查看 10 个生成文件内容，不写入项目 |
| 代码下载 | 调用 `generate`，生成 ZIP 并下载 |
| 生成到项目 | 调用 `generateFile`，写入后端/前端文件并创建菜单权限 |
| 字段同步 | 重新读取数据库字段，字段类型未变化时尽量保留旧配置 |

`generateFile` 仅允许 `config('app.debug', true)` 为真时执行；非调试模式会抛出「非调试模式下，不允许生成文件」。生产环境不要直接执行生成到项目。

生成到项目会：

1. 调用 `TableLogic::updateMenu()` 创建或更新菜单与按钮权限。
2. 调用 `TableLogic::genModule()` 写入后端和前端文件。
3. 清理 `UserMenuCache`。
4. 如果启用导入，写入导入模板 Excel。

## 生成文件路径

预览和 ZIP 会生成 10 个文件。

插件模式 `template=plugin` 的后端路径：

```
server/plugin/{namespace}/app/admin/controller/{package_name}/{ClassName}Controller.php
server/plugin/{namespace}/app/admin/logic/{package_name}/{ClassName}Logic.php
server/plugin/{namespace}/app/admin/validate/{package_name}/{ClassName}Validate.php
server/plugin/{namespace}/app/model/{package_name}/{ClassName}.php
```

应用模式 `template=app` 的后端路径：

```
server/app/{namespace}/controller/{package_name}/{ClassName}Controller.php
server/app/{namespace}/logic/{package_name}/{ClassName}Logic.php
server/app/{namespace}/validate/{package_name}/{ClassName}Validate.php
server/app/{namespace}/model/{package_name}/{ClassName}.php
```

前端路径：

```
{generate_path}/src/views/plugin/{namespace}/{package_name}/{business_name}/index.vue
{generate_path}/src/views/plugin/{namespace}/{package_name}/{business_name}/modules/edit-dialog.vue
{generate_path}/src/views/plugin/{namespace}/{package_name}/{business_name}/modules/view-dialog.vue
{generate_path}/src/views/plugin/{namespace}/{package_name}/{business_name}/modules/table-search.vue
{generate_path}/src/views/plugin/{namespace}/api/{package_name}/{business_name}.ts
```

SQL 预览/ZIP 文件：

```
sql/sql.sql
```

## 生成后必须检查

后端：

```bash
cd server
php -l plugin/<namespace>/app/admin/controller/<package>/<ClassName>Controller.php
php -l plugin/<namespace>/app/admin/logic/<package>/<ClassName>Logic.php
php -l plugin/<namespace>/app/admin/validate/<package>/<ClassName>Validate.php
php -l plugin/<namespace>/app/model/<package>/<ClassName>.php
```

如果依赖 Webman 插件默认路由，`route:list` 不一定显示 SaiCode 生成的默认插件路由，要用实际前端 API 路径或运行时页面验证：

```
/app/<namespace>/admin/<package>/<ClassName>/index
/app/<namespace>/admin/<package>/<ClassName>/read
/app/<namespace>/admin/<package>/<ClassName>/save
/app/<namespace>/admin/<package>/<ClassName>/update
/app/<namespace>/admin/<package>/<ClassName>/destroy
```

修改 PHP、插件配置或路由后，Webman 是常驻进程，验证前考虑：

```bash
cd server
php start.php reload
```

前端：

```bash
cd saiadmin-artd
pnpm lint
```

如果项目当前没有可用 lint 或依赖未安装，至少检查生成路径、import 路径、API 路径和页面菜单组件路径。

菜单权限：

- `updateMenu()` 会写入 `sa_system_menu.generate_id`。
- 生成后通常还需要给目标角色分配新菜单权限。
- 如果前端看不到菜单，先检查菜单记录、角色权限、用户菜单缓存和重新登录。

## Agent 辅助 CLI 脚本

脚本位置：

```
.codex/skills/saicode/scripts/saicode_generate.php
```

执行方式：

```bash
cd server
php ../.codex/skills/saicode/scripts/saicode_generate.php <command> [options]
```

也可以通过环境变量指定 server 目录：

```bash
SERVER_DIR=/path/to/project/server php .codex/skills/saicode/scripts/saicode_generate.php list
```

脚本定位：

- 用于 Agent 批量装载、复现配置、查看字段、更新少量字段、同步字段、预览、ZIP、生成到项目、撤回。
- 复杂的表单设计和搜索设计仍优先使用 Web 端，因为 Web 端能直接预览布局和控件效果。
- 脚本直接操作 `saicode_table` / `saicode_column`，执行前必须确认目标库是当前任务需要的库。

命令：

| 命令 | 说明 |
| --- | --- |
| `list` | 列出已装载表 |
| `menus [--parent=ID]` | 查看菜单树，确定 `belong_menu_id` |
| `load --table=表名 [--source=mysql]` | 装载表 |
| `config --id=ID ...` | 查看或修改生成配置 |
| `columns --id=ID` | 查看字段配置 |
| `columns --id=ID --set=字段:属性=值,属性=值` | 修改普通字段属性 |
| `columns --id=ID --set-json=字段:options=JSON` | 修改 `options` / `query_options` |
| `relation --id=ID` | 查看关联配置 |
| `relation --id=ID --add ...` | 添加关联 |
| `relation --id=ID --del=名称` | 删除关联 |
| `sync --id=ID` | 同步数据库字段 |
| `preview --id=ID` | 输出预览代码 |
| `zip --ids=1,2 [--output=code.zip]` | 生成 ZIP 包 |
| `generate --id=ID` | 生成到项目 |
| `rollback --id=ID [--clean]` | 删除生成文件和菜单，`--clean` 同时删除装载记录 |
| `all --table=表名 --namespace=xx --package=xx ...` | 装载、配置、生成到项目 |

常用参数：

| 参数 | 说明 |
| --- | --- |
| `--source=mysql` | 数据源 |
| `--namespace=<插件或应用名>` | 应用名称 |
| `--package=<模块>` | `package_name` 简写 |
| `--template=plugin/app` | 生成模式 |
| `--generate_path=saiadmin-artd` | 前端根目录 |
| `--belong_menu_id=<id>` | 所属菜单 |
| `--generate_menus=index,save,update,read,destroy,export` | 权限功能 |
| `--tpl_category=single/tree` | 单表或树表 |
| `--tree_id=id` | 树表主 ID |
| `--tree_parent_id=parent_id` | 树表父 ID |
| `--tree_name=name` | 树表名称字段 |
| `--menu_name=菜单名` | 菜单名 |
| `--component_type=1/2` | 模态框/抽屉 |
| `--form_width=600` | 表单宽度 |
| `--is_full=1/2` | 是否全屏 |
| `--span=24` | 默认表单栅格 |

推荐 CLI 工作流：

```bash
cd server

# 1. 查看菜单，确定 belong_menu_id
php ../.codex/skills/saicode/scripts/saicode_generate.php menus
php ../.codex/skills/saicode/scripts/saicode_generate.php menus --parent=80

# 2. 装载表
php ../.codex/skills/saicode/scripts/saicode_generate.php load --table=sa_member --source=mysql

# 3. 配置生成信息
php ../.codex/skills/saicode/scripts/saicode_generate.php config \
  --id=1 \
  --template=plugin \
  --namespace=saiuser \
  --package=member \
  --belong_menu_id=80 \
  --menu_name=会员管理

# 4. 查看和微调字段
php ../.codex/skills/saicode/scripts/saicode_generate.php columns --id=1
php ../.codex/skills/saicode/scripts/saicode_generate.php columns \
  --id=1 \
  --set=status:view_type=radio,dict_type=data_status,is_query=2,query_type=eq
php ../.codex/skills/saicode/scripts/saicode_generate.php columns \
  --id=1 \
  --set-json=avatar:options='{"multiple":false,"limit":1}'

# 5. 可选：关联显示
php ../.codex/skills/saicode/scripts/saicode_generate.php relation \
  --id=1 \
  --add \
  --type=belongsTo \
  --name=level \
  --model=MemberLevel \
  --localKey=level_id \
  --foreignKey=id
php ../.codex/skills/saicode/scripts/saicode_generate.php columns \
  --id=1 \
  --set=level_id:table_field=level.name

# 6. 预览或 ZIP
php ../.codex/skills/saicode/scripts/saicode_generate.php preview --id=1
php ../.codex/skills/saicode/scripts/saicode_generate.php zip --ids=1 --output=runtime/saicode-member.zip

# 7. 确认 debug 模式和路径后，再生成到项目
php ../.codex/skills/saicode/scripts/saicode_generate.php generate --id=1
```

树表示例：

```bash
php ../.codex/skills/saicode/scripts/saicode_generate.php config \
  --id=2 \
  --template=plugin \
  --namespace=demo \
  --package=system \
  --tpl_category=tree \
  --tree_id=id \
  --tree_parent_id=parent_id \
  --tree_name=name
```

撤回示例：

```bash
php ../.codex/skills/saicode/scripts/saicode_generate.php rollback --id=1
php ../.codex/skills/saicode/scripts/saicode_generate.php rollback --id=1 --clean
```

撤回脚本会删除按当前配置推导出的后端/前端文件和 `generate_id=<id>` 的菜单权限。执行前必须确认生成记录配置没有被改到别的路径，避免删错。

## Agent 判断清单

执行或修改 SaiCode 相关任务前，按这个顺序确认：

1. 用户要的是 Web 端配置、脚本自动化、模板改造，还是生成某个业务 CRUD。
2. 先查 `git status --short`，隔离用户已有改动。
3. 如果用户只有自然语言需求，先拆实体、字段、索引和权限边界，并生成 Phinx 建表迁移；不要跳过数据库建表阶段直接进 SaiCode。
4. 如果生成到插件，确认插件目录已存在；新插件先用 `php webman sai:plugin <name>`。
5. 确认目标表已存在，字段具备主键；涉及数据权限时确认 `created_by` 等审计字段。
6. 用 Web 端或 `menus` 命令确认 `belong_menu_id`，不要猜。
7. 生成前先预览或 ZIP；只有确认 debug、路径、菜单归属后才生成到项目。
8. 生成后跑 PHP 语法检查；必要时 reload Webman。
9. 检查前端 API 路径和菜单组件路径是否匹配。
10. 如修改模板或脚本，验证脚本帮助、PHP 语法和至少一个非破坏性命令。
11. 本仓库规范要求功能/文档/规范变更后提交中文 Conventional Commit。

## 常见坑

- `route.php` 为空不代表没有接口；SaiCode Web API 使用插件默认路由。
- SaiCode 生成的业务 CRUD 也常依赖插件默认路由；`route:list` 看不到时，要用实际 `/app/<namespace>/admin/...` 路径验证。
- `generateFile` 会写项目文件和菜单权限，只能在 debug 模式执行。
- `generate_path` 是前端项目根目录名，必须与 `server/` 同级。
- Web 端编辑信息会把 `belong_menu_id` 级联数组保存为最后一级 ID。
- 字段同步会重建字段记录，但字段类型未变化时会保留一批人工配置；字段类型变化时要重新核对。
- `options` / `query_options` 是 JSON，CLI 批量改时用 `--set-json`。
- 生成到模块后如看不到菜单，优先检查角色权限和用户菜单缓存，不要只改前端路由。
