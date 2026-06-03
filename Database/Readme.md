# B8AIadmin 数据库迁移

本目录集中维护 B8AIadmin 数据库资料：

- `b8aiadmin.sql`：全量初始安装 SQL，适合新环境第一次导入。
- `migrations/`：Phinx 增量迁移，后续结构或初始化数据变更优先放这里。
- `seeds/`：Phinx seed 目录，保留给测试数据或一次性初始化数据。
- `phinx.php`：Phinx 配置，读取 `server/.env` 中的 `DB_*` 配置。

## 常用命令

所有命令默认在 `server/` 目录执行。

```bash
# 首次安装向导：配置 .env、创建数据库、导入基线 SQL 并执行迁移
php webman b8:install

# 查看迁移状态
php webman b8:migrate:status

# 执行迁移
php webman b8:migrate

# 预览迁移 SQL，不写入数据库
php webman b8:migrate --dry-run

# 回滚最后一批迁移，默认会二次确认
php webman b8:migrate:rollback

# 跳过确认回滚，适合部署脚本显式调用
php webman b8:migrate:rollback --force

# 创建迁移
php webman b8:migrate:create AddExampleTable
```

也可以直接调用 Phinx：

```bash
cd server
vendor/bin/phinx status -c ../Database/phinx.php
vendor/bin/phinx migrate -c ../Database/phinx.php
vendor/bin/phinx rollback -c ../Database/phinx.php
```

## 新环境初始化流程

1. 在 `server/` 目录执行 `composer install`。如果 `.env` 不存在，会自动从 `.env.example` 复制。
2. 执行 `php webman b8:install`，按提示配置数据库。
3. 安装向导会在目标库不存在时询问是否创建，空库会导入 `Database/b8aiadmin.sql`，然后执行 `php webman b8:migrate`。

`b8aiadmin.sql` 是基线，Phinx 只负责基线之后的增量变更。

如需手动安装，也可以按下面顺序执行：

```bash
cp server/.env.example server/.env
# 编辑 server/.env 的 DB_* 配置
mysql -u root -p b8aiadmin < Database/b8aiadmin.sql
cd server && php webman b8:migrate
```

## 迁移编写规范

- 文件放在 `Database/migrations/`。
- 类名使用清晰的动词短语，例如 `AddUserStatus`、`CreateDemoTable`。
- `up()` 必须尽量幂等：新增表、字段、菜单、权限前先判断是否存在。
- `down()` 只回滚当前迁移创建的内容，不要删除用户原本已有的数据。
- 涉及菜单或权限数据时，建议写入唯一 `remark` 标记，回滚时仅删除带该标记的数据。
- 涉及已有表字段修复时，如果字段可能已经在基线库存在，需要记录本迁移是否实际添加过字段，避免回滚误删基线字段。
- 不再新增独立 SQL patch；需要升级环境执行的数据库变更统一写成 Phinx 迁移。
- 生产或测试环境执行迁移前，先备份数据库并执行 `php webman b8:migrate:status` 确认状态。

## 需求驱动建表约定

当用户只提供业务想法或自然语言需求时，先产出表结构方案，再创建迁移。不要直接进入 SaiCode 生成。

### 1. 表命名

- 业务表统一使用小写蛇形命名。
- 插件业务表建议带插件或业务前缀，例如 `demo_feedback`、`saiuser_member_tag`。
- 不要新建与 `sa_system_*`、`saicode_*`、`sa_tool_*` 等框架元数据冲突的表名。
- 多对多关系表建议使用两个主体名组合，例如 `demo_post_tag`，并保留独立 `id` 主键。

### 2. 必备字段

常规后台 CRUD 表默认包含：

| 字段 | 建议类型 | 说明 |
| --- | --- | --- |
| `id` | `int unsigned auto_increment` | 主键 |
| `created_by` | `int DEFAULT NULL` | 创建者，数据权限和审计使用 |
| `updated_by` | `int DEFAULT NULL` | 更新者 |
| `create_time` | `datetime DEFAULT NULL` | 创建时间 |
| `update_time` | `datetime DEFAULT NULL` | 更新时间 |
| `delete_time` | `datetime DEFAULT NULL` | 软删除 |

