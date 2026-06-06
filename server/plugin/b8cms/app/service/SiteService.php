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
        return $content;
    }

    public function pageContext(?string $lang = null, ?array $content = null, ?Request $request = null, ?string $type = null): array
    {
        $context = $this->bootstrap($lang);
        $context['content'] = $content;
        $context['seo'] = $this->seo($context['settings'], $content);
        $context['seo_links'] = $this->seoLinks($context['languages'], $context['lang'], $content, $request, $type);
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

    private function seo(array $settings, ?array $content): array
    {
        return [
            'title' => $this->firstFilled($content['seo_title'] ?? null, $content['title'] ?? null, $settings['seo_title'] ?? null, $settings['site_name'] ?? null, 'B8CMS'),
            'keywords' => $this->firstFilled($content['seo_keywords'] ?? null, $settings['seo_keywords'] ?? null),
            'description' => $this->firstFilled($content['seo_description'] ?? null, $content['summary'] ?? null, $settings['seo_description'] ?? null),
        ];
    }

    private function seoLinks(array $languages, string $currentLang, ?array $content, ?Request $request, ?string $type): array
    {
        $baseUrl = $this->baseUrl($request);
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

        return [
            'canonical' => $this->absoluteUrl($baseUrl, $canonicalPath),
            'x_default' => $xDefault,
            'home_url' => $this->absoluteUrl($baseUrl, $this->homePath($currentLang, $defaultLang)),
            'alternates' => $alternates,
        ];
    }

    private function contentPath(string $type, string $slug, string $lang, string $defaultLang): string
    {
        $type = in_array($type, ['article', 'product', 'page'], true) ? $type : 'page';
        return $this->withSiteBase($lang === $defaultLang ? "/{$type}/{$slug}" : "/{$lang}/{$type}/{$slug}");
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

        if (preg_match('~^/([A-Za-z]{2}-[A-Za-z]{2})/(article|product|page)/([^/?#]+)$~', $path, $matches)) {
            return $this->contentPath($matches[2], $matches[3], $matches[1], $defaultLang);
        }

        if (preg_match('~^/(article|product|page)/([^/?#]+)$~', $path, $matches)) {
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

    private function baseUrl(?Request $request): string
    {
        if (!$request) {
            return '';
        }

        $scheme = trim(explode(',', (string) $request->header('x-forwarded-proto', ''))[0]);
        $scheme = $scheme !== '' ? $scheme : 'http';
        return $scheme . '://' . $request->host();
    }

    private function absoluteUrl(string $baseUrl, string $path): string
    {
        return $baseUrl === '' ? $path : rtrim($baseUrl, '/') . $path;
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

    private function firstFilled(?string ...$values): string
    {
        foreach ($values as $value) {
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
