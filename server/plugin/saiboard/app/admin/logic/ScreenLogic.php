<?php

namespace plugin\saiboard\app\admin\logic;

use plugin\saiadmin\basic\think\BaseLogic;
use plugin\saiadmin\exception\ApiException;
use plugin\saiboard\app\model\Screen;
use plugin\saiboard\app\model\ScreenToken;
use plugin\saiboard\app\model\ScreenVersion;

class ScreenLogic extends BaseLogic
{
    protected bool $scope = true;

    public function __construct()
    {
        $this->model = new Screen();
        $this->orderField = 'id';
        $this->orderType = 'DESC';
    }

    public function add(array $data): mixed
    {
        $data = $this->normalizePayload($data);
        return parent::add($data);
    }

    public function edit($id, array $data): mixed
    {
        $data = $this->normalizePayload($data, (int) $id);
        return parent::edit($id, $data);
    }

    public function saveLayout(int $id, array $layout): bool
    {
        return (bool) $this->transaction(function () use ($id, $layout) {
            $screen = $this->read($id);
            $layout = $this->normalizeLayout($layout, (int) $screen->width, (int) $screen->height);
            $this->assertLayoutTemplates($layout, (int) ($screen->created_by ?? 0));
            $result = (bool) $screen->save([
                'draft_layout' => $layout,
            ]);
            if ($result) {
                $this->createVersion($screen, 'save_layout', $layout);
            }

            return $result;
        });
    }

    public function publish(int $id): bool
    {
        return (bool) $this->transaction(function () use ($id) {
            $screen = $this->read($id);
            $layout = $screen->draft_layout ?: $this->defaultLayout((int) $screen->width, (int) $screen->height);
            $layout = $this->normalizeLayout($layout, (int) $screen->width, (int) $screen->height);
            $this->assertLayoutTemplates($layout, (int) ($screen->created_by ?? 0));
            $width = (int) $layout['canvas']['width'];
            $height = (int) $layout['canvas']['height'];
            $bgConfig = $this->normalizeBgConfig($layout['bg_config'] ?? $screen->bg_config);
            $result = (bool) $screen->save([
                'width' => $width,
                'height' => $height,
                'bg_config' => $bgConfig,
                'layout' => $layout,
                'status' => 1,
            ]);
            if ($result) {
                $this->createVersion($screen, 'publish', $layout);
            }

            return $result;
        });
    }

    public function copy(int $id): int
    {
        $screen = $this->read($id);
        $data = $screen->toArray();
        $width = (int) $data['width'];
        $height = (int) $data['height'];
        $draftLayout = $screen->draft_layout ?: $this->defaultLayout($width, $height);
        $publishedLayout = $screen->layout ?: $draftLayout;
        $owner = (int) ($screen->created_by ?? 0);
        $currentOwner = (int) (getCurrentInfo()['id'] ?? 0);
        if ($owner !== $currentOwner) {
            $draftLayout = $this->detachLayoutTemplates($draftLayout);
            $publishedLayout = $this->detachLayoutTemplates($publishedLayout);
        }
        unset($data['id'], $data['create_time'], $data['update_time'], $data['delete_time']);
        $data['name'] = $data['name'] . ' 副本';
        $data['code'] = $this->generateCode();
        $data['access_token'] = '';
        $data['status'] = 2;
        $data['draft_layout'] = $this->normalizeLayout($draftLayout, $width, $height);
        $data['layout'] = $this->normalizeLayout($publishedLayout, $width, $height);

        return (int) parent::add($data);
    }

    public function versions(int $screenId): array
    {
        $this->read($screenId);

        return ScreenVersion::where('screen_id', $screenId)
            ->whereNull('delete_time')
            ->order('id', 'desc')
            ->limit(50)
            ->select()
            ->toArray();
    }

    public function restoreVersion(int $screenId, int $versionId): bool
    {
        return (bool) $this->transaction(function () use ($screenId, $versionId) {
            $screen = $this->read($screenId);
            $version = ScreenVersion::where('screen_id', $screenId)
                ->where('id', $versionId)
                ->whereNull('delete_time')
                ->findOrEmpty();
            if ($version->isEmpty()) {
                throw new ApiException('版本不存在');
            }

            $this->createVersion(
                $screen,
                'restore_before',
                $screen->draft_layout ?: $this->defaultLayout((int) $screen->width, (int) $screen->height)
            );
            $width = (int) $version->width;
            $height = (int) $version->height;
            $layout = $this->normalizeLayout($version->layout, $width, $height);
            $layout['bg_config'] = $this->normalizeBgConfig($version->bg_config);
            $this->assertLayoutTemplates($layout, (int) ($screen->created_by ?? 0));

            return (bool) $screen->save([
                'draft_layout' => $layout,
            ]);
        });
    }

    public function deleteVersion(int $screenId, int $versionId): bool
    {
        $this->read($screenId);
        $version = ScreenVersion::where('screen_id', $screenId)
            ->where('id', $versionId)
            ->whereNull('delete_time')
            ->findOrEmpty();
        if ($version->isEmpty()) {
            throw new ApiException('版本不存在');
        }

        return (bool) $version->delete();
    }

