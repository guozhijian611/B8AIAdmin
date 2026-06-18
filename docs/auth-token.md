# 认证 Token 与单设备登录说明

本文档说明 B8AIadmin 当前后台认证 token 的签发、校验、存储位置，以及 `tinywan/jwt` 单设备登录开关的真实影响。

## 当前结论

- 后台登录 token 不是存数据库后按表查询校验，而是使用 `Tinywan\Jwt\JwtToken` 签发 JWT。
- 默认配置下，服务端主要靠 JWT 签名、过期时间和扩展字段校验 token。
- 管理端前端会把 `accessToken`、`refreshToken` 持久化到浏览器 `localStorage`。
- `is_single_device` 默认为 `false` 时，Redis 不参与 token 强校验。
- `is_single_device` 改为 `true` 后，调用同一套 `JwtToken::generateToken()` 和 `JwtToken::verify()` / `JwtToken::getExtend()` 的认证链路会自动参与单设备登录。

## 后台登录签发流程

后台账号登录入口在：

- `server/plugin/saiadmin/app/controller/LoginController.php`
- `server/plugin/saiadmin/app/logic/system/SystemUserLogic.php`

登录成功后，`SystemUserLogic::login()` 会调用：

```php
JwtToken::generateToken([
    'access_exp' => $access_exp,
    'id' => $adminInfo->id,
    'username' => $adminInfo->username,
    'type' => $type,
    'plat' => 'saiadmin',
]);
```

返回结构包含：

- `token_type`
- `expires_in`
- `access_token`
- `refresh_token`

其中 `access_token` 和 `refresh_token` 都是 JWT。JWT 载荷里会包含 `extend` 扩展字段，例如用户 ID、用户名、登录类型和 `plat=saiadmin`。

JWT 配置在：

```text
server/config/plugin/tinywan/jwt/app.php
```

关键配置：

```php
'algorithms' => 'HS256',
'access_secret_key' => '...',
'access_exp' => 7200,
'refresh_secret_key' => '...',
'refresh_exp' => 604800,
'refresh_disable' => false,
'is_single_device' => false,
'cache_token_pre' => 'JWT:TOKEN:',
'cache_refresh_token_pre' => 'JWT:REFRESH_TOKEN:',
```

注意：`server/plugin/saiadmin/config/saithink.php` 里的 `access_exp` 会在后台登录时覆盖 access token 有效期，当前后台登录逻辑优先读取：

```php
config('plugin.saiadmin.saithink.access_exp', 3 * 3600)
```

## 后台接口校验流程

后台接口登录校验在：

```text
server/plugin/saiadmin/app/middleware/CheckLogin.php
```

核心流程：

```php
$token = JwtToken::getExtend();

if ($token['plat'] !== 'saiadmin') {
    throw new ApiException('登录凭证校验失败');
}
```

`JwtToken::getExtend()` 底层会读取请求头：

```http
Authorization: Bearer <access_token>
```

然后执行 JWT 解码、签名校验、过期时间校验，并返回 JWT 的 `extend` 字段。

权限校验在 `CheckAuth` 中继续读取 `getCurrentInfo()`，而 `getCurrentInfo()` 也会调用 `JwtToken::getExtend()`。因此后台常规受保护接口无需在控制器里重复解析 token。

## 前端 token 存储位置

管理端前端登录页在：

```text
saiadmin-artd/src/views/auth/login/index.vue
```

登录成功后调用：

```ts
userStore.setToken(access_token, refresh_token)
```

用户 store 在：

```text
saiadmin-artd/src/store/modules/user.ts
```

该 store 使用 Pinia persist，并指定：

```ts
persist: {
  key: 'user',
  storage: localStorage
}
```

所以管理端 token 默认保存在浏览器 `localStorage` 中。

请求拦截器在：

```text
saiadmin-artd/src/utils/http/index.ts
```

每次请求会自动添加：

```ts
request.headers.set('Authorization', `Bearer ` + accessToken)
```

## 单设备登录开关

开启方式：

```php
// server/config/plugin/tinywan/jwt/app.php
'is_single_device' => true,
```

开启后，`JwtToken::generateToken()` 会在签发 token 时自动写 Redis：

```php
RedisHandler::generateToken($config['cache_token_pre'], $client, $uid, $config['access_exp'], $tokens['access_token']);
RedisHandler::generateToken($config['cache_refresh_token_pre'], $client, $uid, $config['refresh_exp'], $tokens['refresh_token']);
```

校验 token 时，`JwtToken::verify()` 会自动从 Redis 读取当前用户缓存的 token 并对比：

```php
RedisHandler::verifyToken($cacheTokenPre, $client, (string)$decodeToken['extend']['id'], $token);
```

