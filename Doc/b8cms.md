# B8CMS 独立站插件说明

本文档说明 `b8cms` 插件的功能边界、数据库迁移、后台管理、公开接口、ThinkTemplate 模板开发和多语言 SEO 规则。插件源码位于 `server/plugin/b8cms`，后台页面位于 `saiadmin-artd/src/views/plugin/b8cms`。

## 功能范围

`b8cms` 是框架内置的独立站 CMS 插件，当前提供以下能力：

| 能力 | 说明 |
| --- | --- |
| 多语言管理 | 维护语言编码、语言名称、区域标识、默认语言和启停状态 |
| 模板管理 | 维护模板标识、模板说明、预览图、模板参数，并支持设置当前启用模板 |
| 内容管理 | 统一管理文章、产品和页面，按 `content_type` 区分 |
| 产品管理 | 产品使用内容表保存，额外支持价格、币种、库存、SKU、相册和扩展参数 |
| SEO 设置 | 每条文章、产品、页面都可配置 SEO 标题、关键词和描述 |
| 导航管理 | 支持头部、底部等导航位置，导航标题按语言分别维护 |
| 站点设置 | 支持站点名称、Logo、favicon、首页文案、媒体链接、联系方式和 footer 配置 |
| 联系表单 | 前台提交留言，后台查看、处理和备注 |
| ThinkTemplate 视图 | 前台页面使用 `topthink/think-template` 渲染，并按当前启用模板加载视图 |

## 安装和迁移

ThinkTemplate 依赖已经写入 `server/composer.json`：

```bash
cd server
composer install
```

数据库结构和预设数据由 Phinx 迁移维护：

```bash
cd server
php webman b8:migrate:status
php webman b8:migrate --dry-run
php webman b8:migrate
```

迁移文件：

```text
Database/migrations/20260606151515_add_b_8cms_plugin.php
```

迁移会创建并初始化以下表：

| 表名 | 用途 |
| --- | --- |
| `b8cms_language` | 语言配置 |
| `b8cms_template` | 模板配置 |
| `b8cms_content` | 文章、产品、页面内容 |
| `b8cms_navigation` | 多语言导航 |
| `b8cms_site_setting` | 站点设置 |
| `b8cms_contact_message` | 联系表单留言 |

迁移同时会预设 `zh-CN`、`en-US` 两种语言，内置 `default` 模板，写入示例文章、产品、页面、导航、站点设置和后台菜单权限。

## 后台管理模块

后台菜单由迁移写入，入口为“B8CMS”。当前模块如下：

| 模块 | 后台路径 | 主要能力 |
| --- | --- | --- |
| 内容管理 | `/plugin/b8cms/content` | 管理文章、产品、页面和 SEO |
| 语言管理 | `/plugin/b8cms/language` | 新增语言、启停语言、设置默认语言 |
| 模板管理 | `/plugin/b8cms/template` | 管理模板信息、启用模板 |
| 导航管理 | `/plugin/b8cms/navigation` | 维护头部和底部导航 |
| 站点设置 | `/plugin/b8cms/setting` | 维护 Logo、媒体链接、联系方式、首页和 footer 文案 |
| 联系留言 | `/plugin/b8cms/contact` | 查看、删除、处理联系表单留言 |

后台控制器位于：

```text
server/plugin/b8cms/app/admin/controller
```

后台 Logic、Model 和 Validate 分别位于：

```text
server/plugin/b8cms/app/admin/logic
server/plugin/b8cms/app/model
server/plugin/b8cms/app/validate
```

## 公开接口

公开接口位于 `plugin\b8cms\app\api\controller\SiteController`，统一使用 `ok()` / `fail()` 返回 `{code,message,data}` 格式。

| 接口 | 方法 | 说明 |
| --- | --- | --- |
| `/app/b8cms/api/site/bootstrap` | GET | 获取站点启动数据，包括语言、模板、设置、导航和推荐内容 |
| `/app/b8cms/api/content/list` | GET | 获取文章、产品或页面列表 |
| `/app/b8cms/api/content/detail` | GET | 获取文章、产品或页面详情 |
| `/app/b8cms/api/contact/submit` | POST | 提交联系表单 |

### 内容列表参数

| 参数 | 必填 | 说明 |
| --- | --- | --- |
| `type` | 是 | 内容类型：`article`、`product`、`page` |
| `lang` | 否 | 语言编码，例如 `zh-CN`、`en-US` |
| `category` | 否 | 分类筛选 |
| `keyword` | 否 | 标题或摘要关键词 |
| `page` | 否 | 页码 |
| `limit` | 否 | 每页数量，最大 50 |

### 内容详情参数

| 参数 | 必填 | 说明 |
| --- | --- | --- |
| `type` | 是 | 内容类型：`article`、`product`、`page` |
| `slug` | 是 | 访问别名 |
| `lang` | 否 | 语言编码 |

### 联系表单参数

| 参数 | 必填 | 说明 |
| --- | --- | --- |
| `name` | 是 | 姓名 |
| `email` | 是 | 邮箱 |
| `message` | 是 | 留言内容 |
| `phone` | 否 | 电话 |
| `company` | 否 | 公司 |
| `subject` | 否 | 主题 |
| `source` | 否 | 来源，默认 `site` |
| `lang_code` | 否 | 语言编码 |

