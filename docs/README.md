# B8AIadmin 开发文档

> 基于 PHP 8.3 / Webman / SaiAdmin / Vue 3 / uni-app 的全栈开发框架

## 技术栈

| 层级 | 技术 |
|------|------|
| 后端 | PHP 8.3、Webman、SaiAdmin、ThinkORM、MySQL 8.0、Phinx |
| 管理端 | Vue 3、Art Design Pro、Element Plus |
| 移动端 | uni-app、unibest |
| 部署 | 宝塔面板 / Docker |

## 快速导航

### 🚀 快速开始

- [部署指南](deployment-guide.md) — 宝塔面板 / Linux 服务器部署检查
- [辅助函数](helper-functions.md) — 全局辅助函数和配置读取约定
- [认证 Token](auth-token.md) — 后台认证 Token 签发、校验与单设备登录

### ⚙️ 核心功能

- [队列管理](queue-management.md) — Redis / RabbitMQ 队列配置、投递与消费
- [APIDOC 与 unibest 联动](apidoc-unibest.md) — 注解生成 API 文档，自动生成移动端接口
- [OpenTelemetry Trace](webman-otel-trace.md) — 分布式追踪与业务埋点

### 🧩 业务插件

- [B8CMS 独立站](b8cms.md) — CMS 插件、模板开发与多语言 SEO
- [SAI AI 插件](saiai.md) — AI 多模态接入与后台测试台
- [SaiPay 支付](saipay-payment.md) — 支付方式、订单流程与人工确认
- [SAI Board 大屏](saiboard.md) — 大屏可视化设计与开发（待开发）

### 📦 构建与发布

- [Webman 二进制打包](webman-binary-build.md) — 独立二进制文件打包与部署
- [Docker 镜像发布](docker-release.md) — Docker 构建、导出与远程部署

### 📚 参考

- [数据表结构规范](database-schema-standard.md) — 全量表结构、字段语义与审计约定
