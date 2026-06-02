# hg/apidoc 注解速查

## 当前项目应用

`server/config/plugin/hg/apidoc/app.php` 当前只保留移动端相关应用：

| appKey | title | path |
| --- | --- | --- |
| `saiai-api` | AI 插件移动端接口 | `plugin\saiai\app\api\controller` |
| `saiuser-api` | 会员插件移动端接口 | `plugin\saiuser\app\api\controller` |
| `saipay-api` | 支付插件移动端接口 | `plugin\saipay\app\api\controller` |

后台管理接口可以加注解作为代码规范，但不会被当前 APIDOC app 扫描，除非后续显式新增后台 app 配置。

## 推荐导入

```php
use hg\apidoc\annotation as Apidoc;
```

## 常用签名

`Param` / `Query` / `Returned` 的核心命名参数：

```php
#[Apidoc\Param(
    name: 'field',
    type: 'string',
    require: true,
    default: '',
    desc: '字段说明'
)]
```

当前 vendor 构造函数也支持位置参数，项目内推荐短位置参数加命名补充：

```php
#[Apidoc\Query('source', type: 'string', require: false, default: 'mysql', desc: '数据库连接名')]
#[Apidoc\Returned('items', type: 'array', desc: '列表数据')]
```

类型常用值：

- `string`
- `int` / `integer`
- `boolean`
- `array`
- `object`
- `file`
- `datetime`

## SaiAdmin 控制器模板

```php
<?php
namespace plugin\demo\app\controller;

use hg\apidoc\annotation as Apidoc;
use plugin\saiadmin\basic\BaseController;
use plugin\saiadmin\service\Permission;
use support\Request;
use support\Response;

#[Apidoc\Group('插件接口')]
#[Apidoc\Title('示例接口')]
class DemoController extends BaseController
{
    #[Apidoc\Title('示例列表')]
    #[Apidoc\Url('/app/demo/index')]
    #[Apidoc\Method('GET')]
    #[Apidoc\Query('keyword', type: 'string', require: false, desc: '关键词')]
    #[Apidoc\Returned('list', type: 'array', desc: '列表')]
    #[Permission('示例列表', 'demo:index')]
    public function index(Request $request): Response
    {
        return $this->success([]);
    }
}
```

## unibest 联动

OpenAPI JSON 通过 B8AIadmin 桥接路由导出：

```bash
curl -s http://127.0.0.1:8787/apidoc/openapi/saiuser-api
```

unibest 侧自动接口生成应指向具体 appKey 的 OpenAPI URL，例如：

- `http://127.0.0.1:8787/apidoc/openapi/saiai-api`
- `http://127.0.0.1:8787/apidoc/openapi/saiuser-api`
- `http://127.0.0.1:8787/apidoc/openapi/saipay-api`

## 排错

- `Class "hg\apidoc\providers\WebmanService" not found`: 先确认 `server/vendor/hg/apidoc/src/providers/WebmanService.php` 存在，再运行 `composer require hg/apidoc hg/apidoc-export` 或 `composer install`。
- APIDOC 页面/导出看不到接口：确认控制器目录在 `apps[*].path` 中，方法有可解析注解，Webman 常驻进程已 reload。
- 自动 URL 不符合预期：补 `#[Apidoc\Url('/exact/path')]`，不要继续猜自动规则。
- 注解类报未导入：确认文件顶部有 `use hg\apidoc\annotation as Apidoc;`。
