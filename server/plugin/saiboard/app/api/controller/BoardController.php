<?php

namespace plugin\saiboard\app\api\controller;

use hg\apidoc\annotation as Apidoc;
use InvalidArgumentException;
use plugin\saiadmin\app\cache\UserAuthCache;
use plugin\saiadmin\exception\ApiException;
use plugin\saiboard\app\admin\logic\ScreenLogic;
use plugin\saiboard\app\model\Datasource;
use plugin\saiboard\app\model\QueryTemplate;
use plugin\saiboard\app\model\Screen;
use plugin\saiboard\app\model\ScreenToken;
use plugin\saiboard\app\service\DataSourceExecutor;
use plugin\saiboard\app\service\RuntimeGuard;
use RuntimeException;
use support\Request;
use support\Response;
use Throwable;

#[Apidoc\Group('SAI Board')]
#[Apidoc\Title('大屏公开运行接口')]
class BoardController
{
    private const MIN_REFRESH_SECONDS = 10;
    private const MAX_REFRESH_SECONDS = 3600;
    private const TOKEN_TOUCH_INTERVAL_SECONDS = 60;

    public function __construct(
        private readonly DataSourceExecutor $executor = new DataSourceExecutor(),
        private readonly RuntimeGuard $guard = new RuntimeGuard()
    )
    {
    }

    #[Apidoc\Title('获取大屏配置')]
    #[Apidoc\Url('/app/saiboard/api/screen/{code}')]
    #[Apidoc\Method('GET')]
    #[Apidoc\Query('token', type: 'string', require: false, desc: '访问令牌')]
    #[Apidoc\Query('admin_preview', type: 'int', require: false, desc: '后台预览标记，需携带后台 JWT')]
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
        if ($limited = $this->guard->assertAllowed($request, $screen, 'screen')) {
            return $this->rateLimited($limited);
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
    #[Apidoc\Query('admin_preview', type: 'int', require: false, desc: '后台预览标记，需携带后台 JWT')]
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
        if ($limited = $this->guard->assertAllowed($request, $screen, 'data')) {
            return $this->rateLimited($limited);
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
        if (!$this->ownedTemplate($screen, $template)) {
            return fail('查询模板不存在或已停用');
        }

        try {
            return ok($this->executor->execute($template, false, $this->runtimeParams($request), [
                'screen' => (string) $screen->id,
                'owner' => (string) ((int) ($screen->created_by ?? 0)),
            ]));
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() === '数据缓存刷新中') {
                return $this->cacheRefreshing();
            }

            return fail('数据源执行失败');
        } catch (InvalidArgumentException) {
            return fail('数据源执行失败');
        } catch (Throwable) {
            return fail('数据源执行失败');
        }
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

        $token = trim((string) ($request->input('token', '') ?: $request->header('x-saiboard-token', '')));
        if ($token !== '' && $this->validScreenToken($screen, $token)) {
            return true;
        }
        if ($this->isAdminPreview($request) && $this->canAdminPreview($screen)) {
            return true;
        }
        if ($this->hasActiveScreenTokens($screen)) {
            return false;
        }

        $accessToken = trim((string) $screen->access_token);
        if ($accessToken !== '') {
            return $token !== '' && hash_equals($accessToken, $token);
        }

        $current = getCurrentInfo();
        return is_array($current) && ($current['plat'] ?? '') === 'saiadmin';
    }

    private function isAdminPreview(Request $request): bool
    {
        $value = $request->input('admin_preview', '');
        return in_array($value, [1, '1', true, 'true'], true);
    }

    private function canAdminPreview(Screen $screen): bool
    {
        $current = getCurrentInfo();
        if (!is_array($current) || ($current['plat'] ?? '') !== 'saiadmin') {
            return false;
        }

        $adminId = (int) ($current['id'] ?? 0);
        if ($adminId <= 0) {
            return false;
        }
        if ($adminId !== 1 && !in_array('saiboard:screen:read', UserAuthCache::getUserAuth($adminId), true)) {
            return false;
        }

        try {
            (new ScreenLogic())->read((int) $screen->id);
            return true;
        } catch (ApiException) {
            return false;
        } catch (Throwable) {
            return false;
        }
    }

    private function validScreenToken(Screen $screen, string $token): bool
    {
        $record = ScreenToken::where('screen_id', (int) $screen->id)
            ->where('token_hash', hash('sha256', $token))
            ->where('status', 1)
            ->whereNull('delete_time')
            ->findOrEmpty();
        if ($record->isEmpty() || $this->tokenExpired($record->expire_time ?? null)) {
            return false;
        }

        if ($this->shouldTouchToken($record->last_used_time ?? null)) {
            $record->save(['last_used_time' => date('Y-m-d H:i:s')]);
        }

        return true;
    }

    private function hasActiveScreenTokens(Screen $screen): bool
    {
        foreach (ScreenToken::where('screen_id', (int) $screen->id)
            ->where('status', 1)
            ->whereNull('delete_time')
            ->select() as $record) {
            if (!$this->tokenExpired($record->expire_time ?? null)) {
                return true;
            }
        }

        return false;
    }

    private function tokenExpired(mixed $expireTime): bool
    {
        $expireTime = trim((string) $expireTime);
        return $expireTime !== '' && strtotime($expireTime) <= time();
    }

    private function shouldTouchToken(mixed $lastUsedTime): bool
    {
        $lastUsedTime = trim((string) $lastUsedTime);
        if ($lastUsedTime === '') {
            return true;
        }

        $timestamp = strtotime($lastUsedTime);
        return !$timestamp || time() - $timestamp >= self::TOKEN_TOUCH_INTERVAL_SECONDS;
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

    private function ownedTemplate(Screen $screen, QueryTemplate $template): bool
    {
        $owner = (int) ($screen->created_by ?? 0);
        if ($owner <= 0 || (int) ($template->created_by ?? 0) !== $owner) {
            return false;
        }

        $datasourceOwner = Datasource::where('id', (int) $template->datasource_id)
            ->where('status', 1)
            ->value('created_by');

        return (int) $datasourceOwner === $owner;
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
        $reserved = ['code' => true, 'cid' => true, 'token' => true, 'admin_preview' => true];
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

    private function rateLimited(array $limited): Response
    {
        return fail('请求过于频繁，请稍后再试', 429)->withHeaders([
            'Retry-After' => (string) max(1, (int) ($limited['retry_after'] ?? 1)),
            'X-Saiboard-RateLimit-Scope' => (string) ($limited['scope'] ?? 'runtime'),
            'X-Saiboard-RateLimit-Limit' => (string) max(0, (int) ($limited['limit'] ?? 0)),
            'X-Saiboard-RateLimit-Window' => (string) max(1, (int) ($limited['window'] ?? 1)),
        ]);
    }

    private function cacheRefreshing(): Response
    {
        return fail('数据缓存刷新中，请稍后重试', 429)->withHeaders([
            'Retry-After' => '3',
            'X-Saiboard-Cache-State' => 'refreshing',
        ]);
    }
}
