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
        $slug = (string) $request->route->param('slug', '');
        $lang = (string) $request->route->param('lang', $request->input('lang', ''));
        $content = $this->site->contentDetail($type, $slug, $lang);
        if (!$content) {
            return response('Page not found', 404);
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
}