    public function tokens(int $screenId): array
    {
        $this->read($screenId);

        $rows = [];
        foreach (ScreenToken::where('screen_id', $screenId)
            ->whereNull('delete_time')
            ->order('id', 'desc')
            ->select() as $token) {
            $rows[] = $this->formatTokenRow($token);
        }

        return $rows;
    }

    public function createToken(int $screenId, array $data): array
    {
        $this->read($screenId);
        $plainToken = $this->generateToken();
        $token = ScreenToken::create([
            'screen_id' => $screenId,
            'name' => $this->normalizeTokenName($data['name'] ?? ''),
            'token_prefix' => substr($plainToken, 0, 8),
            'token_hash' => $this->tokenHash($plainToken),
            'expire_time' => $this->normalizeExpireTime($data['expire_time'] ?? null),
            'status' => 1,
        ]);

        return [
            'token' => $plainToken,
            'row' => $this->formatTokenRow($token),
        ];
    }

    public function resetToken(int $screenId, int $tokenId): array
    {
        $this->read($screenId);
        $token = $this->tokenRow($screenId, $tokenId);
        $plainToken = $this->generateToken();
        $token->save([
            'token_prefix' => substr($plainToken, 0, 8),
            'token_hash' => $this->tokenHash($plainToken),
            'last_used_time' => null,
        ]);

        return [
            'token' => $plainToken,
            'row' => $this->formatTokenRow($token),
        ];
    }

    public function changeTokenStatus(int $screenId, int $tokenId, int $status): bool
    {
        $this->read($screenId);
        if (!in_array($status, [1, 2], true)) {
            throw new ApiException('状态值不正确');
        }

        return (bool) $this->tokenRow($screenId, $tokenId)->save(['status' => $status]);
    }

    public function deleteToken(int $screenId, int $tokenId): bool
    {
        $this->read($screenId);
        return (bool) $this->tokenRow($screenId, $tokenId)->delete();
    }

    public function visibleIds(): array
    {
        $query = Screen::field('id');
        if ($this->scope) {
            $query = $this->userDataScope($query);
        }

        return array_map('intval', $query->column('id'));
    }

    private function normalizePayload(array $data, int $ignoreId = 0): array
    {
        unset($data['created_by'], $data['updated_by'], $data['create_time'], $data['update_time'], $data['delete_time']);
        if ($ignoreId > 0) {
            unset($data['layout'], $data['draft_layout']);
        }

        $width = max(320, (int) ($data['width'] ?? 1920));
        $height = max(240, (int) ($data['height'] ?? 1080));
        $data['width'] = $width;
        $data['height'] = $height;
        $data['code'] = trim((string) ($data['code'] ?? '')) ?: $this->generateCode();
        $data['is_public'] = (int) ($data['is_public'] ?? 1);
        $data['status'] = (int) ($data['status'] ?? 2);
        $data['bg_config'] = $this->normalizeBgConfig($data['bg_config'] ?? []);
        if ($ignoreId <= 0) {
            $owner = (int) (getCurrentInfo()['id'] ?? 0);
            $data['draft_layout'] = $this->normalizeLayout(
                is_array($data['draft_layout'] ?? null) ? $data['draft_layout'] : $this->defaultLayout($width, $height),
                $width,
                $height
            );
            $data['layout'] = $this->normalizeLayout(
                is_array($data['layout'] ?? null) ? $data['layout'] : $this->defaultLayout($width, $height),
                $width,
                $height
            );
            $this->assertLayoutTemplates($data['draft_layout'], $owner);
            $this->assertLayoutTemplates($data['layout'], $owner);
        }

        $query = Screen::where('code', $data['code']);
        if ($ignoreId > 0) {
            $query->where('id', '<>', $ignoreId);
        }
        if ($query->whereNull('delete_time')->value('id')) {
            throw new ApiException('访问编码已存在');
        }

        return $data;
    }

    private function normalizeLayout(array $layout, int $width, int $height): array
    {
        $layout['canvas'] = [
            'width' => max(320, (int) ($layout['canvas']['width'] ?? $width)),
            'height' => max(240, (int) ($layout['canvas']['height'] ?? $height)),
        ];
        if (isset($layout['bg_config'])) {
            $layout['bg_config'] = $this->normalizeBgConfig($layout['bg_config']);
        }
        $components = is_array($layout['components'] ?? null) ? $layout['components'] : [];
        $layout['components'] = array_values(array_filter($components, static fn ($item) => is_array($item)));
        return $layout;
    }

    private function defaultLayout(int $width, int $height): array
    {
        return [
            'canvas' => ['width' => $width, 'height' => $height],
            'components' => [],
        ];
    }

    private function assertLayoutTemplates(array $layout, int $owner): void
    {
        (new QueryTemplateLogic())->assertOwnedIds($this->layoutTemplateIds($layout), $owner);
    }

