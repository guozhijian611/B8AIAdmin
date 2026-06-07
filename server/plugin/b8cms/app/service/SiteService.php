<?php

namespace plugin\b8cms\app\service;

use support\Request;
use support\think\Db;

class SiteService
{
    public function bootstrap(?string $lang = null): array
    {
        $lang = $this->normalizeLang($lang);
        $template = $this->activeTemplate();
        $languages = $this->languages();
        $defaultLang = $this->defaultLang($languages);

        return [
            'lang' => $lang,
            'languages' => $languages,
            'template' => $template,
            'settings' => $this->settings($lang),
            'links' => $this->siteLinks($lang, $defaultLang),
            'header_nav' => $this->navigations($lang, 'header', $defaultLang),
            'footer_nav' => $this->navigations($lang, 'footer', $defaultLang),
            'carousels' => $this->carousels($lang, 'home', $defaultLang),
            'featured_articles' => $this->contents('article', $lang, 6, true, $defaultLang),
            'featured_products' => $this->contents('product', $lang, 6, true, $defaultLang),
            'pages' => $this->contents('page', $lang, 20, false, $defaultLang),
        ];
    }

    public function contentList(string $type, ?string $lang = null, array $filters = []): array
    {
        $lang = $this->normalizeLang($lang);
        $limit = max(1, min((int) ($filters['limit'] ?? 10), 50));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $query = Db::table('b8cms_content')
            ->where('content_type', $type)
            ->where('lang_code', $lang)
            ->where('status', 1)
            ->whereNull('delete_time');

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }
        if (!empty($filters['keyword'])) {
            $keyword = '%' . $filters['keyword'] . '%';
            $query->where(function ($query) use ($keyword) {
                $query->where('title', 'like', $keyword)->whereOr('summary', 'like', $keyword);
            });
        }

