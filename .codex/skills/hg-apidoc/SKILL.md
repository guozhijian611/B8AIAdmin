---
name: hg-apidoc
description: hg/apidoc 注解与 Webman/SaiAdmin 接口文档开发技能。Use when Codex needs to add, review, repair, or configure APIDOC annotations, config/plugin/hg/apidoc settings, /apidoc/openapi/{appKey} exports, or unibest automatic API generation links in this B8AIadmin project.
---

# HG APIDOC

## Scope

Use this skill when writing or changing controller methods that should be visible in APIDOC, when fixing APIDOC install/config issues, or when wiring APIDOC output into unibest/OpenAPI tooling.

Primary local facts:

- Backend root: `server/`.
- Installed packages: `hg/apidoc` and `hg/apidoc-export`.
- Config: `server/config/plugin/hg/apidoc/app.php`.
- Route registration: `server/config/plugin/hg/apidoc/route.php` calls `hg\apidoc\providers\WebmanService::register()`.
- OpenAPI export bridge: `server/app/controller/ApidocOpenapiController.php`.
- Export route: `/apidoc/openapi/{appKey}`.
- Current APIDOC apps are mobile-facing only: `saiai-api`, `saiuser-api`, `saipay-api`.

## Operating Rules

- Check `git status --short` before edits and keep unrelated user changes isolated.
- Prefer PHP 8 attributes with alias import:

```php
use hg\apidoc\annotation as Apidoc;
```

- Add APIDOC annotations to new controller methods while adding SaiAdmin `#[Permission(...)]` attributes.
- Use explicit `#[Apidoc\Url(...)]` for Webman/SaiAdmin routes. Do not rely on auto URL for deep plugin namespaces unless you have verified the generated URL.
- Keep backend/admin-only interfaces out of the mobile APIDOC apps unless the user explicitly asks to expose admin docs.
- After PHP, route, or APIDOC config edits, consider Webman reload before runtime validation.
- For unibest automatic API generation, use the exported OpenAPI endpoint for the target app key, not the APIDOC UI route.

## Annotation Pattern

For a controller class:

```php
#[Apidoc\Group('运维管理')]
#[Apidoc\Title('数据库导入导出')]
class DatabaseBackupController extends BaseController
{
}
```

For a JSON endpoint:

```php
#[Apidoc\Title('数据库导入导出概览')]
#[Apidoc\Url('/core/database-backup/index')]
#[Apidoc\Method('GET')]
#[Apidoc\Query('source', type: 'string', require: false, default: 'mysql', desc: '数据库连接名')]
#[Apidoc\Returned('database', type: 'string', desc: '数据库名')]
#[Permission('数据库导入导出列表', 'core:database-backup:index')]
public function index(Request $request): Response
{
    // ...
}
```

For a file upload endpoint:

```php
#[Apidoc\Title('导入 SQL 文件')]
#[Apidoc\Url('/core/database-backup/import')]
#[Apidoc\Method('POST')]
#[Apidoc\ContentType('multipart/form-data')]
#[Apidoc\Param('file', type: 'file', require: true, desc: 'SQL 文件')]
#[Apidoc\Param('drop_table_if_exists', type: 'boolean', require: false, default: false, desc: '表已存在时是否先删除')]
#[Permission('数据库导入', 'core:database-backup:import')]
public function import(Request $request): Response
{
    // ...
}
```

## Common Attributes

- `Apidoc\Group`: class-level API group.
- `Apidoc\Title`: class or method display name.
- `Apidoc\Url`: exact route path.
- `Apidoc\Method`: HTTP method, usually `GET`, `POST`, `PUT`, `DELETE`.
- `Apidoc\Query`: query-string parameter for GET routes.
- `Apidoc\Param`: request body, form, or file parameter.
- `Apidoc\Returned`: response data field under the configured global `data` node.
- `Apidoc\ContentType`: request content type, especially `multipart/form-data`.

Read `references/annotation-cheatsheet.md` when you need parameter signatures, mobile app key details, or validation commands.

## Validation

Run focused checks from `server/`:

```bash
php -l path/to/Controller.php
php webman route:list | rg 'target-route'
```

If APIDOC config or route provider changed:

```bash
php webman reload
```

For OpenAPI export checks, use:

```bash
curl -s http://127.0.0.1:8787/apidoc/openapi/saiai-api | head
```
