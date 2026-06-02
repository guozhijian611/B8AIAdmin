# B8AIadmin 数据库迁移

本目录集中维护 B8AIadmin 数据库资料：

- `b8aiadmin.sql`：全量初始安装 SQL，适合新环境第一次导入。
- `migrations/`：Phinx 增量迁移，后续结构或初始化数据变更优先放这里。
- `seeds/`：Phinx seed 目录，保留给测试数据或一次性初始化数据。
- `phinx.php`：Phinx 配置，读取 `server/.env` 中的 `DB_*` 配置。

## 常用命令

所有命令默认在 `server/` 目录执行。

```bash
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

1. 创建数据库并配置 `server/.env`。
2. 导入 `Database/b8aiadmin.sql`。
3. 在 `server/` 目录执行 `php webman b8:migrate`。

`b8aiadmin.sql` 是基线，Phinx 只负责基线之后的增量变更。

## 迁移编写规范

- 文件放在 `Database/migrations/`。
- 类名使用清晰的动词短语，例如 `AddUserStatus`、`CreateDemoTable`。
- `up()` 必须尽量幂等：新增表、字段、菜单、权限前先判断是否存在。
- `down()` 只回滚当前迁移创建的内容，不要删除用户原本已有的数据。
- 涉及菜单或权限数据时，建议写入唯一 `remark` 标记，回滚时仅删除带该标记的数据。
- 涉及已有表字段修复时，如果字段可能已经在基线库存在，需要记录本迁移是否实际添加过字段，避免回滚误删基线字段。
- 不再新增独立 SQL patch；需要升级环境执行的数据库变更统一写成 Phinx 迁移。
- 生产或测试环境执行迁移前，先备份数据库并执行 `php webman b8:migrate:status` 确认状态。

## 部署建议

数据库迁移属于高风险操作，不建议随 Webman 进程启动自动执行。部署脚本如需自动迁移，应使用显式开关：

```bash
if [ "${AUTO_MIGRATE:-0}" = "1" ]; then
  cd server && php webman b8:migrate
fi
```

默认关闭，生产环境开启前先确认备份、目标数据库和回滚窗口。
