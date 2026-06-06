<?php

namespace plugin\b8cms\app\api\controller;

use plugin\b8cms\app\service\SiteService;
use support\Request;
use support\Response;

class SiteViewController
{
    public function __construct(private readonly SiteService $site = new SiteService())
    {
    }

    public function home(Request $request): Response
    {
        $lang = (string) $request->route?->param('lang', $request->input('lang', ''));
        if ($response = $this->redirectLegacyLangUrl($request, $this->site->canonicalPath($lang))) {
            return $response;
        }

        return $this->render($request, 'index');
    }

    public function article(Request $request): Response
    {
        return $this->content($request, 'article');
    }

    public function product(Request $request): Response
    {
        return $this->content($request, 'product');
    }

    public function page(Request $request): Response
    {
        return $this->content($request, 'page');
    }

    private function content(Request $request, string $type): Response
    {
        $slug = preg_replace('/\.html$/i', '', (string) $request->route->param('slug', '')) ?: '';
        $lang = (string) $request->route->param('lang', $request->input('lang', ''));
        $content = $this->site->contentDetail($type, $slug, $lang);
        if (!$content) {
            return response('Page not found', 404);
        }

        if ($response = $this->redirectCanonicalUrl($request, $this->site->canonicalPath((string) $content['lang_code'], $content, $type))) {
            return $response;
        }

        $template = $content['template_file'] ?: $type;
        return $this->render($request, $template, $content, (string) $content['lang_code'], $type);
    }

    private function render(Request $request, string $template, ?array $content = null, ?string $lang = null, ?string $type = null): Response
    {
        $lang = $lang ?: (string) $request->route?->param('lang', $request->input('lang', ''));
        $context = $this->site->pageContext($lang, $content, $request, $type);
        $templateKey = $context['template']['template_key'] ?? 'default';
        $template = $this->resolveTemplate($template, $content ? ($type ?: 'page') : 'index', $templateKey);
        return think_view($templateKey . '/' . $template, $context, '', 'b8cms');
    }

    private function resolveTemplate(string $template, string $fallback, string $templateKey): string
    {
        $template = trim($template) ?: $fallback;
        if (!preg_match('/^[A-Za-z0-9_\/-]+$/', $template) || str_contains($template, '..') || str_starts_with($template, '/')) {
            $template = $fallback;
        }

        $templatePath = base_path() . "/plugin/b8cms/app/view/{$templateKey}/{$template}.html";
        return is_file($templatePath) ? $template : $fallback;
    }

    private function redirectLegacyLangUrl(Request $request, string $canonicalPath): ?Response
    {
        if ((string) $request->input('lang', '') === '') {
            return null;
        }

        $query = [];
        parse_str((string) $request->queryString(), $query);
        unset($query['lang']);

        $target = $canonicalPath;
        if ($query !== []) {
            $target .= '?' . http_build_query($query);
        }

        return redirect($target)->withStatus(301);
    }

    private function redirectCanonicalUrl(Request $request, string $canonicalPath): ?Response
    {
        $query = [];
        parse_str((string) $request->queryString(), $query);
        unset($query['lang']);

        $currentPath = '/' . ltrim($request->path(), '/');
        $target = $canonicalPath;
        if ($query !== []) {
            $target .= '?' . http_build_query($query);
        }

        if ($currentPath === $canonicalPath && (string) $request->input('lang', '') === '') {
            return null;
        }

        return redirect($target)->withStatus(301);
    }
}