    private function layoutTemplateIds(array $layout): array
    {
        $ids = [];
        $components = is_array($layout['components'] ?? null) ? $layout['components'] : [];
        foreach ($components as $component) {
            if (!is_array($component)) {
                continue;
            }
            $dataset = is_array($component['dataset'] ?? null) ? $component['dataset'] : [];
            $ids[] = $dataset['queryTemplateId'] ?? $dataset['query_template_id'] ?? 0;
        }

        return $ids;
    }

    private function detachLayoutTemplates(array $layout): array
    {
        $components = is_array($layout['components'] ?? null) ? $layout['components'] : [];
        foreach ($components as $index => $component) {
            if (!is_array($component)) {
                continue;
            }
            $dataset = is_array($component['dataset'] ?? null) ? $component['dataset'] : [];
            unset($dataset['queryTemplateId'], $dataset['query_template_id']);
            $component['dataset'] = $dataset;
            $components[$index] = $component;
        }
        $layout['components'] = $components;

        return $layout;
    }

    private function createVersion(Screen $screen, string $source, array $layout): void
    {
        $screenId = (int) $screen->id;
        Screen::where('id', $screenId)->lock(true)->value('id');
        $width = max(320, (int) ($layout['canvas']['width'] ?? $screen->width));
        $height = max(240, (int) ($layout['canvas']['height'] ?? $screen->height));
        $versionNo = ((int) ScreenVersion::where('screen_id', $screenId)->max('version_no')) + 1;

        ScreenVersion::create([
            'screen_id' => $screenId,
            'version_no' => $versionNo,
            'source' => $source,
            'title' => $this->versionTitle($source, $versionNo),
            'width' => $width,
            'height' => $height,
            'bg_config' => $this->normalizeBgConfig($layout['bg_config'] ?? $screen->bg_config),
            'layout' => $this->normalizeLayout($layout, $width, $height),
        ]);
        $this->trimVersions($screenId);
    }

    private function trimVersions(int $screenId): void
    {
        $keepIds = ScreenVersion::where('screen_id', $screenId)
            ->whereNull('delete_time')
            ->order('id', 'desc')
            ->limit(50)
            ->column('id');
        if (!$keepIds) {
            return;
        }

        ScreenVersion::where('screen_id', $screenId)
            ->whereNull('delete_time')
            ->whereNotIn('id', $keepIds)
            ->delete();
    }

    private function versionTitle(string $source, int $versionNo): string
    {
        $label = match ($source) {
            'publish' => '发布快照',
            'restore_before' => '恢复前快照',
            default => '保存快照',
        };

        return $label . ' #' . $versionNo;
    }

    private function normalizeBgConfig(mixed $config): array
    {
        $config = is_array($config) ? $config : [];
        $theme = (string) ($config['theme'] ?? 'midnight');
        $fitMode = (string) ($config['fit_mode'] ?? 'contain');
        $imageFit = (string) ($config['image_fit'] ?? 'cover');

        return array_merge($config, [
            'color' => trim((string) ($config['color'] ?? '')) ?: '#07111f',
            'theme' => in_array($theme, ['midnight', 'teal', 'amber'], true) ? $theme : 'midnight',
            'fit_mode' => in_array($fitMode, ['contain', 'cover', 'stretch'], true) ? $fitMode : 'contain',
            'image' => trim((string) ($config['image'] ?? '')),
            'image_fit' => in_array($imageFit, ['cover', 'contain', 'stretch', 'repeat'], true)
                ? $imageFit
                : 'cover',
        ]);
    }

    private function generateCode(): string
    {
        do {
            $code = 'sb_' . bin2hex(random_bytes(8));
        } while (Screen::where('code', $code)->whereNull('delete_time')->value('id'));

        return $code;
    }

    private function generateToken(): string
    {
        return bin2hex(random_bytes(24));
    }

    private function tokenRow(int $screenId, int $tokenId): ScreenToken
    {
        $token = ScreenToken::where('screen_id', $screenId)
            ->where('id', $tokenId)
            ->whereNull('delete_time')
            ->findOrEmpty();
        if ($token->isEmpty()) {
            throw new ApiException('访问令牌不存在');
        }

        return $token;
    }

    private function normalizeTokenName(mixed $name): string
    {
        $name = trim((string) $name);
        if ($name === '') {
            throw new ApiException('请填写令牌名称');
        }

        return mb_substr($name, 0, 80);
    }

    private function normalizeExpireTime(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);
        if (!$timestamp) {
            throw new ApiException('过期时间不正确');
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function tokenHash(string $token): string
    {
        return hash('sha256', $token);
    }

    private function formatTokenRow(ScreenToken $token): array
    {
        $row = $token->toArray();
        unset($row['token_hash']);
        $row['is_expired'] = $this->isTokenExpired($row['expire_time'] ?? null);

        return $row;
    }

    private function isTokenExpired(mixed $expireTime): bool
    {
        $expireTime = trim((string) $expireTime);
        return $expireTime !== '' && strtotime($expireTime) <= time();
    }
}
