# 辅助函数说明

本文档记录 B8AIadmin 常用全局辅助函数和配置读取约定。后端辅助函数由 Webman 自动加载，业务代码可直接调用；修改 PHP、路由或配置后，需要按 Webman 常驻进程要求 reload/restart 后验证。

## JSON 响应

`ok(array|string $data = [], string $msg = 'success')`

- 返回统一成功响应：`{code: 200, message, data}`。
- 当第一个参数是字符串时，会把字符串作为成功提示。
- 启用 trace 上下文时，底层会自动追加 `trace_id`。

```php
return ok(['id' => 1]);
return ok('保存成功');
```

`fail(string $msg = 'fail', int $code = 400)`

- 返回统一失败响应，HTTP 状态保持 200，业务状态通过 `code` 表示。
- 启用 trace 上下文时，底层会自动追加 `trace_id`。

```php
return fail('参数错误');
return fail('无权限操作', 403);
```

## 配置读取

`getConfigGroup($group, bool $toKeyValue = false)`

- 读取 SaiAdmin 配置组。
- 默认保持 SaiAdmin 原始行为，返回配置项列表。
- 第二个参数传 `true` 时，自动展开为 `key => value`，适合业务直接按键名读取。
- 如果同一配置组内存在重复 `key`，展开时后出现的配置项会覆盖前面的值。

```php
$list = getConfigGroup('upload_config');
$uploadMode = \plugin\saiadmin\utils\Arr::getConfigValue($list, 'upload_mode');

$config = getConfigGroup('upload_config', true);
$uploadMode = $config['upload_mode'] ?? '';
```

`SystemConfigLogic::getGroup($group, bool $toKeyValue = false)`

- 与 `getConfigGroup()` 行为一致。
- 适合在 Logic、Service 或需要显式依赖配置逻辑的代码中使用。

```php
use plugin\saiadmin\app\logic\system\SystemConfigLogic;

$config = (new SystemConfigLogic())->getGroup('email_config', true);
$host = $config['Host'] ?? '';
```

`ConfigCache::getConfig($code, bool $toKeyValue = false)`

- 底层配置缓存读取方法。
- 默认返回缓存中的配置项列表。
- 第二个参数传 `true` 时，在读取缓存后展开为 `key => value`；缓存本身仍保存原始列表，避免影响旧调用。

```php
use plugin\saiadmin\app\cache\ConfigCache;

$config = ConfigCache::getConfig('email_config', true);
```

`Arr::getConfigValue($config, $key)`

- 从配置项列表中按 `key` 读取单项值。
- 适合保留原始配置列表结构的旧代码。

```php
use plugin\saiadmin\utils\Arr;

$config = getConfigGroup('email_config');
$host = Arr::getConfigValue($config, 'Host');
```

## 当前用户和字典

`getCurrentInfo()`

- 读取当前登录用户的 JWT 扩展信息。
- 没有请求上下文或 token 不可用时返回 `false`。

`dictDataList(string $code)`

- 根据字典编码读取字典列表。

```php
$statusList = dictDataList('data_status');
```

## 文件、路由和队列

`fastRoute(string $name, string $controller)`

- 快速注册标准 CRUD 路由：`index`、`save`、`update`、`read`、`destroy`、`import`、`export`。
- 非标准动作仍需在插件 `config/route.php` 中显式注册。

`downloadFile($fileName)`

- 从模板目录下载文件，模板不存在时抛出 `ApiException`。

`formatBytes($bytes)`

- 将字节数格式化为 `B`、`KB`、`MB`、`GB`、`TB`。

`queue_send(...)`

- 投递队列任务到指定队列配置。
- 队列消费进程、延迟队列和失败重试策略需按当前项目队列配置确认。
