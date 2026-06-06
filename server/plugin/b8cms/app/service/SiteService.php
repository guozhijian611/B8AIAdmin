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

        return [
            'lang' => $lang,
            'languages' => $this->languages(),
            'template' => $template,
            'settings' => $this->settings($lang),
            'header_nav' => $this->navigations($lang, 'header'),
            'footer_nav' => $this->navigations($lang, 'footer'),
            'featured_articles' => $this->contents('article', $lang, 6, true),
            'featured_products' => $this->contents('product', $lang, 6, true),
            'pages' => $this->contents('page', $lang, 20),
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
        return $content;
    }

    public function pageContext(?string $lang = null, ?array $content = null): array
    {
        $context = $this->bootstrap($lang);
        $context['content'] = $content;
        $context['seo'] = $this->seo($context['settings'], $content);
        return $context;
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

    private function navigations(string $lang, string $position): array
    {
        return Db::table('b8cms_navigation')
            ->where('lang_code', $lang)
            ->where('position', $position)
            ->where('status', 1)
            ->whereNull('delete_time')
            ->field('id,parent_id,title,url,target,content_type,content_id,sort')
            ->order('sort', 'asc')
            ->select()
            ->toArray();
    }

    private function contents(string $type, string $lang, int $limit, bool $featured = false): array
    {
        $query = Db::table('b8cms_content')
            ->where('content_type', $type)
            ->where('lang_code', $lang)
            ->where('status', 1)
            ->whereNull('delete_time');

        if ($featured) {
            $query->where('is_featured', 1);
        }

        return $query->field($this->publicContentFields())
            ->order('sort', 'asc')
            ->order('published_at', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();
    }

    private function seo(array $settings, ?array $content): array
    {
        return [
            'title' => $content['seo_title'] ?? $content['title'] ?? $settings['seo_title'] ?? $settings['site_name'] ?? 'B8CMS',
            'keywords' => $content['seo_keywords'] ?? $settings['seo_keywords'] ?? '',
            'description' => $content['seo_description'] ?? $content['summary'] ?? $settings['seo_description'] ?? '',
        ];
    }

    private function publicContentFields(): string
    {
        return 'id,content_type,lang_code,slug,title,subtitle,category,summary,cover_image,price,currency,stock,sku,sort,is_featured,published_at,seo_title,seo_keywords,seo_description,template_file';
    }
}
