---
name: b8-phinx
description: B8AIadmin 框架数据库迁移技能。Use when Codex needs to create, run, rollback, validate, or debug Phinx migrations, b8:install, b8:migrate, Database/b8aiadmin.sql baselines, Database/migrations incrementals, or new-project migration failures in this Webman/SaiAdmin framework.
---

# B8 Phinx 数据迁移

## 适用场景

- 用户要求新增、执行、回滚或排查数据库迁移。
- 新项目安装或复制框架后运行 `php webman b8:install`、`php webman b8:migrate` 报错。
- 需要同步数据库文档、OpenAPI、表结构、菜单权限、初始化数据。
- 需要判断数据库变更应该进入 `Database/b8aiadmin.sql`、`Database/migrations/` 还是当前库修复。

## 项目事实

- 后端命令默认在 `server/` 执行。
- Phinx 配置：`Database/phinx.php`。
- 基线 SQL：`Database/b8aiadmin.sql`，用于首次安装导入基础库结构和基础数据。
- 增量迁移：`Database/migrations/`，用于基线之后的升级变更。
- Seed 目录：`Database/seeds/`，当前业务初始化更常见做法是写幂等迁移。
- 安装命令：`php webman b8:install` 会配置数据库、导入基线并执行迁移。
- 迁移命令：`php webman b8:migrate`、`php webman b8:migrate:status`、`php webman b8:migrate --dry-run`、`php webman b8:migrate:rollback`、`php webman b8:migrate:create <Name>`。
- 项目已经不再以 `Database/patches/` 作为主要数据库升级路径。

## 工作流

1. 先执行 `git status --short`，确认是否有无关改动；只暂存本次迁移相关文件。
2. 查真实源头：现有表用 `SHOW CREATE TABLE` 或 `information_schema`，现有路由/控制器/菜单用实际文件和数据库，不凭生成器假设。
3. 判断落点：
   - 首次安装基线缺失的框架基础结构，才考虑更新 `Database/b8aiadmin.sql`。
   - 版本升级、新功能表、字段、菜单、权限、初始化配置，默认新增 `Database/migrations/` 迁移。
   - 临时线上修复、数据库覆盖、生产自动迁移等高风险事项，先问用户确认。
4. 用 `cd server && php webman b8:migrate:create <Name>` 创建迁移，或按现有命名补充文件。
5. 迁移必须尽量幂等：建表用 `CREATE TABLE IF NOT EXISTS` 或先判断；插入菜单、权限、配置时用 `NOT EXISTS`、唯一业务键或可控 `ON DUPLICATE KEY`。
6. 迁移必须支持回滚；如果不可逆，要在迁移代码和最终回复中说明原因。
7. 验证链：

```bash
cd server
php -l ../Database/migrations/<file>.php
php webman b8:migrate --dry-run
php webman b8:migrate
php webman b8:migrate:status
```

8. 迁移执行后，用实际数据库复核影响：

```sql
SHOW CREATE TABLE your_table;
SHOW COLUMNS FROM your_table LIKE 'field_name';
SELECT ... FROM ... WHERE ...;
```

9. 提交前执行 `git diff --check`，然后中文 Conventional Commit，例如 `feat: 增加队列表迁移`、`fix: 修复迁移元数据主键非空约束`。

## 编写规则

- 修改业务表字段前先判断表和字段是否存在，避免新项目、旧项目、重复执行时失败。
- 新增软删除模型表时确认 `delete_time`；继承 SaiAdmin `BaseModel` 的表通常会被软删除条件影响。
- 菜单、权限、字典、配置迁移要避免回滚误删用户数据。优先用 `remark`、唯一业务键、默认占位值等标记“本迁移创建且仍未被用户修改”的数据。
- 回滚初始化配置时，只删除仍匹配迁移默认值或迁移标记的数据。
- 不要把真实生产凭据、Token、Cookie 写入迁移。
- Phinx 表定义里，参与主键或唯一关键业务键的字段要显式声明 `null => false`。
- 对 MySQL 8，复合主键字段如果没有显式 `NOT NULL`，可能触发 `1171 All parts of a PRIMARY KEY must be NOT NULL`。
- Webman 是常驻进程；PHP、路由或插件配置变更后验证前考虑 reload/restart。纯数据库迁移一般不需要 reload，但接口重新请求要确认当前进程已读到最新代码。

## 新项目迁移排障

遇到 `php webman b8:migrate` 报错时，先读完整输出里的四个点：

- `using config file`：确认实际使用哪个项目的 `Database/phinx.php`。
- `using database`：确认目标库，不要把本地、测试、线上混掉。
- `Migration Name` 或堆栈里的迁移文件路径：定位真正失败的迁移。
- SQLSTATE 错误码和 Phinx 调用位置：判断是 SQL、表结构、权限还是迁移幂等问题。

典型问题：

- `1171 All parts of a PRIMARY KEY must be NOT NULL`：复合主键字段允许 NULL。修复迁移中参与主键的字段，显式加 `null => false`，然后重新 `--dry-run` 和执行迁移。
- 字段已存在：迁移缺少 `hasTable()` / `hasColumn()` 判断，补成幂等逻辑。
- 表不存在：确认基线 SQL 是否已导入，或迁移是否需要在缺表时跳过。
- 菜单插入失败：检查 `sa_system_menu` 当前结构、父级菜单 `code` 是否存在、唯一字段是否冲突。
- 迁移状态不一致：先用 `php webman b8:migrate:status` 看 `phinxlog`，不要直接手改日志表，除非用户确认并清楚后果。

## 最终回复要点

- 说明失败迁移、错误码和根因。
- 说明修改了哪些迁移文件，是否已执行真实迁移。
- 列出验证命令和数据库复核结果。
- 如果存在未处理的无关工作区改动，说明已隔离。
- 如果生产或其他新项目还需要执行迁移，明确命令和风险确认点。
