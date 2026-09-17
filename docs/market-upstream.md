# 上游商店配置与开关

本文档说明如何配置和开启 saipackage 的上游应用市场。

## 配置项

您可以在 `server/.env`（复制自 `server/.env.example`）中配置以下键值对：

- `MARKET_UPSTREAM_ENABLED`：是否开启上游应用市场，可选值为 `true/1/yes/on`（开启），其余值为关闭。默认值为 `false`。
- `MARKET_UPSTREAM_BASE_URL`：上游应用市场的基址，用于指定插件市场后端接口地址。默认值为 `https://saas.saithink.top/dev-api`。

## 关闭时行为

当 `MARKET_UPSTREAM_ENABLED` 为 `false`（或缺省）时：

1. 前端插件管理界面将隐藏“上游市场”Tab。
2. 后端所有在线商店相关的接口（如获取列表、登录、下载等）直接返回 `上游应用市场已关闭` 错误，不会向 `MARKET_UPSTREAM_BASE_URL` 发起任何网络请求。
3. 本地 zip 上传和安装功能不受影响，仍然可用。

## 如何开启

1. 在 `server/.env` 文件中，将 `MARKET_UPSTREAM_ENABLED` 设置为 `true`。
2. （可选）根据需要修改 `MARKET_UPSTREAM_BASE_URL`。
3. 重启 Webman 服务（`php start.php restart` 或重载）以使配置生效。
4. 刷新前端页面，“上游市场”Tab 即可出现，并能够正常访问上游市场资源。

## 安装来源 install_source

插件安装时，系统会在插件的 `info.ini`（位于 `runtime/saipackage/{app}/info.ini`）中记录安装来源标记 `install_source`。

支持的三个值为：
- `upstream`：上游在线商店下载安装。
- `b8_local`：本地 zip 上传。默认所有本地上传的插件都会带有此标记。
- `b8_remote`：远程 B8 市场（目前仅作预留）。

## 扩展中心三 Tab

插件安装页（扩展中心）分为：**已安装**（本地列表，含 `install_source`）、**B8 市场**（占位，引导本地上传，不接远程 API）、**上游市场**（仅当 `MARKET_UPSTREAM_ENABLED` 开启且前端读到 `upstream_enabled=true` 时显示）。
