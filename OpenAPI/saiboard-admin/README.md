# SAI Board 后台管理接口

本目录维护 SAI Board 后台管理接口的 OpenAPI 文档。

## 文件说明

- `openapi.yaml`：OpenAPI 3.0 规范文件，共导出 43 个接口。

## 来源

- APIDOC app key：`saiboard-admin`
- 动态导出地址：`/apidoc/openapi/saiboard-admin`
- 后端控制器：`server/plugin/saiboard/app/admin/controller`
- 插件路由：`server/plugin/saiboard/config/route.php`
- 前端调用：`saiadmin-artd/src/views/plugin/saiboard/api`

后台管理接口默认使用 `Authorization` 请求头，并受 SaiAdmin 登录、权限和数据范围控制。
