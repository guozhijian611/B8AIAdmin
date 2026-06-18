<?php

namespace plugin\saiboard\app\admin\logic;

use plugin\saiadmin\basic\think\BaseLogic;
use plugin\saiadmin\exception\ApiException;
use plugin\saiboard\app\model\Screen;

class ScreenLogic extends BaseLogic
{
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
        $screen = $this->read($id);
        return (bool) $screen->save([
            'draft_layout' => $this->normalizeLayout($layout, (int) $screen->width, (int) $screen->height),
            'status' => 2,
        ]);
    }

    public function publish(int $id): bool
    {
        $screen = $this->read($id);
        $layout = $screen->draft_layout ?: $this->defaultLayout((int) $screen->width, (int) $screen->height);
        return (bool) $screen->save([
            'layout' => $this->normalizeLayout($layout, (int) $screen->width, (int) $screen->height),
            'status' => 1,
        ]);
    }

    public function copy(int $id): int
    {
        $screen = $this->read($id);
        $data = $screen->toArray();
        unset($data['id'], $data['create_time'], $data['update_time'], $data['delete_time']);
        $data['name'] = $data['name'] . ' 副本';
        $data['code'] = $this->generateCode();
        $data['access_token'] = $this->generateToken();
        $data['status'] = 2;
        $data['layout'] = $this->defaultLayout((int) $data['width'], (int) $data['height']);

        return (int) parent::add($data);
    }

    private function normalizePayload(array $data, int $ignoreId = 0): array
    {
        $width = max(320, (int) ($data['width'] ?? 1920));
        $height = max(240, (int) ($data['height'] ?? 1080));
        $data['width'] = $width;
        $data['height'] = $height;
        $data['code'] = trim((string) ($data['code'] ?? '')) ?: $this->generateCode();
        $data['is_public'] = (int) ($data['is_public'] ?? 1);
        $data['status'] = (int) ($data['status'] ?? 2);
        $data['bg_config'] = $this->normalizeBgConfig($data['bg_config'] ?? []);
        if ($ignoreId <= 0) {
            $data['draft_layout'] = $data['draft_layout'] ?? $this->defaultLayout($width, $height);
            $data['layout'] = $data['layout'] ?? $this->defaultLayout($width, $height);
        }

        $query = Screen::where('code', $data['code']);
        if ($ignoreId > 0) {
            $query->where('id', '<>', $ignoreId);
        }
        if ($query->whereNull('delete_time')->value('id')) {
            throw new ApiException('访问编码已存在');
        }

        if ((int) $data['is_public'] === 2 && trim((string) ($data['access_token'] ?? '')) === '') {
            $data['access_token'] = $this->generateToken();
        }

        return $data;
    }

    private function normalizeLayout(array $layout, int $width, int $height): array
    {
        $layout['canvas'] = [
            'width' => max(320, (int) ($layout['canvas']['width'] ?? $width)),
            'height' => max(240, (int) ($layout['canvas']['height'] ?? $height)),
        ];
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
}
