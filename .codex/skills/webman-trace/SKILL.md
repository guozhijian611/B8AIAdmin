---
name: b8aiadmin-webman-trace
description: 在 B8AIadmin 中查询 openb8/webman-otel-trace 本地日志、定位 trace id 对应请求、SQL 和 Webman 异常，并沉淀修复验证流程。
---

# B8AIadmin Webman Trace 调试技能

## 适用场景

- 用户给出 32 位 `trace_id` 或完整 `traceparent`，要求查询本地 trace。
- 需要排查 `/__trace` 页面看不到、请求日志缺失、SQL 日志 trace_id 为空、Webman 异常堆栈与 trace 对不上。
- 需要修复 trace 暴露出的后端异常，并验证修复是否生效。

## 项目事实

- 运行包：`server/vendor/openb8/webman-otel-trace` 通过 Composer path/symlink 指向 `packages/webman-otel-trace`。
- 配置入口：`server/config/plugin/openb8/webman-otel-trace/`。
- 本地日志：
  - HTTP 请求：`server/runtime/logs/otel-request-*.log`
  - 业务 Span：`server/runtime/logs/otel-span-*.log`
  - SQL：`server/runtime/logs/otel-sql-*.log`
  - Webman 异常：`server/runtime/logs/webman-*.log`
- 页面入口：debug 模式下 `/__trace`，路由来自 `server/config/plugin/openb8/webman-otel-trace/route.php`。
- 业务 span 入口：`OpenB8\WebmanOtelTrace\Support\Trace::span()`，源码在 `packages/webman-otel-trace/src/Support/Trace.php`。
- 业务说明书：`Doc/webman-otel-trace.md`。
- Webman 是常驻进程，改 PHP、路由或插件配置后，验证前需要 `cd server && php start.php reload` 或重启。

## 业务 span 规范

Logic / Service 里的关键业务动作优先手动打 span，不要做全量 PHP 方法追踪：

```php
use OpenB8\WebmanOtelTrace\Support\Trace;

return Trace::span('order.pay', function () use ($orderId) {
    return $this->payOrder($orderId);
}, [
    'order.id' => $orderId,
]);
```

适合打 span 的节点：支付、AI 请求、文件解析、三方接口、队列任务主流程、复杂事务。普通 CRUD、小型私有方法、参数转换和框架内部方法不要加。

可在 span 内补充：

```php
Trace::setAttribute('model', $model);
Trace::addEvent('llm.request.start');
```

业务 span 默认由 `OTEL_BUSINESS_SPAN=true` 开启，本地页面日志由 `OTEL_BUSINESS_SPAN_FILE=true` 控制。生产若只保留 HTTP/SQL/request log，可设置 `OTEL_BUSINESS_SPAN=false`。

## 快速查询

优先使用脚本：

```bash
python3 .codex/skills/webman-trace/scripts/find_trace.py <trace_id_or_traceparent>
```

常用参数：

```bash
python3 .codex/skills/webman-trace/scripts/find_trace.py <trace_id> --server-dir server --max-files 10 --max-lines 5000
python3 .codex/skills/webman-trace/scripts/find_trace.py <trace_id> --show-payload
```

脚本会汇总：

- `otel-request` 中同 trace 的 HTTP 请求、状态码、业务 code、耗时、请求路径。
- `otel-sql` 中同 trace 的 SQL。
- `webman` 日志中包含 trace 的异常上下文。

默认会脱敏 `authorization`、`cookie`、`password`、`token`、`secret` 等字段。只有用户明确要求看完整敏感字段时，才临时读取原始日志。

## 手工兜底

当脚本没有找到结果时，按顺序确认：

1. 搜索全部运行日志：

```bash
rg -n "<trace_id>" server/runtime/logs
```

2. 若只出现在 `webman-*.log`，先读异常头、请求路径、`request_param`、`exception_info` 和堆栈底部 trace 上下文。
3. 若出现在 `otel-request-*.log`，解析 JSON，关注 `path`、`status_code`、`business_code`、`exception`、`request`、`response`。
4. 若 SQL trace_id 是 `null`，多半是定时任务、健康检查、连接 ping 或未进入 HTTP trace 上下文，不要误判为当前请求 SQL。
5. 若 `/__trace` 查不到但日志里有，检查 `trace_view.max_files`、`trace_view.max_lines`、日志日期范围和页面解析逻辑。
6. 若业务 span 看不到，先确认业务代码是否调用 `Trace::span()`、`OTEL_BUSINESS_SPAN`、`OTEL_BUSINESS_SPAN_FILE`、`runtime/logs/otel-span-*.log` 是否有同 trace_id 记录；跨服务完整时间线再看 OTLP/Jaeger/Tempo 后端。

## 修复流程

1. 用 trace 定位真实请求和异常，不要只凭前端现象猜。
2. 查控制器、Logic、Model、表结构或插件配置的真实运行入口。
3. 如果涉及 SaiAdmin `BaseModel`，记住它默认启用 ThinkORM `SoftDelete`，继承它的业务表通常需要 `delete_time` 字段；否则列表、读取、删除可能自动追加不存在的软删除条件。
4. 修复后至少执行：

```bash
git diff --check
cd server && php -l <changed-php-file>
```

5. 数据库结构问题要用 `SHOW CREATE TABLE` 或 `information_schema` 复核；需要升级环境执行的数据库变更统一放到 `Database/migrations/`，写成可回滚的 Phinx 迁移。
6. PHP 或配置改动后 reload Webman 再请求验证；数据库迁移修复则先执行 `php webman b8:migrate`，再重新请求触发接口。

## 输出建议

最终回复用户时说明：

- trace 对应的请求路径和异常摘要。
- 根因文件/表结构。
- 已做的修复和验证命令。
- 是否执行了数据库迁移，生产环境是否还需要执行同名迁移。
