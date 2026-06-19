# SAI Board 公开运行时接口

本目录维护 SAI Board 公开运行时接口的 OpenAPI 文档。

## 文件说明

- `openapi.yaml`：OpenAPI 3.0 规范文件，共导出 2 个接口。

## 来源

- APIDOC app key：`saiboard-api`
- 动态导出地址：`/apidoc/openapi/saiboard-api`
- 后端控制器：`server/plugin/saiboard/app/api/controller`
- 插件路由：`server/plugin/saiboard/config/route.php`
- 前端运行页：`saiadmin-artd/src/views/plugin/saiboard/runtime`

公开运行时接口由大屏配置决定访问方式，支持公开、子令牌和后台预览鉴权；数据接口以 `code + cid` 在服务端解析组件绑定的查询模板。