如果是纯关联表、日志表、导入临时表或外部同步镜像表，可以省略审计/软删除字段，但需要在迁移或最终说明中写明原因。

### 3. 字段语义

- `status` 默认使用 `tinyint unsigned NOT NULL DEFAULT 1`，注释写清枚举，例如 `状态：1正常 2停用`。
- `is_*` 是否类字段默认使用 `tinyint unsigned NOT NULL DEFAULT 2`，约定 `1是 2否`。
- `sort` 默认使用 `int unsigned NOT NULL DEFAULT 100`。
- 金额使用 `decimal(10,2)` 或按业务明确精度，不用浮点保存金额。
- 图片字段优先命名为 `image` / `cover_image` / `avatar`，文件字段优先命名为 `file` / `attachment`，便于 SaiCode 推断上传控件。
- 标题、名称字段优先使用 `title` / `name` / `*_name`，便于 SaiCode 推断模糊搜索。
- 外键字段使用 `<主体>_id`，例如 `category_id`、`member_id`。
- 敏感字段如手机号、邮箱、token、secret、password_hash 必须在注释和代码中体现脱敏或安全约束。

### 4. 数据字典

自然语言需求中出现固定可选项时，先判断是否需要数据字典：

- 通用启停状态复用 `data_status`。
- 是否类字段复用 `yes_or_no`。
- 性别、附件类型、支付方式等已有通用字典优先复用现有编码。
- 业务专属枚举必须新建字典，例如 `feedback_priority`、`ticket_type`、`order_source`。
- `status` 如果表达业务流程状态，例如待审核、已通过、已拒绝，不应复用 `data_status`，应新建业务状态字典。

字典落库使用 `sa_system_dict_type` 和 `sa_system_dict_data`：

| 表 | 说明 |
| --- | --- |
| `sa_system_dict_type` | 字典类型，核心字段为 `name`、`code`、`status` |
| `sa_system_dict_data` | 字典项，核心字段为 `type_id`、`label`、`value`、`color`、`code`、`sort`、`status` |

表规格 JSON 中用顶层 `dicts` 声明需要新建的字典，用字段级 `dict` 引用字典编码。迁移脚手架会幂等插入字典类型和字典项，回滚时只删除本迁移创建的字典记录。

### 5. 索引和唯一约束

- 高频筛选字段需要索引：`status`、`created_by`、`create_time`、`delete_time`、外键字段。
- 唯一业务编号、手机号、邮箱、第三方 openid 等需要唯一索引时，必须确认是否允许软删除后重复。
- 组合唯一索引要按业务唯一性声明，例如同一会员同一标签只允许一条：`member_id + tag_id`。

### 6. 自动化脚手架

可先把需求整理成表规格 JSON，再生成 Phinx 迁移：

```bash
php .codex/skills/saicode/scripts/create_table_migration.php \
  --spec=.codex/skills/saicode/templates/table_spec.example.json \
  --dry-run
```

生成真实迁移文件：

```bash
php .codex/skills/saicode/scripts/create_table_migration.php \
  --spec=/path/to/table.json
```

该脚本只生成迁移，不执行数据库变更。生成后仍需在 `server/` 目录执行：

```bash
php webman b8:migrate:status
php webman b8:migrate --dry-run
```

确认无误后再执行：

```bash
php webman b8:migrate
```

如果表规格中字段配置了 `dict`，表装载到 SaiCode 后继续应用字段字典配置：

```bash
cd server
php ../.codex/skills/saicode/scripts/saicode_generate.php apply-spec \
  --id=<saicode_table_id> \
  --spec=../.codex/skills/saicode/templates/table_spec.example.json
```

## 部署建议

数据库迁移属于高风险操作，不建议随 Webman 进程启动自动执行。部署脚本如需自动迁移，应使用显式开关：

```bash
if [ "${AUTO_MIGRATE:-0}" = "1" ]; then
  cd server && php webman b8:migrate
fi
```

默认关闭，生产环境开启前先确认备份、目标数据库和回滚窗口。