## 前台页面路由

前台页面由 `plugin\b8cms\app\api\controller\SiteViewController` 渲染。

| 路由 | 说明 |
| --- | --- |
| `/` | 首页 |
| `/article/{slug}` | 默认语言文章详情 |
| `/product/{slug}` | 默认语言产品详情 |
| `/page/{slug}` | 默认语言页面详情 |
| `/{lang}/article/{slug}` | 指定语言文章详情 |
| `/{lang}/product/{slug}` | 指定语言产品详情 |
| `/{lang}/page/{slug}` | 指定语言页面详情 |

首页也支持通过查询参数切换语言：

```text
/?lang=en-US
```

## ThinkTemplate 模板开发

模板配置位于：

```text
server/plugin/b8cms/config/view.php
```

默认模板目录：

```text
server/plugin/b8cms/app/view/default
```

默认模板文件：

| 文件 | 说明 |
| --- | --- |
| `public/header.html` | 公共头部，包含 SEO、Logo、头部导航和语言切换 |
| `public/footer.html` | 公共底部，包含 footer 文案、底部导航、联系方式和媒体链接 |
| `index.html` | 首页 |
| `article.html` | 文章详情 |
| `product.html` | 产品详情 |
| `page.html` | 普通页面 |

新增模板时，推荐按以下结构创建：

```text
server/plugin/b8cms/app/view/{template_key}/public/header.html
server/plugin/b8cms/app/view/{template_key}/public/footer.html
server/plugin/b8cms/app/view/{template_key}/index.html
server/plugin/b8cms/app/view/{template_key}/article.html
server/plugin/b8cms/app/view/{template_key}/product.html
server/plugin/b8cms/app/view/{template_key}/page.html
```

后台“模板管理”中的 `template_key` 必须与视图目录名一致。启用模板后，前台会按如下规则渲染：

```php
think_view($templateKey . '/' . $template, $context, '', 'b8cms')
```

例如启用模板 `default`，产品详情会加载：

```text
server/plugin/b8cms/app/view/default/product.html
```

## 模板可用变量

`SiteService::pageContext()` 会向模板注入以下变量：

| 变量 | 说明 |
| --- | --- |
| `lang` | 当前语言 |
| `languages` | 已启用语言列表 |
| `template` | 当前启用模板 |
| `settings` | 当前语言合并后的站点设置 |
| `header_nav` | 当前语言头部导航 |
| `footer_nav` | 当前语言底部导航 |
| `featured_articles` | 当前语言推荐文章 |
| `featured_products` | 当前语言推荐产品 |
| `pages` | 当前语言页面列表 |
| `content` | 当前详情页内容，首页为空 |
| `seo` | 当前页面 SEO 信息 |

SEO 的优先级为：

1. 内容自身的 `seo_title`、`seo_keywords`、`seo_description`。
2. 内容标题或摘要。
3. 站点设置中的 `seo_title`、`seo_keywords`、`seo_description`。
4. 站点名称或 `B8CMS`。

## 多语言内容规则

内容、导航和本地化站点设置都通过语言编码隔离。

| 数据 | 语言字段 | 说明 |
| --- | --- | --- |
| 内容 | `lang_code` | 同一个 `slug` 可以在不同语言下各建一条 |
| 导航 | `lang_code` | 每种语言单独维护导航标题和链接 |
| 站点设置 | `lang_code` | 空字符串表示全局设置，具体语言会覆盖全局设置 |

语言切换时，如果传入的语言不存在或已停用，系统会回退到默认语言。默认语言由 `b8cms_language.is_default = 1` 决定。

## 产品字段

产品属于 `b8cms_content.content_type = product`，额外关注以下字段：

| 字段 | 说明 |
| --- | --- |
| `price` | 产品价格 |
| `currency` | 币种，例如 `CNY`、`USD` |
| `stock` | 库存 |
| `sku` | 产品 SKU |
| `images` | 产品相册，JSON 数组 |
| `extra` | 产品扩展参数，JSON 对象 |
| `is_featured` | 是否推荐到首页 |

## 维护注意事项

1. 修改 PHP、路由、插件配置或 Composer 依赖后，需要 reload 或 restart Webman；Composer 新依赖需要 restart。
2. 新增模板目录后，需要在后台模板管理添加对应 `template_key`，并设置启用。
3. 新增公开接口时，应同步补充 APIDOC 注解。
4. 数据库结构变更只新增 Phinx 迁移，不新增独立 SQL patch。
5. 预设菜单、权限和初始化数据应保持幂等，回滚时避免误删用户后续新增的数据。

## 常用验证命令

```bash
cd server
find plugin/b8cms -type f -name '*.php' -print | xargs -n1 php -l
php -l ../Database/migrations/20260606151515_add_b_8cms_plugin.php
php webman route:list | rg 'b8cms|/article|/product|/page'
php webman b8:migrate:status
php webman b8:migrate --dry-run
```

管理端变更后：

```bash
cd saiadmin-artd
pnpm build
```

前台运行时验证：

```bash
curl 'http://127.0.0.1:8787/app/b8cms/api/site/bootstrap?lang=zh-CN'
curl 'http://127.0.0.1:8787/'
curl 'http://127.0.0.1:8787/product/b8cms-starter'
```
