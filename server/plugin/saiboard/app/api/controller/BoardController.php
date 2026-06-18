<?php

namespace plugin\saiboard\app\api\controller;

use hg\apidoc\annotation as Apidoc;
use plugin\saiboard\app\model\QueryTemplate;
use plugin\saiboard\app\model\Screen;
use plugin\saiboard\app\service\DataSourceExecutor;
use support\Request;
use support\Response;

#[Apidoc\Group('SAI Board')]
#[Apidoc\Title('大屏公开运行接口')]
class BoardController
{
    private const MIN_REFRESH_SECONDS = 10;
    private const MAX_REFRESH_SECONDS = 3600;

    public function __construct(private readonly DataSourceExecutor $executor = new DataSourceExecutor())
    {
    }

    #[Apidoc\Title('获取大屏配置')]
    #[Apidoc\Url('/app/saiboard/api/screen/{code}')]
    #[Apidoc\Method('GET')]
    #[Apidoc\Query('token', type: 'string', require: false, desc: '访问令牌')]
    #[Apidoc\Returned('screen', type: 'object', desc: '脱敏大屏配置')]
    public function getScreen(Request $request, string $code): Response
    {
        $screen = $this->publishedScreen($code);
        if (!$screen) {
            return fail('大屏不存在或未发布', 404);
        }
        if (!$this->authorized($request, $screen)) {
            return fail('无权访问大屏', 401);
        }

        return ok([
            'screen' => [
                'code' => $screen->code,
                'name' => $screen->name,
                'width' => (int) $screen->width,
                'height' => (int) $screen->height,
                'bg_config' => $screen->bg_config,
                'is_public' => (int) $screen->is_public,
                'status' => (int) $screen->status,
                'layout' => $this->publicLayout($screen->layout),
            ],
        ]);
    }

    #[Apidoc\Title('获取组件数据')]
    #[Apidoc\Url('/app/saiboard/api/data')]
    #[Apidoc\Method('GET')]
    #[Apidoc\Query('code', type: 'string', require: true, desc: '大屏编码')]
    #[Apidoc\Query('cid', type: 'string', require: true, desc: '组件ID')]
    #[Apidoc\Query('token', type: 'string', require: false, desc: '访问令牌')]
    #[Apidoc\Query('params', type: 'object', require: false, desc: '运行时查询参数')]
    #[Apidoc\Returned('rows', type: 'array', desc: '数据行')]
    public function data(Request $request): Response
    {
        $code = trim((string) $request->input('code', ''));
        $cid = trim((string) $request->input('cid', ''));
        if ($code === '' || $cid === '') {
            return fail('参数错误');
        }

        $screen = $this->publishedScreen($code);
        if (!$screen) {
            return fail('大屏不存在或未发布', 404);
        }
        if (!$this->authorized($request, $screen)) {
            return fail('无权访问大屏', 401);
        }

        $dataset = $this->componentDataset($screen->layout, $cid);
        $queryTemplateId = (int) ($dataset['queryTemplateId'] ?? $dataset['query_template_id'] ?? 0);
        if ($queryTemplateId <= 0) {
            return fail('组件未绑定查询模板');
        }

        $template = QueryTemplate::where('id', $queryTemplateId)->where('status', 1)->findOrEmpty();
        if ($template->isEmpty()) {
            return fail('查询模板不存在或已停用');
        }

        return ok($this->executor->execute($template, false, $this->runtimeParams($request)));
    }

    private function publishedScreen(string $code): ?Screen
    {
        if (!preg_match('/^[A-Za-z0-9_-]{1,32}$/', $code)) {
            return null;
        }

        $screen = Screen::where('code', $code)
            ->where('status', 1)
            ->findOrEmpty();

        return $screen->isEmpty() ? null : $screen;
    }

    private function authorized(Request $request, Screen $screen): bool
    {
        if ((int) $screen->is_public === 1) {
            return true;
        }

        $token = (string) ($request->input('token', '') ?: $request->header('x-saiboard-token', ''));
        $accessToken = trim((string) $screen->access_token);
        if ($accessToken !== '') {
            return $token !== '' && hash_equals($accessToken, $token);
        }

        $current = getCurrentInfo();
        return is_array($current) && ($current['plat'] ?? '') === 'saiadmin';
    }

    private function publicLayout(array $layout): array
    {
        $components = is_array($layout['components'] ?? null) ? $layout['components'] : [];
        foreach ($components as $index => $component) {
            if (!is_array($component)) {
                continue;
            }

            $dataset = is_array($component['dataset'] ?? null) ? $component['dataset'] : [];
            if ($dataset !== []) {
                $dataset['refresh'] = $this->normalizeRefresh($dataset['refresh'] ?? null);
                $component['dataset'] = $dataset;
                $components[$index] = $component;
            }
        }

        $layout['components'] = $components;
        return $layout;
    }

    private function componentDataset(array $layout, string $cid): array
    {
        $components = is_array($layout['components'] ?? null) ? $layout['components'] : [];
        foreach ($components as $component) {
            if (!is_array($component) || (string) ($component['id'] ?? '') !== $cid) {
                continue;
            }

            $dataset = is_array($component['dataset'] ?? null) ? $component['dataset'] : [];
            if ($dataset !== []) {
                $dataset['refresh'] = $this->normalizeRefresh($dataset['refresh'] ?? null);
            }

            return $dataset;
        }

        return [];
    }

    private function normalizeRefresh(mixed $refresh): int
    {
        $refresh = (int) $refresh;
        if ($refresh <= 0) {
            $refresh = 30;
        }

        return min(self::MAX_REFRESH_SECONDS, max(self::MIN_REFRESH_SECONDS, $refresh));
    }

    private function runtimeParams(Request $request): array
    {
        $reserved = ['code' => true, 'cid' => true, 'token' => true];
        $params = $request->input('params', []);
        $result = is_array($params) ? $params : [];

        foreach ($request->all() as $key => $value) {
            $key = (string) $key;
            if (isset($reserved[$key]) || $key === 'params') {
                continue;
            }
            $result[$key] = $value;
        }

        return $result;
    }
}
