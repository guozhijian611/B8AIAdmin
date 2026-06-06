<?php

namespace plugin\b8cms\app\admin\controller;

use plugin\b8cms\app\admin\logic\ContentLogic;
use plugin\b8cms\app\validate\ContentValidate;
use plugin\saiadmin\service\Permission;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use support\Request;
use support\Response;
use support\think\Db;

class ContentController extends AbstractCrudController
{
    public function __construct()
    {
        $this->logic = new ContentLogic();
        $this->validate = new ContentValidate();
        parent::__construct();
    }

    protected function searchFields(): array
    {
        return [
            ['content_type', ''],
            ['lang_code', ''],
            ['title', ''],
            ['slug', ''],
            ['category', ''],
            ['status', ''],
        ];
    }

    #[Permission('内容列表', 'b8cms:content:index')]
    public function index(Request $request): Response
    {
        return parent::index($request);
    }

    #[Permission('内容读取', 'b8cms:content:read')]
    public function read(Request $request): Response
    {
        return parent::read($request);
    }

    #[Permission('内容添加', 'b8cms:content:save')]
    public function save(Request $request): Response
    {
        if ($response = $this->ensureUniqueSlug($request->post())) {
            return $response;
        }

        return parent::save($request);
    }

    #[Permission('内容修改', 'b8cms:content:update')]
    public function update(Request $request): Response
    {
        if ($response = $this->ensureUniqueSlug($request->post())) {
            return $response;
        }

        return parent::update($request);
    }

    #[Permission('内容删除', 'b8cms:content:destroy')]
    public function destroy(Request $request): Response
    {
        return parent::destroy($request);
    }

    #[Permission('内容状态', 'b8cms:content:changeStatus')]
    public function changeStatus(Request $request): Response
    {
        return parent::changeStatus($request);
    }

    #[Permission('批量收录策略', 'b8cms:content:update')]
    public function batchSeoRobots(Request $request): Response
    {
        $ids = array_values(array_filter(array_map('intval', (array) $request->post('ids', []))));
        $robots = trim((string) $request->post('robots', ''));
        if ($ids === [] || !in_array($robots, ['index,follow', 'index,nofollow', 'noindex,follow', 'noindex,nofollow'], true)) {
            return $this->fail('请选择内容和收录策略');
        }

        $rows = Db::table('b8cms_content')
            ->whereIn('id', $ids)
            ->whereNull('delete_time')
            ->field('id,extra')
            ->select()
            ->toArray();

        foreach ($rows as $row) {
            $extra = json_decode((string) ($row['extra'] ?? '{}'), true) ?: [];
            $seo = is_array($extra['seo'] ?? null) ? $extra['seo'] : [];
            $seo['robots'] = $robots;
            $extra['seo'] = $seo;
            Db::table('b8cms_content')
                ->where('id', (int) $row['id'])
                ->update([
                    'extra' => json_encode($extra, JSON_UNESCAPED_UNICODE),
                    'update_time' => date('Y-m-d H:i:s'),
                ]);
        }

        return $this->success('操作成功');
    }

    #[Permission('模板文件选项', 'b8cms:content:index')]
    public function templateOptions(Request $request): Response
    {
        $contentType = (string) $request->input('content_type', 'page');
        if (!in_array($contentType, ['article', 'product', 'page'], true)) {
            $contentType = 'page';
        }

        $templateKey = trim((string) $request->input('template_key', ''));
        if ($templateKey === '') {
            $templateKey = $this->activeTemplateKey();
        }

        return $this->success([
            'template_key' => $templateKey,
            'options' => $this->templateFileOptions($templateKey, $contentType),
        ]);
    }

    private function activeTemplateKey(): string
    {
        $templateKey = Db::table('b8cms_template')
            ->where('is_active', 1)
            ->where('status', 1)
            ->whereNull('delete_time')
            ->order('sort', 'asc')
            ->value('template_key');

        return $templateKey ?: 'default';
    }

    private function ensureUniqueSlug(array $data): ?Response
    {
        $contentType = (string) ($data['content_type'] ?? '');
        $langCode = (string) ($data['lang_code'] ?? '');
        $slug = trim((string) ($data['slug'] ?? ''));
        if ($contentType === '' || $langCode === '' || $slug === '') {
            return null;
        }

        $query = Db::table('b8cms_content')
            ->where('content_type', $contentType)
            ->where('lang_code', $langCode)
            ->where('slug', $slug)
            ->whereNull('delete_time');
        if (!empty($data['id'])) {
            $query->where('id', '<>', (int) $data['id']);
        }

        return $query->value('id') ? $this->fail('同语言同类型下访问别名已存在') : null;
    }

    private function templateFileOptions(string $templateKey, string $contentType): array
    {
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $templateKey)) {
            return [];
        }

        $viewPath = base_path() . "/plugin/b8cms/app/view/{$templateKey}";
        if (!is_dir($viewPath)) {
            return [];
        }

        $options = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($viewPath, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'html') {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($viewPath) + 1));
            if (str_starts_with($relative, 'public/') || $relative === 'index.html') {
                continue;
            }

            $value = preg_replace('/\.html$/', '', $relative);
            if (!$value || str_contains($value, '..')) {
                continue;
            }

            $isRecommended = $value === $contentType || str_starts_with($value, $contentType . '-');
            $options[] = [
                'label' => $relative . ($isRecommended ? '（推荐）' : ''),
                'value' => $value,
                'content_type' => $this->guessTemplateType($value),
                'is_recommended' => $isRecommended,
            ];
        }

        usort($options, function (array $a, array $b) use ($contentType): int {
            $aScore = $this->templateSortScore($a, $contentType);
            $bScore = $this->templateSortScore($b, $contentType);
            if ($aScore === $bScore) {
                return strcmp($a['value'], $b['value']);
            }

            return $aScore <=> $bScore;
        });

        return $options;
    }

    private function guessTemplateType(string $template): string
    {
        foreach (['article', 'product', 'page'] as $type) {
            if ($template === $type || str_starts_with($template, $type . '-')) {
                return $type;
            }
        }

        return 'custom';
    }

    private function templateSortScore(array $option, string $contentType): int
    {
        if ($option['value'] === $contentType) {
            return 0;
        }

        return $option['is_recommended'] ? 10 : 20;
    }
}
