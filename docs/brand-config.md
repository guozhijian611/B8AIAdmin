# 品牌配置说明 (Brand Config)

本文档说明了 B8AIAdmin 的白标（White-Label）品牌配置方案，当前主要实现了 K1 阶段（后台可配置 + 种子默认中性化），并规划了 K2 阶段（公开品牌 API）。

## K1: 品牌种子与配置就绪

在 K1 阶段，系统内硬编码或带有「SaiAdmin」展示属性的文案和默认设置已改为 B8AIAdmin 或中性描述。此改动主要位于：
1. `sa_system_config` 中的 `site_config` 组。
2. `sa_site_info` 表中的默认数据。
3. `sa_system_user` 的管理员个性签名。
4. 前端配置的降级系统名称。

> **注意**：K1 不修改代码中原有的 `SaiAdmin` 目录名、类名及相关 PHP 命名空间，从而避免与上游市场生态产生代码级别的冲突。

### 品牌配置键及默认值表

以下是 `sa_system_config` 中站点配置组 (group_id = 1) 的各项默认值：

| 配置键 (Key) | 配置项 (Name) | 类型 | 默认值 (K1 更新后) | 说明 |
| --- | --- | --- | --- | --- |
| `site_name` | 网站名称 | input | **B8AIAdmin** | 原 SaiAdmin |
| `site_logo` | 站点Logo | uploadImage | `(空字符串)` | 新增（移除原 saithink 图标） |
| `site_favicon` | 站点Favicon | uploadImage | `(空字符串)` | 新增 |
| `site_desc` | 网站描述 | textarea | B8AIAdmin 可配置品牌的中后台管理系统 | 原基于 vue3 + webman 框架说明 |
| `site_keywords` | 网站关键字 | input | B8AIAdmin,后台管理系统 | 补充 B8AIAdmin |
| `site_copyright`| 版权信息 | textarea | Copyright © 2026 B8AIAdmin | 替换原 saithink |
| `site_record_number` | 网站备案号 | input | `(空字符串)` | 去除原虚假备案号 (9527) |

`sa_site_info` (id = 1) 的种子数据亦做了相应对齐。

## K2: 公开品牌 API 需求清单 (规划)

为了使前台或登录页面能够在用户未登录时动态加载白标品牌，K2 需要开发一个免鉴权的公开品牌 API（如 `/api/v1/system/brand` 或在获取验证码/系统配置公开接口中集成）。

**需要暴露的字段清单如下**：

1. **`site_name`**: 用于页面 `<title>` 和登录页主标题。
2. **`site_logo`**: 用于左上角/登录页的品牌 Logo 图像 URL。
3. **`site_favicon`**: 用于页面 `<head>` 的网站图标。
4. **`site_desc`**: 用于 SEO `<meta name="description">` 或登录页副标题/欢迎语。
5. **`site_keywords`**: 用于 SEO `<meta name="keywords">`。
6. **`site_copyright`**: 登录页底部的版权声明。
7. **`site_record_number`**: 登录页底部的备案号展示及外链。

*(注：K2 的 API 实现及 K3 的前端完整接线不包含在本次 K1 的范围中。)*
