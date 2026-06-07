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
| 轮播图管理 | 按语言维护首页轮播图，支持桌面图、移动图、图片 Alt、主/次按钮和排序 |
| 导航管理 | 支持头部、底部等导航位置，导航标题按语言分别维护 |
| 站点设置 | 支持站点名称、Logo、favicon、首页文案、媒体链接、联系方式和 footer 配置 |
| 联系表单 | 前台提交留言，后台查看、处理和备注 |
| 文章评论 | 前台免登录提交评论和无限级回复，后台审核、屏蔽和审计访客信息 |
| 评论屏蔽规则 | 预设并管理屏蔽词、屏蔽邮箱和临时邮箱域名 |
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
Database/migrations/20260607023154_add_b_8cms_carousel.php
```

迁移会创建并初始化以下表：

| 表名 | 用途 |
| --- | --- |
| `b8cms_language` | 语言配置 |
| `b8cms_template` | 模板配置 |
| `b8cms_content` | 文章、产品、页面内容 |
| `b8cms_carousel` | 首页多语言轮播图 |
| `b8cms_navigation` | 多语言导航 |
| `b8cms_site_setting` | 站点设置 |
| `b8cms_contact_message` | 联系表单留言 |
| `b8cms_comment` | 文章评论、回复层级和访客审计信息 |
| `b8cms_comment_filter` | 评论屏蔽词、邮箱和域名规则 |

迁移同时会预设 `zh-CN`、`en-US` 两种语言，内置 `default` 模板，写入示例文章、产品、页面、首页轮播图、导航、站点设置、评论屏蔽词、屏蔽邮箱和后台菜单权限。

## 后台管理模块

后台菜单由迁移写入，入口为“B8CMS”。当前模块如下：

| 模块 | 后台路径 | 主要能力 |
| --- | --- | --- |
| 内容管理 | `/plugin/b8cms/content` | 管理文章、产品、页面和 SEO |
| 语言管理 | `/plugin/b8cms/language` | 新增语言、启停语言、设置默认语言 |
| 模板管理 | `/plugin/b8cms/template` | 管理模板信息、启用模板 |
| 轮播图 | `/plugin/b8cms/carousel` | 维护首页多语言轮播图、图片、按钮和排序 |
| 导航管理 | `/plugin/b8cms/navigation` | 维护头部和底部导航 |
| 站点设置 | `/plugin/b8cms/setting` | 维护 Logo、媒体链接、联系方式、首页和 footer 文案 |
| 联系留言 | `/plugin/b8cms/contact` | 查看、删除、处理联系表单留言 |
| 评论管理 | `/plugin/b8cms/comment` | 查看评论、通过评论、屏蔽评论、审计访客信息 |
| 屏蔽规则 | `/plugin/b8cms/comment-filter` | 维护屏蔽词、屏蔽邮箱和临时邮箱域名 |

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
| `/app/b8cms/api/site/bootstrap` | GET | 获取站点启动数据，包括语言、模板、设置、导航、轮播图和推荐内容 |
| `/app/b8cms/api/content/list` | GET | 获取文章、产品或页面列表 |
| `/app/b8cms/api/content/detail` | GET | 获取文章、产品或页面详情 |
| `/app/b8cms/api/comment/list` | GET | 获取文章已通过评论树 |
| `/app/b8cms/api/comment/submit` | POST | 免登录提交文章评论或回复 |
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

### 评论列表参数

| 参数 | 必填 | 说明 |
| --- | --- | --- |
| `content_id` | 是 | 文章 ID |

评论列表只返回 `status = 1` 的已通过评论，并按 `parent_id/root_id/path` 组织为树形结构。屏蔽或待审核评论不会出现在前台。

### 评论提交参数

| 参数 | 必填 | 说明 |
| --- | --- | --- |
| `content_id` | 是 | 文章 ID，只允许评论已发布文章 |
| `parent_id` | 否 | 父级评论 ID，传 `0` 表示一级评论 |
| `nickname` | 是 | 昵称 |
| `email` | 是 | 邮箱，前台必填但不会公开展示 |
| `comment` | 是 | 评论内容 |
| `website` | 否 | 个人网站 |
| `browser_fingerprint` | 否 | 前台采集的浏览器指纹摘要 |
| `source_url` | 否 | 来源页面 URL |

评论不要求登录。系统会保存 `ip`、`user_agent`、`browser_fingerprint`、`source_url`、邮箱和命中规则，便于后台审计。提交时会即时匹配 `b8cms_comment_filter` 中启用的规则：

| 规则类型 | 匹配方式 | 说明 |
| --- | --- | --- |
| `word` | `contains`、`exact`、`regex` | 匹配昵称和评论正文 |
| `email` | `contains`、`exact`、`domain`、`regex` | 匹配邮箱地址，`domain` 会匹配主域名和子域名 |

命中规则的评论会保存为 `status = 3` 已屏蔽，并记录 `block_reason` 与 `matched_rule`；未命中的评论默认保存为 `status = 1` 已通过。

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
B8AIadmin 是父框架，B8CMS 前台默认挂载在 `/cms`，避免插件抢占框架根路径 `/`。挂载路径由 `server/plugin/b8cms/config/app.php` 中的 `site_path` 配置，独立站单域名部署时可改为 `/`。

| 路由 | 说明 |
| --- | --- |
| `/cms` | 默认语言首页 |
| `/cms/{lang}` | 指定语言首页 |
| `/robots.txt` | 根路径 robots，从后台站点配置动态生成并指向当前 B8CMS sitemap |
| `/cms/robots.txt` | 当前挂载路径下的 robots 调试入口 |
| `/cms/sitemap.xml` | 全站多语言 sitemap |
| `/cms/{lang}/sitemap.xml` | 指定语言 sitemap |
| `/cms/article/{slug}.html` | 默认语言文章详情 |
| `/cms/product/{slug}.html` | 默认语言产品详情 |
| `/cms/page/{slug}.html` | 默认语言页面详情 |
| `/cms/{lang}/article/{slug}.html` | 指定语言文章详情 |
| `/cms/{lang}/product/{slug}.html` | 指定语言产品详情 |
| `/cms/{lang}/page/{slug}.html` | 指定语言页面详情 |

例如英文首页：

```text
/cms/en-US
```

内容详情页的规范地址统一使用 `.html` 后缀；旧版无后缀详情地址会 301 跳转到对应 `.html` 地址，避免搜索引擎收录重复页面。

`sitemap.xml` 只输出已启用语言、已发布且未删除的首页、文章、产品和页面 URL，详情页 URL 与 canonical 保持一致，统一带 `.html` 后缀。Sitemap 会同时输出 `xhtml:link` 多语言替代链接和 `image:image` 图片条目，图片来自封面图、内容图片和 SEO 图片。

前台 canonical、sitemap、robots、Open Graph URL 会优先使用后台“站点配置”中的正式站点域名：

| 配置标识 | 说明 |
| --- | --- |
| `site_url` | 正式站点域名，留空时使用当前请求 Host |
| `force_https` | 设置为 `1` 时强制输出 HTTPS，并对 HTTP 访问做 301 |
| `canonical_host_mode` | `keep` 保持当前域名，`www` 统一 www，`non_www` 统一裸域 |

`robots.txt` 从后台“站点配置”中的 `robots` 分组动态生成：

| 配置标识 | 说明 |
| --- | --- |
| `robots_rules` | robots 基础规则，多行文本，例如 `User-agent`、`Allow`、`Disallow` |
| `robots_extra` | robots 附加规则，多行文本，用于补充 Crawl-delay、指定爬虫规则等 |

系统会自动按当前域名和 B8CMS 挂载路径追加 `Sitemap` 地址，后台规则中即使填写了旧的 `Sitemap:` 行也会被前台生成时过滤，避免域名或路径变更后出现错误 sitemap。

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
| `product-feature.html` | 产品强调页示例，可用于单个产品的专用展示 |
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

文章、产品、页面都可以在内容管理中单独选择“模板文件”。后台会从当前启用模板目录动态读取 `.html` 文件生成下拉选项，并排除 `public/*` 公共片段和 `index.html` 首页模板；保存时不需要带 `.html` 后缀，例如：

| 内容类型 | 模板文件字段 | 实际加载文件 |
| --- | --- | --- |
| 产品 | `product-feature` | `server/plugin/b8cms/app/view/default/product-feature.html` |
| 文章 | `article` | `server/plugin/b8cms/app/view/default/article.html` |
| 页面 | `page` | `server/plugin/b8cms/app/view/default/page.html` |

模板文件只允许字母、数字、下划线、短横线和目录分隔符，不能使用 `..` 或绝对路径。目标模板不存在时，系统会自动回退到内容类型默认模板：文章回退 `article`，产品回退 `product`，页面回退 `page`。

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
| `carousels` | 当前语言首页轮播图，缺省时回退默认语言 |
| `featured_articles` | 当前语言推荐文章 |
| `featured_products` | 当前语言推荐产品 |
| `pages` | 当前语言页面列表 |
| `all_products` | 页面模板可用的当前语言已发布产品列表 |
| `all_articles` | 页面模板可用的当前语言已发布文章列表 |
| `all_pages` | 页面模板可用的当前语言已发布页面列表 |
| `content` | 当前详情页内容，首页为空 |
| `seo` | 当前页面 SEO 信息 |
| `seo_links` | canonical、alternate、x-default 和首页地址 |

默认 `index.html` 会优先渲染 `carousels`。每条轮播支持 `title`、`subtitle`、`description`、`image`、`mobile_image`、`image_alt`、`button_text`、`button_url`、`secondary_button_text`、`secondary_button_url` 和 `target`，按钮链接会按当前语言统一规范成 B8CMS 路径。

默认 `article.html` 已接入评论展示与提交。模板通过 `/app/b8cms/api/comment/list` 获取评论树，通过 `/app/b8cms/api/comment/submit` 提交评论，并在提交时采集浏览器指纹摘要和来源 URL。

默认 `page.html` 会根据页面 `slug` 渲染动态内容：

| 页面 slug | 渲染内容 |
| --- | --- |
| `products` | 读取 `all_products` 展示当前语言全部已发布产品、价格、SKU 和详情链接 |
| `news` | 读取 `all_articles` 展示当前语言全部已发布文章和详情链接 |
| `about` | 读取站点配置、当前模板、社交媒体链接和已发布页面入口 |
| `contact` | 读取联系方式配置，并接入联系表单 |

SEO 的优先级为：

1. 内容自身的 `seo_title`、`seo_keywords`、`seo_description`。
2. 内容标题或摘要。
3. 站点设置中的 `seo_title`、`seo_keywords`、`seo_description`。
4. 站点名称或 `B8CMS`。

公共头部会输出多语言 SEO 链接：

| 标签 | 说明 |
| --- | --- |
| `rel="canonical"` | 当前语言页面的规范地址 |
| `rel="alternate" hreflang="语言编码"` | 同一内容在其他语言下的地址 |
| `rel="alternate" hreflang="x-default"` | 默认语言地址 |
| `meta name="robots"` | 当前页面收录策略 |
| `og:*` | Open Graph 分享信息，包含站点名、locale、文章时间和产品价格等扩展标签 |
| `twitter:*` | Twitter Card 分享信息，支持站点账号、作者、独立标题、描述和图片 |
| `application/ld+json` | 结构化数据，首页输出 `WebSite/Organization`，文章输出 `Article`，产品输出 `Product`，页面输出 `WebPage/AboutPage/ContactPage/CollectionPage` |

详情页的多语言切换会优先指向同一个 `content_type + slug` 在其他语言下的详情页。例如中文产品 `/cms/product/b8cms-growth-suite.html` 会对应英文 `/cms/en-US/product/b8cms-growth-suite.html`。如果某个语言没有相同 slug 的已发布内容，则不会输出该语言的 alternate 链接，避免搜索引擎看到不存在的语言版本。

后台内容管理会校验同语言、同内容类型下的 `slug` 不重复，并提供标题生成 slug、SEO 预览、标题与描述长度提示、批量收录策略、图片 Alt/Title/Caption、Twitter、文章作者和产品结构化字段。SEO 高级设置保存到 `extra.seo`，图片 SEO 保存到内容 `extra` 根节点，示例：

```json
{
  "image_alt": "B8CMS Starter 独立站套件界面",
  "image_title": "B8CMS Starter 产品图",
  "image_caption": "用于 B8CMS Starter 产品详情和图片 sitemap 的说明。",
  "seo": {
    "robots": "index,follow",
    "canonical_url": "",
    "og_title": "B8CMS Starter",
    "og_description": "适合独立站起步的多语言 CMS 产品套件。",
    "og_image": "/upload/b8cms/starter-og.png",
    "twitter_title": "B8CMS Starter",
    "twitter_description": "多语言独立站起步套件。",
    "twitter_image": "/upload/b8cms/starter-twitter.png",
    "twitter_creator": "@openb8",
    "twitter_card": "summary_large_image",
    "author_name": "B8 Team",
    "schema_type": "",
    "product_brand": "B8CMS",
    "product_manufacturer": "OpenB8",
    "product_mpn": "B8CMS-STARTER",
    "product_gtin": "",
    "schema_enabled": true
  }
}
```

`canonical_url` 留空时自动使用当前规范地址；`schema_enabled` 关闭后不会输出 JSON-LD。产品页会把产品扩展参数转换成 `additionalProperty`，并把价格、库存、SKU、品牌、制造商、MPN/GTIN 写入结构化数据。

## 多语言内容规则

内容、导航和本地化站点设置都通过语言编码隔离。

| 数据 | 语言字段 | 说明 |
| --- | --- | --- |
| 内容 | `lang_code` | 同一个 `slug` 可以在不同语言下各建一条 |
| 导航 | `lang_code` | 每种语言单独维护导航标题和链接 |
| 轮播图 | `lang_code` | 每种语言单独维护首页轮播文案、图片和按钮 |
| 站点设置 | `lang_code` | 空字符串表示全局设置，具体语言会覆盖全局设置 |

语言切换时，如果传入的语言不存在或已停用，系统会回退到默认语言。默认语言由 `b8cms_language.is_default = 1` 决定。

首页和详情页都使用路径形式切换语言，默认语言不带语言前缀，非默认语言带 `/{lang}` 前缀，例如 `/cms/en-US`、`/cms/en-US/page/contact.html`。

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

产品扩展参数保存到 `extra`，后台内容管理会读取 `extra.product_params_schema` 动态渲染表单，并把填写值保存到 `extra.product_params`：

```json
{
  "product_params_schema": [
    {
      "key": "model",
      "label": "产品型号",
      "type": "text",
      "placeholder": "B8CMS-PRO"
    },
    {
      "key": "min_order",
      "label": "起订数量",
      "type": "number",
      "unit": "套",
      "min": 1,
      "precision": 0,
      "default": 1
    },
    {
      "key": "deployment",
      "label": "部署方式",
      "type": "select",
      "options": [
        { "label": "SaaS 托管", "value": "SaaS 托管" },
        { "label": "私有化部署", "value": "私有化部署" }
      ]
    },
    {
      "key": "seo_ready",
      "label": "支持 SEO",
      "type": "switch",
      "default": true
    }
  ],
  "product_params": {
    "model": "B8CMS-PRO",
    "min_order": 1,
    "deployment": "SaaS 托管",
    "seo_ready": true
  }
}
```

动态表单字段类型支持 `text`、`textarea`、`number`、`select`、`switch`。产品详情模板会把 `extra.product_params_schema` 和 `extra.product_params` 合并为 `content.product_params` 并展示。

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
php webman route:list | rg 'b8cms|/cms|\.html|/article|/product|/page'
php webman b8:migrate:status
php webman b8:migrate --dry-run
php -l ../Database/migrations/20260607023154_add_b_8cms_carousel.php
```

管理端变更后：

```bash
cd saiadmin-artd
pnpm build
```

前台运行时验证：

```bash
curl 'http://127.0.0.1:8787/app/b8cms/api/site/bootstrap?lang=zh-CN'
curl -I 'http://127.0.0.1:8787/'
curl 'http://127.0.0.1:8787/cms'
curl 'http://127.0.0.1:8787/cms/product/b8cms-starter.html'
curl -I 'http://127.0.0.1:8787/cms/product/b8cms-starter'
curl 'http://127.0.0.1:8787/cms/sitemap.xml'
curl 'http://127.0.0.1:8787/robots.txt'
```
