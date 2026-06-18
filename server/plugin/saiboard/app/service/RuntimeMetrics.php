<?php

namespace plugin\saiboard\app\service;

use support\think\Cache;
use Throwable;

class RuntimeMetrics
{
    private const EVENTS = [
        'request',
        'rate_limited',
        'cache_hit',
        'cache_miss',
        'cache_store',
        'stale_hit',
        'source_success',
        'source_fail',
        'redis_lock_acquired',
        'file_lock_acquired',
        'lock_wait',
        'lock_timeout',
    ];

    public function record(string $event, array $tags = [], int $step = 1): void
    {
        if (!$this->enabled() || $step <= 0) {
            return;
        }

        $event = $this->normalizeEvent($event);
        if ($event === '') {
            return;
        }

        $bucket = $this->bucket();
        $ttl = $this->ttl();
        $tags = $this->normalizeTags($tags);

        $this->increment($this->key($bucket, $event, []), $step, $ttl);
        foreach ($this->tagDimensions($tags) as $dimension) {
            $this->increment($this->key($bucket, $event, $dimension), $step, $ttl);
        }
    }

    public function snapshot(int $screenId = 0): array
    {
        $window = $this->window();
        $bucket = $this->bucket();
        $buckets = [$bucket, $bucket - $window];
        $result = [
            'enabled' => $this->enabled(),
            'window' => $window * 2,
            'bucket_window' => $window,
            'current_bucket' => $bucket,
            'totals' => [],
            'screen' => $screenId > 0 ? [] : null,
        ];

        foreach (self::EVENTS as $event) {
            $result['totals'][$event] = $this->sumEvent($event, [], $buckets);
            if ($screenId > 0) {
                $result['screen'][$event] = $this->sumEvent($event, ['screen' => (string) $screenId], $buckets);
            }
        }

        $requests = max(0, (int) $result['totals']['request']);
        $cacheHits = max(0, (int) $result['totals']['cache_hit']);
        $cacheMisses = max(0, (int) $result['totals']['cache_miss']);
        $result['cache_hit_rate'] = ($cacheHits + $cacheMisses) > 0
            ? round($cacheHits / ($cacheHits + $cacheMisses), 4)
            : 0.0;
        $result['rate_limited_rate'] = $requests > 0
            ? round(((int) $result['totals']['rate_limited']) / $requests, 4)
            : 0.0;

        return $result;
    }

    private function tagDimensions(array $tags): array
    {
        if ($tags === []) {
            return [];
        }

        $dimensions = [$tags];
        foreach ($tags as $key => $value) {
            $dimensions[] = [$key => $value];
        }

        return $dimensions;
    }

    private function sumEvent(string $event, array $tags, array $buckets): int
    {
        $total = 0;
        foreach ($buckets as $bucket) {
            $value = Cache::get($this->key($bucket, $event, $tags), 0);
            $total += is_numeric($value) ? (int) $value : 0;
        }

        return $total;
    }

    private function increment(string $key, int $step, int $ttl): void
    {
        try {
            $value = Cache::get($key, 0);
            $value = is_numeric($value) ? (int) $value : 0;
            Cache::set($key, $value + $step, $ttl);
        } catch (Throwable) {
            // 指标写入不能影响公开运行时主链路。
        }
    }

    private function key(int $bucket, string $event, array $tags): string
    {
        return 'saiboard:runtime:metrics:' . $bucket . ':' . $event . ':' . md5(json_encode($tags, JSON_UNESCAPED_UNICODE));
    }

    private function normalizeEvent(string $event): string
    {
        $event = strtolower(trim($event));
        return preg_match('/^[a-z0-9_]{1,40}$/', $event) ? $event : '';
    }

    private function normalizeTags(array $tags): array
    {
        $result = [];
        foreach ($tags as $key => $value) {
            $key = strtolower(trim((string) $key));
            if (!preg_match('/^[a-z0-9_]{1,30}$/', $key)) {
                continue;
            }
            $result[$key] = mb_substr(preg_replace('/[^A-Za-z0-9_.:-]/', '_', (string) $value) ?: '', 0, 80);
        }
        ksort($result);

        return $result;
    }

    private function bucket(): int
    {
        $window = $this->window();
        return intdiv(time(), $window) * $window;
    }

    private function ttl(): int
    {
        return max(120, $this->window() * 3);
    }

    private function window(): int
    {
        return max(60, (int) config('plugin.saiboard.app.runtime.metrics.window', 300));
    }

    private function enabled(): bool
    {
        return (bool) config('plugin.saiboard.app.runtime.metrics.enabled', true);
    }
}
