<?php

namespace plugin\saiboard\app\service;

use plugin\saiboard\app\model\Screen;
use support\Redis;
use support\Request;
use support\think\Cache;
use Throwable;

class RuntimeGuard
{
    public function __construct(private readonly RuntimeMetrics $metrics = new RuntimeMetrics())
    {
    }

    public function assertAllowed(Request $request, Screen $screen, string $action): ?array
    {
        $action = $this->normalizeAction($action);
        $screenId = (int) $screen->id;
        $owner = (int) ($screen->created_by ?? 0);
        $ip = $this->clientIp($request);

        $this->metrics->record('request', [
            'action' => $action,
            'screen' => (string) $screenId,
            'owner' => (string) $owner,
        ]);

        if (!$this->enabled()) {
            return null;
        }

        $rules = [
            ['scope' => 'ip', 'identity' => $ip],
            ['scope' => 'screen', 'identity' => (string) $screenId],
            ['scope' => 'owner', 'identity' => (string) max(0, $owner)],
        ];

        foreach ($rules as $rule) {
            $result = $this->hit($action, $rule['scope'], $rule['identity']);
            if ($result['allowed']) {
                continue;
            }

            $this->metrics->record('rate_limited', [
                'action' => $action,
                'scope' => $rule['scope'],
                'screen' => (string) $screenId,
                'owner' => (string) $owner,
            ]);

            return [
                'scope' => $rule['scope'],
                'limit' => $result['limit'],
                'window' => $result['window'],
                'retry_after' => $result['retry_after'],
            ];
        }

        return null;
    }

    private function hit(string $action, string $scope, string $identity): array
    {
        $window = $this->window($scope);
        $limit = $this->limit($scope);
        if ($limit <= 0) {
            return ['allowed' => true, 'limit' => $limit, 'window' => $window, 'retry_after' => 0];
        }

        $bucket = intdiv(time(), $window);
        $key = 'saiboard:runtime:rate:' . $scope . ':' . $action . ':' . md5($identity) . ':' . $bucket;
        $count = $this->redisHit($key, $window);
        if ($count === null) {
            $count = $this->cacheHit($key, $window);
        }

        return [
            'allowed' => $count <= $limit,
            'limit' => $limit,
            'window' => $window,
            'retry_after' => max(1, ($bucket + 1) * $window - time()),
        ];
    }

    private function redisHit(string $key, int $window): ?int
    {
        try {
            $script = <<<'LUA'
local current = redis.call('INCR', KEYS[1])
if current == 1 then
    redis.call('EXPIRE', KEYS[1], ARGV[1])
end
return current
LUA;
            return (int) Redis::eval($script, 1, $key, (string) ($window + 5));
        } catch (Throwable) {
            return null;
        }
    }

    private function cacheHit(string $key, int $window): int
    {
        try {
            $count = Cache::get($key, 0);
            $count = is_numeric($count) ? (int) $count : 0;
            $count++;
            Cache::set($key, $count, $window + 5);
            return $count;
        } catch (Throwable) {
            return 1;
        }
    }

    private function clientIp(Request $request): string
    {
        $ip = $request->getRealIp();
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    private function normalizeAction(string $action): string
    {
        $action = strtolower(trim($action));
        return preg_match('/^[a-z0-9_]{1,30}$/', $action) ? $action : 'runtime';
    }

    private function window(string $scope): int
    {
        return max(1, (int) config("plugin.saiboard.app.runtime.rate_limit.{$scope}.window", 60));
    }

    private function limit(string $scope): int
    {
        return (int) config("plugin.saiboard.app.runtime.rate_limit.{$scope}.limit", 0);
    }

    private function enabled(): bool
    {
        return (bool) config('plugin.saiboard.app.runtime.rate_limit.enabled', true);
    }
}