        return $query->field($this->publicContentFields())
            ->order('sort', 'asc')
            ->order('published_at', 'desc')
            ->paginate($limit, false, ['page' => $page])
            ->toArray();
    }

    public function contentDetail(string $type, string $slug, ?string $lang = null): ?array
    {
        $lang = $this->normalizeLang($lang);
        $content = Db::table('b8cms_content')
            ->where('content_type', $type)
            ->where('slug', $slug)
            ->where('lang_code', $lang)
            ->where('status', 1)
            ->whereNull('delete_time')
            ->find();

        if (!$content) {
            return null;
        }

        $content['images'] = json_decode((string) ($content['images'] ?? '[]'), true) ?: [];
        $content['extra'] = json_decode((string) ($content['extra'] ?? '{}'), true) ?: [];
        $content['product_params'] = $this->productParams($content['extra']);
        $content['image_alt'] = $this->firstFilled($content['extra']['image_alt'] ?? null, $content['title'] ?? null);
        $content['image_title'] = $this->firstFilled($content['extra']['image_title'] ?? null, $content['image_alt'] ?? null);
        $content['image_caption'] = $this->firstFilled($content['extra']['image_caption'] ?? null, $content['summary'] ?? null);
        return $content;
    }

    public function pageContext(?string $lang = null, ?array $content = null, ?Request $request = null, ?string $type = null): array
    {
        $context = $this->bootstrap($lang);
        $context['content'] = $content;
        $context['seo_links'] = $this->seoLinks($context['languages'], $context['lang'], $content, $request, $type, $context['settings']);
        $context['seo'] = $this->seo($context['settings'], $content, $context['seo_links'], $request, $type);
        $defaultLang = $this->defaultLang($context['languages']);
        $context['all_products'] = $type === 'page' ? $this->contents('product', $context['lang'], 50, false, $defaultLang) : [];
        $context['all_articles'] = $type === 'page' ? $this->contents('article', $context['lang'], 50, false, $defaultLang) : [];
        $context['all_pages'] = $type === 'page' ? $this->contents('page', $context['lang'], 50, false, $defaultLang) : [];
        return $context;
    }

    public function canonicalPath(?string $lang = null, ?array $content = null, ?string $type = null): string
    {
        $languages = $this->languages();
        $defaultLang = $this->defaultLang($languages);
        $lang = $this->normalizeLang($lang);

        if ($content) {
            return $this->contentPath(
                $type ?: (string) ($content['content_type'] ?? ''),
                (string) ($content['slug'] ?? ''),
                (string) ($content['lang_code'] ?? $lang),
                $defaultLang
            );
        }

        return $this->homePath($lang, $defaultLang);
    }

    public function sitemapXml(Request $request, ?string $lang = null): string
    {
        $items = $this->sitemapItems($request, $lang);
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\" xmlns:xhtml=\"http://www.w3.org/1999/xhtml\" xmlns:image=\"http://www.google.com/schemas/sitemap-image/1.1\">\n";

        foreach ($items as $item) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>' . $this->xmlEscape($item['loc']) . "</loc>\n";
            foreach ($item['alternates'] ?? [] as $alternate) {
                $xml .= '    <xhtml:link rel="alternate" hreflang="' . $this->xmlEscape((string) ($alternate['hreflang'] ?? '')) . '" href="' . $this->xmlEscape((string) ($alternate['url'] ?? '')) . "\" />\n";
            }
            if (!empty($item['x_default'])) {
                $xml .= '    <xhtml:link rel="alternate" hreflang="x-default" href="' . $this->xmlEscape((string) $item['x_default']) . "\" />\n";
            }
            if ($item['lastmod'] !== '') {
                $xml .= '    <lastmod>' . $this->xmlEscape($item['lastmod']) . "</lastmod>\n";
            }
            foreach ($item['images'] ?? [] as $image) {
                $xml .= "    <image:image>\n";
                $xml .= '      <image:loc>' . $this->xmlEscape((string) ($image['loc'] ?? '')) . "</image:loc>\n";
                if (!empty($image['title'])) {
                    $xml .= '      <image:title>' . $this->xmlEscape((string) $image['title']) . "</image:title>\n";
                }
                if (!empty($image['caption'])) {
                    $xml .= '      <image:caption>' . $this->xmlEscape((string) $image['caption']) . "</image:caption>\n";
                }
                $xml .= "    </image:image>\n";
            }
            $xml .= "  </url>\n";
        }

        return $xml . "</urlset>\n";
    }

    public function robotsTxt(Request $request): string
    {
        $settings = $this->settings($this->defaultLang($this->languages()));
        $sitemapUrl = $this->absoluteUrl($this->baseUrl($request, $settings), $this->withSiteBase('/sitemap.xml'));
        $lines = $this->robotsSettingLines($settings['robots_rules'] ?? '', $this->defaultRobotsRules());
        $lines = array_merge($lines, $this->robotsSettingLines($settings['robots_extra'] ?? ''));
        $lines = array_values(array_filter($lines, fn (string $line) => !preg_match('/^Sitemap\s*:/i', $line)));
        $lines[] = 'Sitemap: ' . $sitemapUrl;

        return implode("\n", $lines) . "\n";
    }

    public function submitContact(Request $request): int
    {
        $data = $request->post();
        $lang = $this->normalizeLang($data['lang_code'] ?? $request->input('lang', null));
        return Db::table('b8cms_contact_message')->insertGetId([
            'lang_code' => $lang,
            'name' => trim((string) ($data['name'] ?? '')),
            'email' => trim((string) ($data['email'] ?? '')),
            'phone' => trim((string) ($data['phone'] ?? '')),
            'company' => trim((string) ($data['company'] ?? '')),
            'subject' => trim((string) ($data['subject'] ?? '')),
            'message' => trim((string) ($data['message'] ?? '')),
            'source' => trim((string) ($data['source'] ?? 'site')),
            'status' => 1,
            'ip' => $request->getRealIp(),
            'user_agent' => substr((string) $request->header('user-agent', ''), 0, 500),
            'created_by' => null,
            'updated_by' => null,
            'create_time' => date('Y-m-d H:i:s'),
            'update_time' => date('Y-m-d H:i:s'),
            'delete_time' => null,
        ]);
    }

    public function commentList(int $contentId): array
    {
        $rows = Db::table('b8cms_comment')
            ->where('content_id', $contentId)
            ->where('status', 1)
            ->whereNull('delete_time')
            ->field('id,content_id,parent_id,root_id,level,path,nickname,website,comment,create_time')
            ->order('root_id', 'asc')
            ->order('path', 'asc')
            ->select()
            ->toArray();

        return $this->buildCommentTree($rows);
    }

    public function submitComment(Request $request): array
    {
        $data = $request->post();
        $contentId = (int) ($data['content_id'] ?? 0);
        $parentId = (int) ($data['parent_id'] ?? 0);
        $nickname = trim((string) ($data['nickname'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $comment = trim((string) ($data['comment'] ?? ''));

        if ($contentId <= 0) {
            throw new \InvalidArgumentException('文章ID必须填写');
        }
        if ($nickname === '' || $email === '' || $comment === '') {
            throw new \InvalidArgumentException('昵称、邮箱和评论内容必须填写');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('邮箱格式不正确');
        }

        $content = Db::table('b8cms_content')
            ->where('id', $contentId)
            ->where('content_type', 'article')
            ->where('status', 1)
            ->whereNull('delete_time')
            ->find();
        if (!$content) {
            throw new \InvalidArgumentException('文章不存在或未发布');
        }

        $parent = null;
        if ($parentId > 0) {
            $parent = Db::table('b8cms_comment')
                ->where('id', $parentId)
                ->where('content_id', $contentId)
                ->where('status', 1)
                ->whereNull('delete_time')
                ->find();
            if (!$parent) {
                throw new \InvalidArgumentException('回复的评论不存在或不可回复');
            }
        }

        $matched = $this->matchedCommentFilter($nickname, $email, $comment);
        $status = $matched ? 3 : 1;
        $now = date('Y-m-d H:i:s');
        $level = $parent ? ((int) $parent['level'] + 1) : 1;
        $rootId = $parent ? (int) $parent['root_id'] : 0;
        $parentPath = $parent ? (string) $parent['path'] : '';

        $id = Db::transaction(function () use ($contentId, $parentId, $rootId, $level, $content, $nickname, $email, $data, $comment, $status, $matched, $request, $now, $parentPath) {
            $id = Db::table('b8cms_comment')->insertGetId([
                'content_id' => $contentId,
                'parent_id' => $parentId,
                'root_id' => $rootId,
                'level' => $level,
                'path' => '',
                'content_type' => (string) ($content['content_type'] ?? 'article'),
                'content_slug' => (string) ($content['slug'] ?? ''),
                'content_title' => (string) ($content['title'] ?? ''),
                'lang_code' => (string) ($content['lang_code'] ?? ''),
                'nickname' => $this->limitText($nickname, 80),
                'email' => $this->limitText($email, 160),
                'website' => $this->limitText(trim((string) ($data['website'] ?? '')), 255),
                'comment' => $comment,
                'status' => $status,
                'block_reason' => $matched['reason'] ?? '',
                'matched_rule' => $matched['rule'] ?? '',
                'ip' => substr((string) $request->getRealIp(), 0, 45),
                'user_agent' => substr((string) $request->header('user-agent', ''), 0, 500),
                'browser_fingerprint' => substr(trim((string) ($data['browser_fingerprint'] ?? '')), 0, 255),
                'source_url' => substr(trim((string) ($data['source_url'] ?? '')), 0, 500),
                'reviewed_by' => null,
                'reviewed_at' => null,
                'remark' => '',
                'created_by' => null,
                'updated_by' => null,
                'create_time' => $now,
                'update_time' => $now,
                'delete_time' => null,
            ]);

            $nextRootId = $rootId > 0 ? $rootId : $id;
            $path = trim($parentPath . '/' . $this->commentPathSegment((int) $id), '/');
            Db::table('b8cms_comment')
                ->where('id', $id)
                ->update([
                    'root_id' => $nextRootId,
                    'path' => $path,
                    'update_time' => $now,
                ]);

            return $id;
        });

        return [
            'id' => $id,
            'status' => $status,
            'is_blocked' => $status === 3,
        ];
    }

    public function normalizeLang(?string $lang): string
    {
        $lang = trim((string) $lang);
        if ($lang !== '') {
            $exists = Db::table('b8cms_language')
                ->where('code', $lang)
                ->where('status', 1)
                ->whereNull('delete_time')
                ->value('code');
            if ($exists) {
                return $lang;
            }
        }

        $default = Db::table('b8cms_language')
            ->where('is_default', 1)
            ->where('status', 1)
            ->whereNull('delete_time')
            ->value('code');

        return $default ?: 'zh-CN';
    }

    private function languages(): array
    {
        return Db::table('b8cms_language')
            ->where('status', 1)
            ->whereNull('delete_time')
            ->field('code,name,native_name,locale,is_default')
            ->order('sort', 'asc')
            ->select()
            ->toArray();
    }

    private function activeTemplate(): array
    {
        $template = Db::table('b8cms_template')
            ->where('is_active', 1)
            ->where('status', 1)
            ->whereNull('delete_time')
            ->order('sort', 'asc')
            ->find();

        if (!$template) {
            return [
                'template_key' => 'default',
                'name' => '默认模板',
                'options' => [],
            ];
        }

        $template['options'] = json_decode((string) ($template['options'] ?? '{}'), true) ?: [];
        return $template;
    }

    private function settings(string $lang): array
    {
        $rows = Db::table('b8cms_site_setting')
            ->where('status', 1)
            ->whereNull('delete_time')
            ->where(function ($query) use ($lang) {
                $query->where('lang_code', '')->whereOr('lang_code', $lang);
            })
            ->order('lang_code', 'asc')
            ->order('sort', 'asc')
            ->select()
            ->toArray();

        $settings = [];
        foreach ($rows as $row) {
            $value = json_decode((string) $row['value'], true);
            $settings[$row['setting_key']] = json_last_error() === JSON_ERROR_NONE ? $value : $row['value'];
        }

        return $settings;
    }

    private function navigations(string $lang, string $position, string $defaultLang): array
    {
        $rows = Db::table('b8cms_navigation')
            ->where('lang_code', $lang)
            ->where('position', $position)
            ->where('status', 1)
            ->whereNull('delete_time')
            ->field('id,parent_id,title,url,target,content_type,content_id,sort')
            ->order('sort', 'asc')
            ->select()
            ->toArray();

        foreach ($rows as &$row) {
            $row['url'] = $this->normalizeSiteUrl((string) $row['url'], $lang, $defaultLang);
        }
        unset($row);

        return $rows;
    }

    private function carousels(string $lang, string $position, string $defaultLang): array
    {
        $rows = Db::table('b8cms_carousel')
            ->where('lang_code', $lang)
            ->where('position', $position)
            ->where('status', 1)
            ->whereNull('delete_time')
            ->field('id,lang_code,position,title,subtitle,description,image,mobile_image,image_alt,button_text,button_url,secondary_button_text,secondary_button_url,target,sort')
            ->order('sort', 'asc')
            ->select()
            ->toArray();

        if ($rows === [] && $lang !== $defaultLang) {
            $rows = Db::table('b8cms_carousel')
                ->where('lang_code', $defaultLang)
                ->where('position', $position)
                ->where('status', 1)
                ->whereNull('delete_time')
                ->field('id,lang_code,position,title,subtitle,description,image,mobile_image,image_alt,button_text,button_url,secondary_button_text,secondary_button_url,target,sort')
                ->order('sort', 'asc')
                ->select()
                ->toArray();
        }

        foreach ($rows as &$row) {
            $row['button_url'] = $this->normalizeSiteUrl((string) $row['button_url'], $lang, $defaultLang);
            $row['secondary_button_url'] = $this->normalizeSiteUrl((string) $row['secondary_button_url'], $lang, $defaultLang);
            $row['image_alt'] = $this->firstFilled($row['image_alt'] ?? null, $row['title'] ?? null);
        }
        unset($row);

        return $rows;
    }

    private function contents(string $type, string $lang, int $limit, bool $featured = false, ?string $defaultLang = null): array
    {
        $query = Db::table('b8cms_content')
            ->where('content_type', $type)
            ->where('lang_code', $lang)
            ->where('status', 1)
            ->whereNull('delete_time');

        if ($featured) {
            $query->where('is_featured', 1);
        }

        $rows = $query->field($this->publicContentFields())
            ->order('sort', 'asc')
            ->order('published_at', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();

        $defaultLang = $defaultLang ?: $this->defaultLang($this->languages());
        foreach ($rows as &$row) {
            $row['url'] = $this->contentPath((string) $row['content_type'], (string) $row['slug'], $lang, $defaultLang);
        }
        unset($row);

        return $rows;
    }

    private function seo(array $settings, ?array $content, array $seoLinks, ?Request $request, ?string $type): array
    {
        $baseUrl = $this->baseUrl($request, $settings);
        $siteName = $this->firstFilled($settings['og_site_name'] ?? null, $settings['site_name'] ?? null, 'B8CMS');
        $extraSeo = $this->contentSeoExtra($content);
        $title = $this->firstFilled($content['seo_title'] ?? null, $content['title'] ?? null, $settings['seo_title'] ?? null, $settings['site_name'] ?? null, 'B8CMS');
        $description = $this->firstFilled($content['seo_description'] ?? null, $content['summary'] ?? null, $settings['seo_description'] ?? null);
        $image = $this->absoluteAssetUrl($baseUrl, $this->firstFilled($extraSeo['og_image'] ?? null, $content['cover_image'] ?? null, $settings['og_image'] ?? null, $settings['logo'] ?? null));
        $ogTitle = $this->firstFilled($extraSeo['og_title'] ?? null, $title);
        $ogDescription = $this->firstFilled($extraSeo['og_description'] ?? null, $description);
        $twitterTitle = $this->firstFilled($extraSeo['twitter_title'] ?? null, $ogTitle);
        $twitterDescription = $this->firstFilled($extraSeo['twitter_description'] ?? null, $ogDescription);
        $twitterImage = $this->absoluteAssetUrl($baseUrl, $this->firstFilled($extraSeo['twitter_image'] ?? null, $image));
        $twitterCard = $this->firstFilled($extraSeo['twitter_card'] ?? null, $image === '' ? 'summary' : 'summary_large_image');
        $schemaEnabled = ($extraSeo['schema_enabled'] ?? true) !== false;
        $publishedAt = $this->dateIso($content['published_at'] ?? null);
        $modifiedAt = $this->dateIso($content['update_time'] ?? $content['published_at'] ?? null);

        return [
            'title' => $title,
            'keywords' => $this->firstFilled($content['seo_keywords'] ?? null, $settings['seo_keywords'] ?? null),
            'description' => $description,
            'robots' => $this->firstFilled($extraSeo['robots'] ?? null, $settings['seo_robots'] ?? null, 'index,follow'),
            'site_name' => $siteName,
            'theme_color' => $this->firstFilled($settings['theme_color'] ?? null),
            'og_title' => $ogTitle,
            'og_description' => $ogDescription,
            'og_image' => $image,
            'og_url' => (string) ($seoLinks['canonical'] ?? ''),
            'og_type' => $type === 'article' ? 'article' : ($type === 'product' ? 'product' : 'website'),
            'og_locale' => (string) ($seoLinks['locale'] ?? ''),
            'og_locale_alternates' => $seoLinks['locale_alternates'] ?? [],
            'twitter_card' => $twitterCard,
            'twitter_title' => $twitterTitle,
            'twitter_description' => $twitterDescription,
            'twitter_image' => $twitterImage,
            'twitter_site' => $this->firstFilled($settings['twitter_site'] ?? null),
            'twitter_creator' => $this->firstFilled($extraSeo['twitter_creator'] ?? null, $settings['twitter_creator'] ?? null),
            'article_published_time' => $type === 'article' ? $publishedAt : '',
            'article_modified_time' => $type === 'article' ? $modifiedAt : '',
            'article_author' => $type === 'article' ? $this->firstFilled($extraSeo['author_name'] ?? null, $siteName) : '',
            'product_price_amount' => $type === 'product' && (float) ($content['price'] ?? 0) > 0 ? (string) (float) ($content['price'] ?? 0) : '',
            'product_price_currency' => $type === 'product' ? (string) ($content['currency'] ?? '') : '',
            'product_availability' => $type === 'product' ? (((int) ($content['stock'] ?? 0) > 0) ? 'in stock' : 'out of stock') : '',
            'json_ld' => $schemaEnabled ? $this->structuredDataJson($settings, $content, $seoLinks, $type, $image) : '',
        ];
    }

    private function seoLinks(array $languages, string $currentLang, ?array $content, ?Request $request, ?string $type, array $settings = []): array
    {
        $baseUrl = $this->baseUrl($request, $settings);
        $defaultLang = $this->defaultLang($languages);
        $alternates = [];

        foreach ($languages as $language) {
            $lang = (string) ($language['code'] ?? '');
            if ($lang === '') {
                continue;
            }

            $path = $content ? $this->contentPath($type ?: (string) ($content['content_type'] ?? ''), (string) ($content['slug'] ?? ''), $lang, $defaultLang) : $this->homePath($lang, $defaultLang);
            if ($content) {
                $exists = Db::table('b8cms_content')
                    ->where('content_type', $type ?: (string) ($content['content_type'] ?? ''))
                    ->where('slug', $content['slug'])
                    ->where('lang_code', $lang)
                    ->where('status', 1)
                    ->whereNull('delete_time')
                    ->value('id');
                if (!$exists) {
                    continue;
                }
            }

            $alternates[] = [
                'lang' => $lang,
                'hreflang' => str_replace('_', '-', $lang),
                'native_name' => $language['native_name'] ?? $lang,
                'url' => $this->absoluteUrl($baseUrl, $path),
                'locale' => $this->localeForLanguage($language),
                'is_current' => $lang === $currentLang,
            ];
        }

        $canonicalPath = $content
            ? $this->contentPath($type ?: (string) ($content['content_type'] ?? ''), (string) ($content['slug'] ?? ''), $currentLang, $defaultLang)
            : $this->homePath($currentLang, $defaultLang);

        $xDefault = null;
        foreach ($alternates as $alternate) {
            if ($alternate['lang'] === $defaultLang) {
                $xDefault = $alternate['url'];
                break;
            }
        }

        $seoExtra = $this->contentSeoExtra($content);
        $canonical = $this->absoluteUrl($baseUrl, $canonicalPath);
        if (!empty($seoExtra['canonical_url'])) {
            $canonical = $this->absoluteUrl($baseUrl, (string) $seoExtra['canonical_url']);
        }

        return [
            'canonical' => $canonical,
            'x_default' => $xDefault,
            'home_url' => $this->absoluteUrl($baseUrl, $this->homePath($currentLang, $defaultLang)),
            'alternates' => $alternates,
            'locale' => $this->localeForLang($languages, $currentLang),
            'locale_alternates' => array_values(array_filter(array_map(
                fn (array $alternate) => $alternate['lang'] === $currentLang ? null : (string) ($alternate['locale'] ?? ''),
                $alternates
            ))),
        ];
    }

    private function contentPath(string $type, string $slug, string $lang, string $defaultLang): string
    {
        $type = in_array($type, ['article', 'product', 'page'], true) ? $type : 'page';
        return $this->withSiteBase($lang === $defaultLang ? "/{$type}/{$slug}.html" : "/{$lang}/{$type}/{$slug}.html");
    }

    private function homePath(string $lang, string $defaultLang): string
    {
        return $this->withSiteBase($lang === $defaultLang ? '/' : '/' . rawurlencode($lang));
    }

    private function siteLinks(string $lang, string $defaultLang): array
    {
        return [
            'home' => $this->homePath($lang, $defaultLang),
            'products' => $this->contentPath('page', 'products', $lang, $defaultLang),
            'news' => $this->contentPath('page', 'news', $lang, $defaultLang),
            'about' => $this->contentPath('page', 'about', $lang, $defaultLang),
            'contact' => $this->contentPath('page', 'contact', $lang, $defaultLang),
        ];
    }

    private function sitemapItems(Request $request, ?string $lang): array
    {
        $languages = $this->languages();
        $defaultLang = $this->defaultLang($languages);
        $enabledLangs = array_values(array_filter(array_map(fn ($language) => (string) ($language['code'] ?? ''), $languages)));
        $targetLangs = $lang ? [$this->normalizeLang($lang)] : $enabledLangs;
        $targetLangs = array_values(array_intersect($targetLangs, $enabledLangs));
        if ($targetLangs === []) {
            $targetLangs = [$defaultLang];
        }

        $settings = $this->settings($defaultLang);
        $baseUrl = $this->baseUrl($request, $settings);
        $rows = Db::table('b8cms_content')
            ->whereIn('lang_code', $targetLangs)
            ->where('status', 1)
            ->whereNull('delete_time')
            ->field('content_type,lang_code,slug,title,summary,cover_image,images,extra,seo_title,published_at,update_time')
            ->order('lang_code', 'asc')
            ->order('content_type', 'asc')
            ->order('sort', 'asc')
            ->order('published_at', 'desc')
            ->select()
            ->toArray();
        $alternateRows = Db::table('b8cms_content')
            ->whereIn('lang_code', $enabledLangs)
            ->where('status', 1)
            ->whereNull('delete_time')
            ->field('content_type,lang_code,slug')
            ->select()
            ->toArray();
        $alternateIndex = $this->contentAlternateIndex($alternateRows);

        $latestByLang = [];
        foreach ($rows as $row) {
            $rowLang = (string) ($row['lang_code'] ?? '');
            $lastmod = $this->sitemapLastmod($row['update_time'] ?? null, $row['published_at'] ?? null);
            if ($lastmod !== '' && (!isset($latestByLang[$rowLang]) || $lastmod > $latestByLang[$rowLang])) {
                $latestByLang[$rowLang] = $lastmod;
            }
        }

        $items = [];
        foreach ($targetLangs as $targetLang) {
            $items[] = [
                'loc' => $this->absoluteUrl($baseUrl, $this->homePath($targetLang, $defaultLang)),
                'lastmod' => $latestByLang[$targetLang] ?? '',
                'alternates' => $this->sitemapHomeAlternates($languages, $baseUrl, $defaultLang),
                'x_default' => $this->absoluteUrl($baseUrl, $this->homePath($defaultLang, $defaultLang)),
                'images' => [],
            ];
        }

        foreach ($rows as $row) {
            $type = (string) ($row['content_type'] ?? '');
            $slug = (string) ($row['slug'] ?? '');
            $rowLang = (string) ($row['lang_code'] ?? '');
            if ($slug === '' || !in_array($type, ['article', 'product', 'page'], true)) {
                continue;
            }

            $items[] = [
                'loc' => $this->absoluteUrl($baseUrl, $this->contentPath($type, $slug, $rowLang, $defaultLang)),
                'lastmod' => $this->sitemapLastmod($row['update_time'] ?? null, $row['published_at'] ?? null),
                'alternates' => $this->sitemapContentAlternates($languages, $alternateIndex, $baseUrl, $defaultLang, $type, $slug),
                'x_default' => $this->sitemapContentXDefault($alternateIndex, $baseUrl, $defaultLang, $type, $slug),
                'images' => $this->sitemapImagesForContent($row, $baseUrl),
            ];
        }

        return $this->uniqueSitemapItems($items);
    }

    private function contentAlternateIndex(array $rows): array
    {
        $index = [];
        foreach ($rows as $row) {
            $type = (string) ($row['content_type'] ?? '');
            $slug = (string) ($row['slug'] ?? '');
            $lang = (string) ($row['lang_code'] ?? '');
            if ($type === '' || $slug === '' || $lang === '') {
                continue;
            }
            $index[$type . ':' . $slug][$lang] = $slug;
        }

        return $index;
    }

    private function sitemapHomeAlternates(array $languages, string $baseUrl, string $defaultLang): array
    {
        $alternates = [];
        foreach ($languages as $language) {
            $lang = (string) ($language['code'] ?? '');
            if ($lang === '') {
                continue;
            }
            $alternates[] = [
                'hreflang' => str_replace('_', '-', $lang),
                'url' => $this->absoluteUrl($baseUrl, $this->homePath($lang, $defaultLang)),
            ];
        }

        return $alternates;
    }

    private function sitemapContentAlternates(array $languages, array $alternateIndex, string $baseUrl, string $defaultLang, string $type, string $slug): array
    {
        $alternates = [];
        $items = $alternateIndex[$type . ':' . $slug] ?? [];
        foreach ($languages as $language) {
            $lang = (string) ($language['code'] ?? '');
            if ($lang === '' || !isset($items[$lang])) {
                continue;
            }
            $alternates[] = [
                'hreflang' => str_replace('_', '-', $lang),
                'url' => $this->absoluteUrl($baseUrl, $this->contentPath($type, (string) $items[$lang], $lang, $defaultLang)),
            ];
        }

        return $alternates;
    }

    private function sitemapContentXDefault(array $alternateIndex, string $baseUrl, string $defaultLang, string $type, string $slug): string
    {
        $items = $alternateIndex[$type . ':' . $slug] ?? [];
        if (!isset($items[$defaultLang])) {
            return '';
        }

        return $this->absoluteUrl($baseUrl, $this->contentPath($type, (string) $items[$defaultLang], $defaultLang, $defaultLang));
    }

    private function sitemapImagesForContent(array $row, string $baseUrl): array
    {
        $extra = json_decode((string) ($row['extra'] ?? '{}'), true) ?: [];
        $seo = is_array($extra['seo'] ?? null) ? $extra['seo'] : [];
        $images = [];
        $sources = [
            $row['cover_image'] ?? '',
            $seo['og_image'] ?? '',
            $seo['twitter_image'] ?? '',
        ];
        $gallery = json_decode((string) ($row['images'] ?? '[]'), true) ?: [];
        foreach ($gallery as $image) {
            $sources[] = is_array($image) ? ($image['url'] ?? '') : $image;
        }

        $title = $this->firstFilled($extra['image_title'] ?? null, $extra['image_alt'] ?? null, $row['seo_title'] ?? null, $row['title'] ?? null);
        $caption = $this->firstFilled($extra['image_caption'] ?? null, $row['summary'] ?? null);
        foreach ($sources as $source) {
            $loc = $this->absoluteAssetUrl($baseUrl, (string) $source);
            if ($loc === '' || isset($images[$loc])) {
                continue;
            }
            $images[$loc] = [
                'loc' => $loc,
                'title' => $title,
                'caption' => $caption,
            ];
        }

        return array_values($images);
    }

    private function defaultRobotsRules(): string
    {
        return implode("\n", [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin',
            'Disallow: /app/',
            'Disallow: /apidoc/',
            'Disallow: /runtime/',
        ]);
    }

    private function robotsSettingLines(mixed $value, string $fallback = ''): array
    {
        if (is_array($value)) {
            $text = implode("\n", array_map('strval', $value));
        } else {
            $text = trim((string) $value);
        }

        if ($text === '') {
            $text = $fallback;
        }

        if ($text === '') {
            return [];
        }

        $lines = preg_split('/\R/', $text) ?: [];
        return array_values(array_filter(array_map(function (string $line) {
            $line = trim($line);
            return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $line) ?: '';
        }, $lines), fn (string $line) => $line !== ''));
    }

    private function normalizeSiteUrl(string $url, string $lang, string $defaultLang): string
    {
        $url = trim($url);
        if ($url === '') {
            return '#';
        }
        if (preg_match('/^(https?:)?\/\//', $url) || preg_match('/^(mailto|tel):/i', $url) || str_starts_with($url, '#')) {
            return $url;
        }

        $parts = parse_url($url);
        $path = $parts['path'] ?? '/';
        $query = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }
        $targetLang = isset($query['lang']) && is_string($query['lang']) && $query['lang'] !== '' ? $query['lang'] : $lang;
        $path = $this->stripSiteBasePath($path);

        if ($path === '/' || $path === '') {
            return $this->homePath($targetLang, $defaultLang);
        }

        if (preg_match('~^/([A-Za-z]{2}-[A-Za-z]{2})$~', $path, $matches)) {
            return $this->homePath($matches[1], $defaultLang);
        }

        if (preg_match('~^/([A-Za-z]{2}-[A-Za-z]{2})/(article|product|page)/([^/?#]+?)(?:\.html)?$~', $path, $matches)) {
            return $this->contentPath($matches[2], $matches[3], $matches[1], $defaultLang);
        }

        if (preg_match('~^/(article|product|page)/([^/?#]+?)(?:\.html)?$~', $path, $matches)) {
            return $this->contentPath($matches[1], $matches[2], $targetLang, $defaultLang);
        }

        return $url;
    }

    private function siteBasePath(): string
    {
        $path = '/' . trim((string) config('plugin.b8cms.app.site_path', '/cms'), '/');
        return $path === '/' ? '' : $path;
    }

    private function withSiteBase(string $path): string
    {
        $base = $this->siteBasePath();
        if ($base === '') {
            return $path;
        }

        return $path === '/' ? $base : $base . $path;
    }

    private function stripSiteBasePath(string $path): string
    {
        $base = $this->siteBasePath();
        if ($base === '' || $path === $base) {
            return $path === $base ? '/' : $path;
        }

        return str_starts_with($path, $base . '/') ? substr($path, strlen($base)) : $path;
    }

    private function productParams(array $extra): array
    {
        $schema = $extra['product_params_schema'] ?? [];
        $values = $extra['product_params'] ?? [];
        if (!is_array($schema) || !is_array($values)) {
            return [];
        }

        $params = [];
        foreach ($schema as $field) {
            if (!is_array($field)) {
                continue;
            }

            $key = trim((string) ($field['key'] ?? ''));
            if ($key === '' || !array_key_exists($key, $values)) {
                continue;
            }

            $value = $values[$key];
            if ($value === null || $value === '') {
                continue;
            }

            if (is_bool($value)) {
                $value = $value ? 'Yes' : 'No';
            } elseif (is_array($value)) {
                $value = implode(', ', array_filter(array_map('strval', $value)));
            }

            $params[] = [
                'key' => $key,
                'label' => (string) ($field['label'] ?? $key),
                'value' => (string) $value,
                'unit' => (string) ($field['unit'] ?? ''),
            ];
        }

        return $params;
    }

    private function contentSeoExtra(?array $content): array
    {
        $extra = is_array($content['extra'] ?? null) ? $content['extra'] : [];
        $seo = is_array($extra['seo'] ?? null) ? $extra['seo'] : [];
        return [
            'robots' => trim((string) ($seo['robots'] ?? '')),
            'canonical_url' => trim((string) ($seo['canonical_url'] ?? '')),
            'og_title' => trim((string) ($seo['og_title'] ?? '')),
            'og_description' => trim((string) ($seo['og_description'] ?? '')),
            'og_image' => trim((string) ($seo['og_image'] ?? '')),
            'twitter_title' => trim((string) ($seo['twitter_title'] ?? '')),
            'twitter_description' => trim((string) ($seo['twitter_description'] ?? '')),
            'twitter_image' => trim((string) ($seo['twitter_image'] ?? '')),
            'twitter_creator' => trim((string) ($seo['twitter_creator'] ?? '')),
            'twitter_card' => trim((string) ($seo['twitter_card'] ?? '')),
            'author_name' => trim((string) ($seo['author_name'] ?? '')),
            'schema_type' => trim((string) ($seo['schema_type'] ?? '')),
            'product_brand' => trim((string) ($seo['product_brand'] ?? '')),
            'product_mpn' => trim((string) ($seo['product_mpn'] ?? '')),
            'product_gtin' => trim((string) ($seo['product_gtin'] ?? '')),
            'product_manufacturer' => trim((string) ($seo['product_manufacturer'] ?? '')),
            'schema_enabled' => !array_key_exists('schema_enabled', $seo) || $seo['schema_enabled'] !== false,
        ];
    }

    private function structuredDataJson(array $settings, ?array $content, array $seoLinks, ?string $type, string $image): string
    {
        $siteName = $this->firstFilled($settings['site_name'] ?? null, 'B8CMS');
        $siteUrl = (string) ($seoLinks['home_url'] ?? '');
        $pageUrl = (string) ($seoLinks['canonical'] ?? $siteUrl);
        $title = $this->firstFilled($content['seo_title'] ?? null, $content['title'] ?? null, $settings['seo_title'] ?? null, $siteName);
        $description = $this->firstFilled($content['seo_description'] ?? null, $content['summary'] ?? null, $settings['seo_description'] ?? null);
        $organization = $this->organizationStructuredData($settings, $siteUrl, $siteName);

        if (!$content) {
            $graph = [
                [
                    '@type' => 'WebSite',
                    '@id' => $siteUrl . '#website',
                    'url' => $siteUrl,
                    'name' => $siteName,
                    'description' => $description,
                    'inLanguage' => (string) ($settings['locale'] ?? ''),
                    'publisher' => ['@id' => $siteUrl . '#organization'],
                ],
                $organization,
            ];

            return $this->jsonLd(['@context' => 'https://schema.org', '@graph' => $this->cleanStructuredData($graph)]);
        }

        $extraSeo = $this->contentSeoExtra($content);
        $base = [
            '@id' => $pageUrl . '#primary',
            'url' => $pageUrl,
            'name' => $title,
            'headline' => $title,
            'description' => $description,
            'inLanguage' => (string) ($content['lang_code'] ?? ''),
            'mainEntityOfPage' => $pageUrl,
            'publisher' => ['@id' => $siteUrl . '#organization'],
        ];
        if ($image !== '') {
            $base['image'] = [$image];
        }

        if ($type === 'product') {
            $primary = array_merge(['@type' => 'Product'], $base, [
                'sku' => (string) ($content['sku'] ?? ''),
                'mpn' => $extraSeo['product_mpn'] ?? '',
                'gtin' => $extraSeo['product_gtin'] ?? '',
                'brand' => [
                    '@type' => 'Brand',
                    'name' => $this->firstFilled($extraSeo['product_brand'] ?? null, $content['category'] ?? null, $siteName),
                ],
                'manufacturer' => [
                    '@type' => 'Organization',
                    'name' => $this->firstFilled($extraSeo['product_manufacturer'] ?? null, $siteName),
                ],
                'additionalProperty' => $this->productAdditionalProperties($content['product_params'] ?? []),
            ]);
            if ((float) ($content['price'] ?? 0) > 0) {
                $primary['offers'] = [
                    '@type' => 'Offer',
                    'url' => $pageUrl,
                    'priceCurrency' => (string) ($content['currency'] ?? 'USD'),
                    'price' => (string) (float) ($content['price'] ?? 0),
                    'availability' => ((int) ($content['stock'] ?? 0) > 0) ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                ];
            }
        } elseif ($type === 'article') {
            $authorName = $this->firstFilled($extraSeo['author_name'] ?? null, $siteName);
            $primary = array_merge(['@type' => 'Article'], $base, [
                'datePublished' => $this->dateIso($content['published_at'] ?? null),
                'dateModified' => $this->dateIso($content['update_time'] ?? $content['published_at'] ?? null),
                'author' => [
                    '@type' => $authorName === $siteName ? 'Organization' : 'Person',
                    'name' => $authorName,
                ],
            ]);
        } else {
            $primary = array_merge(['@type' => $this->structuredPageType($extraSeo, $content)], $base, [
                'datePublished' => $this->dateIso($content['published_at'] ?? null),
                'dateModified' => $this->dateIso($content['update_time'] ?? $content['published_at'] ?? null),
            ]);
        }

        $graph = [
            $organization,
            $primary,
            [
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    [
                        '@type' => 'ListItem',
                        'position' => 1,
                        'name' => $siteName,
                        'item' => $siteUrl,
                    ],
                    [
                        '@type' => 'ListItem',
                        'position' => 2,
                        'name' => (string) ($content['title'] ?? $title),
                        'item' => $pageUrl,
                    ],
                ],
            ],
        ];

        return $this->jsonLd(['@context' => 'https://schema.org', '@graph' => $this->cleanStructuredData($graph)]);
    }

    private function organizationStructuredData(array $settings, string $siteUrl, string $siteName): array
    {
        $baseUrl = $this->baseFromUrl($siteUrl);
        return [
            '@type' => 'Organization',
            '@id' => $siteUrl . '#organization',
            'name' => $siteName,
            'legalName' => $this->firstFilled($settings['legal_name'] ?? null, $siteName),
            'url' => $siteUrl,
            'logo' => $this->absoluteAssetUrl($baseUrl, (string) ($settings['logo'] ?? '')),
            'sameAs' => $this->socialLinkUrls($settings['social_links'] ?? []),
            'contactPoint' => $this->contactPoint($settings),
        ];
    }

    private function socialLinkUrls(mixed $links): array
    {
        if (!is_array($links)) {
            return [];
        }

        $urls = [];
        foreach ($links as $link) {
            $url = is_array($link) ? (string) ($link['url'] ?? '') : (string) $link;
            if ($url !== '' && preg_match('/^https?:\/\//i', $url)) {
                $urls[] = $url;
            }
        }

        return array_values(array_unique($urls));
    }

    private function contactPoint(array $settings): array
    {
        $phone = $this->firstFilled($settings['contact_phone'] ?? null);
        $email = $this->firstFilled($settings['contact_email'] ?? null);
        if ($phone === '' && $email === '') {
            return [];
        }

        return [
            '@type' => 'ContactPoint',
            'telephone' => $phone,
            'email' => $email,
            'contactType' => $this->firstFilled($settings['business_contact_type'] ?? null, 'customer service'),
        ];
    }

    private function productAdditionalProperties(mixed $params): array
    {
        if (!is_array($params)) {
            return [];
        }

        $properties = [];
        foreach ($params as $param) {
            if (!is_array($param) || ($param['value'] ?? '') === '') {
                continue;
            }
            $properties[] = [
                '@type' => 'PropertyValue',
                'name' => (string) ($param['label'] ?? $param['key'] ?? ''),
                'value' => (string) $param['value'],
                'unitText' => (string) ($param['unit'] ?? ''),
            ];
        }

        return $properties;
    }

    private function structuredPageType(array $extraSeo, array $content): string
    {
        $allowed = ['WebPage', 'AboutPage', 'ContactPage', 'CollectionPage', 'FAQPage'];
        $configured = $extraSeo['schema_type'] ?? '';
        if (in_array($configured, $allowed, true)) {
            return $configured;
        }

        return match ((string) ($content['slug'] ?? '')) {
            'about' => 'AboutPage',
            'contact' => 'ContactPage',
            'products', 'news' => 'CollectionPage',
            default => 'WebPage',
        };
    }

    private function matchedCommentFilter(string $nickname, string $email, string $comment): ?array
    {
        $rules = Db::table('b8cms_comment_filter')
            ->where('status', 1)
            ->whereNull('delete_time')
            ->field('rule_type,match_type,value')
            ->select()
            ->toArray();
        $text = $nickname . "\n" . $comment;
        $emailDomain = strtolower(substr(strrchr($email, '@') ?: '', 1));

        foreach ($rules as $rule) {
            $ruleType = (string) ($rule['rule_type'] ?? '');
            $matchType = (string) ($rule['match_type'] ?? 'contains');
            $value = trim((string) ($rule['value'] ?? ''));
            if ($value === '') {
                continue;
            }

            if ($ruleType === 'email' && $this->matchesEmailRule($email, $emailDomain, $matchType, $value)) {
                return [
                    'reason' => '邮箱命中屏蔽规则',
                    'rule' => "{$ruleType}:{$matchType}:{$value}",
                ];
            }

            if ($ruleType === 'word' && $this->matchesTextRule($text, $matchType, $value)) {
                return [
                    'reason' => '评论命中屏蔽词',
                    'rule' => "{$ruleType}:{$matchType}:{$value}",
                ];
            }
        }

        return null;
    }

    private function matchesEmailRule(string $email, string $domain, string $matchType, string $value): bool
    {
        $email = strtolower($email);
        $value = strtolower($value);

        return match ($matchType) {
            'exact' => $email === $value,
            'domain' => $domain === $value || str_ends_with($domain, '.' . $value),
            'regex' => @preg_match($value, $email) === 1,
            default => str_contains($email, $value),
        };
    }

    private function matchesTextRule(string $text, string $matchType, string $value): bool
    {
        $target = $this->lowerText($text);
        $rule = $this->lowerText($value);

        return match ($matchType) {
            'exact' => $target === $rule,
            'regex' => @preg_match($value, $text) === 1,
            default => str_contains($target, $rule),
        };
    }

    private function buildCommentTree(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            $row['children'] = [];
            $items[(int) $row['id']] = $row;
        }

        $tree = [];
        foreach ($items as $id => &$item) {
            $parentId = (int) ($item['parent_id'] ?? 0);
            if ($parentId > 0 && isset($items[$parentId])) {
                $items[$parentId]['children'][] = &$item;
                continue;
            }

            $tree[] = &$item;
        }
        unset($item);

        return $tree;
    }

    private function commentPathSegment(int $id): string
    {
        return str_pad((string) $id, 10, '0', STR_PAD_LEFT);
    }

    private function lowerText(string $text): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
    }

    private function limitText(string $text, int $length): string
    {
        return function_exists('mb_substr') ? mb_substr($text, 0, $length, 'UTF-8') : substr($text, 0, $length);
    }

    public function preferredBaseRedirectUrl(Request $request): string
    {
        $settings = $this->settings($this->defaultLang($this->languages()));
        $preferredBaseUrl = $this->baseUrl($request, $settings);
        $requestBaseUrl = $this->requestBaseUrl($request);
        if ($preferredBaseUrl === '' || $requestBaseUrl === '' || strcasecmp(rtrim($preferredBaseUrl, '/'), rtrim($requestBaseUrl, '/')) === 0) {
            return '';
        }

        $path = '/' . ltrim($request->path(), '/');
        $query = (string) $request->queryString();
        return rtrim($preferredBaseUrl, '/') . $path . ($query === '' ? '' : '?' . $query);
    }

    private function baseUrl(?Request $request, array $settings = []): string
    {
        $configured = $this->normalizeBaseUrl((string) ($settings['site_url'] ?? ''));
        $baseUrl = $configured !== '' ? $configured : $this->requestBaseUrl($request);
        return $this->applyCanonicalBaseOptions($baseUrl, $settings);
    }

    private function requestBaseUrl(?Request $request): string
    {
        if (!$request) {
            return '';
        }

        $scheme = trim(explode(',', (string) $request->header('x-forwarded-proto', ''))[0]);
        $scheme = $scheme !== '' ? $scheme : 'http';
        return $scheme . '://' . $request->host();
    }

    private function normalizeBaseUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (!preg_match('/^https?:\/\//i', $url)) {
            $url = 'https://' . $url;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME) ?: 'https';
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return '';
        }
        $port = parse_url($url, PHP_URL_PORT);

        return strtolower($scheme) . '://' . strtolower($host) . ($port ? ':' . $port : '');
    }

    private function applyCanonicalBaseOptions(string $baseUrl, array $settings): string
    {
        $baseUrl = $this->normalizeBaseUrl($baseUrl);
        if ($baseUrl === '') {
            return '';
        }

        $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'http';
        $host = parse_url($baseUrl, PHP_URL_HOST) ?: '';
        $port = parse_url($baseUrl, PHP_URL_PORT);
        if ($this->settingTruthy($settings['force_https'] ?? false)) {
            $scheme = 'https';
            if ($port === 80) {
                $port = null;
            }
        }

        $hostMode = (string) ($settings['canonical_host_mode'] ?? 'keep');
        if ($hostMode === 'www' && !str_starts_with($host, 'www.')) {
            $host = 'www.' . $host;
        } elseif ($hostMode === 'non_www' && str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        return $scheme . '://' . $host . ($port ? ':' . $port : '');
    }

    private function settingTruthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    private function absoluteUrl(string $baseUrl, string $path): string
    {
        if (preg_match('/^https?:\/\//i', $path)) {
            return $path;
        }
        if (str_starts_with($path, '//')) {
            return ($this->schemeFromUrl($baseUrl) ?: 'https') . ':' . $path;
        }

        return $baseUrl === '' ? $path : rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }

    private function absoluteAssetUrl(string $baseUrl, string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (preg_match('/^https?:\/\//i', $url)) {
            return $url;
        }
        if (str_starts_with($url, '//')) {
            return ($this->schemeFromUrl($baseUrl) ?: 'https') . ':' . $url;
        }
        if ($baseUrl === '') {
            return $url;
        }

        return rtrim($baseUrl, '/') . '/' . ltrim($url, '/');
    }

    private function baseFromUrl(string $url): string
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);
        if (!$scheme || !$host) {
            return '';
        }

        $port = parse_url($url, PHP_URL_PORT);
        return $scheme . '://' . $host . ($port ? ':' . $port : '');
    }

    private function schemeFromUrl(string $url): string
    {
        return (string) parse_url($url, PHP_URL_SCHEME);
    }

    private function sitemapLastmod(mixed ...$values): string
    {
        foreach ($values as $value) {
            $timestamp = strtotime((string) $value);
            if ($timestamp !== false) {
                return date('Y-m-d', $timestamp);
            }
        }

        return '';
    }

    private function uniqueSitemapItems(array $items): array
    {
        $unique = [];
        foreach ($items as $item) {
            $loc = (string) ($item['loc'] ?? '');
            if ($loc === '' || isset($unique[$loc])) {
                continue;
            }
            $unique[$loc] = [
                'loc' => $loc,
                'lastmod' => (string) ($item['lastmod'] ?? ''),
                'alternates' => $item['alternates'] ?? [],
                'x_default' => (string) ($item['x_default'] ?? ''),
                'images' => $item['images'] ?? [],
            ];
        }

        return array_values($unique);
    }

    private function jsonLd(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }

    private function cleanStructuredData(array $value): array
    {
        $clean = [];
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $item = $this->cleanStructuredData($item);
                if ($item === []) {
                    continue;
                }
                $clean[$key] = $item;
                continue;
            }

            if ($item === null || $item === '') {
                continue;
            }

            $clean[$key] = $item;
        }

        return array_is_list($value) ? array_values($clean) : $clean;
    }

    private function dateIso(mixed $value): string
    {
        $timestamp = strtotime((string) $value);
        return $timestamp === false ? '' : date('c', $timestamp);
    }

    private function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    private function defaultLang(array $languages): string
    {
        foreach ($languages as $language) {
            if ((int) ($language['is_default'] ?? 2) === 1 && !empty($language['code'])) {
                return (string) $language['code'];
            }
        }

        return (string) ($languages[0]['code'] ?? 'zh-CN');
    }

    private function localeForLang(array $languages, string $lang): string
    {
        foreach ($languages as $language) {
            if ((string) ($language['code'] ?? '') === $lang) {
                return $this->localeForLanguage($language);
            }
        }

        return str_replace('-', '_', $lang);
    }

    private function localeForLanguage(array $language): string
    {
        $locale = trim((string) ($language['locale'] ?? ''));
        if ($locale !== '') {
            return str_replace('-', '_', $locale);
        }

        return str_replace('-', '_', (string) ($language['code'] ?? ''));
    }

    private function firstFilled(mixed ...$values): string
    {
        foreach ($values as $value) {
            if (is_array($value) || is_object($value)) {
                continue;
            }
            $value = trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function publicContentFields(): string
    {
        return 'id,content_type,lang_code,slug,title,subtitle,category,summary,cover_image,price,currency,stock,sku,sort,is_featured,published_at,seo_title,seo_keywords,seo_description,template_file';
    }
}