如果同一个用户再次登录，新 token 会覆盖 Redis 中的旧 token。旧浏览器或旧设备再次请求受保护接口时，会因为 Redis 中缓存的 token 不一致而被强制下线。

## Redis 依赖

开启 `is_single_device` 后，Redis 会成为登录签发和受保护接口校验的强依赖。

Redis 配置在：

```text
server/config/redis.php
server/.env
server/.env.example
```

常用环境变量：

```dotenv
REDIS_HOST = 127.0.0.1
REDIS_PORT = 6379
REDIS_PASSWORD = ''
REDIS_DB = 0
```

如果 Redis 不可用，`JwtToken` 会抛出异常，登录或鉴权会失败。

## 重要边界和风险

### 1. 只有走 JwtToken 服务的链路才自动生效

以下调用会自动参与单设备登录：

- `JwtToken::generateToken()`
- `JwtToken::getExtend()`
- `JwtToken::verify()`

如果某个业务自己手写 JWT 解码，或者完全绕开 `Tinywan\Jwt\JwtToken`，就不会自动参与单设备登录。

### 2. Redis 单设备 key 默认只区分 client 和用户 ID

`tinywan/jwt` 默认 Redis key 形态是：

```text
JWT:TOKEN:<client>:<uid>
JWT:REFRESH_TOKEN:<client>:<uid>
```

当前 `saiadmin` 后台登录签发时没有传 `client`，会走默认 `WEB`。

当前 `saiuser` 会员登录签发时也没有传 `client`，同样会走默认 `WEB`。

这意味着如果直接全局打开 `is_single_device=true`，不同平台之间只要用户 ID 相同，就可能互相覆盖 Redis token。例如：

- 后台管理员 `id=1`
- 会员用户 `id=1`

二者都使用默认 `WEB` client 时，会写到同一类 Redis key 上，可能导致互踢。

如果要让后台、会员端、移动端分别单设备，应在签发 token 时显式增加不同 `client`，例如：

```php
JwtToken::generateToken([
    'client' => 'SAIADMIN_WEB',
    'id' => $adminInfo->id,
    'username' => $adminInfo->username,
    'plat' => 'saiadmin',
]);
```

会员端可使用类似：

```php
'client' => 'SAIUSER_' . $platform_id,
```

这样 Redis key 会按端隔离，避免不同平台相同 ID 互相踢下线。

### 3. 开启后已有登录态需要重新登录

`is_single_device=false` 时签发的旧 token 没有写 Redis。开启单设备后，旧 token 下次校验时可能因为 Redis 中没有对应缓存而失效。

切换配置后应通知用户重新登录。

### 4. Webman 常驻进程需要 reload 或 restart

修改 `server/config/plugin/tinywan/jwt/app.php` 后，Webman 常驻进程不会自动读取新配置。

本地可在 `server/` 目录执行：

```bash
php start.php reload
```

或按当前部署方式重启 Webman。

### 5. 当前后台退出主要是前端清理本地 token

当前管理端 `logOut()` 主要清理前端 store 和 `localStorage` 中的 token。后台 `LoginController` 当前没有专门的 logout 接口调用 `JwtToken::clear()`。

因此：

- 新登录踢旧登录：开启 `is_single_device` 后可自动实现。
- 点击退出后服务端立即让当前 token 失效：需要新增 logout 接口并调用 `JwtToken::clear()`。

## 推荐开启步骤

如果只需要后台管理端同账号新登录踢旧登录，推荐步骤：

1. 确认 Redis 可用。
2. 修改 `server/config/plugin/tinywan/jwt/app.php`：

```php
'is_single_device' => true,
```

3. reload 或 restart Webman。
4. 退出当前浏览器登录态并重新登录。
5. 使用同一后台账号在另一个浏览器或无痕窗口登录。
6. 回到旧浏览器访问任意受保护接口，确认旧 token 被强制下线。

如果项目同时启用了 `saiuser` 会员端，建议先补齐 `client` 隔离，再开启全局单设备登录。

## 验证建议

后端配置变更后：

```bash
cd server
php -l config/plugin/tinywan/jwt/app.php
php start.php reload
```

Redis key 可用 `redis-cli` 观察，前缀为：

```text
JWT:TOKEN:
JWT:REFRESH_TOKEN:
```

示例：

```bash
redis-cli keys 'JWT:*'
```

生产环境不建议频繁使用 `keys`，可改用 `scan`。

## 安全要求

- 不要在日志、请求记录、操作记录中保存原始 `Authorization`、`access_token`、`refresh_token`。
- 排查认证问题时优先记录是否携带 token、认证 scheme、用户 ID、平台、token hash 或 JWT claim 摘要。
- 如需临时打开原始请求头调试，只能在本机或短时间调试环境使用，并在结束后恢复脱敏。
