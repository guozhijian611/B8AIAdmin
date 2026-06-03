<?php

declare(strict_types=1);

namespace plugin\saiadmin\app\service;

use support\think\Cache;
use Webman\Http\Request;

class LogReaderTicketService
{
    public const TTL = 600;
    public const COOKIE = 'saiadmin_log_reader_ticket';

    private const CACHE_PREFIX = 'saiadmin:log-reader:ticket:';

    public static function issue(Request $request, int $adminId): array
    {
        $ticket = bin2hex(random_bytes(32));

        Cache::set(self::CACHE_PREFIX . $ticket, [
            'admin_id' => $adminId,
            'user_agent' => (string) $request->header('user-agent', ''),
            'issued_at' => time(),
        ], self::TTL);

        return [
            'url' => '/log-reader?ticket=' . $ticket,
            'expires_in' => self::TTL,
        ];
    }

    public static function ticketFromRequest(Request $request): string
    {
        return (string) ($request->get('ticket') ?: $request->cookie(self::COOKIE, ''));
    }

    public static function validate(string $ticket, Request $request): bool
    {
        if ($ticket === '') {
            return false;
        }

        $key = self::CACHE_PREFIX . $ticket;
        $payload = Cache::get($key);
        if (!$payload || !is_array($payload)) {
            return false;
        }

        $userAgent = (string) $request->header('user-agent', '');
        if (($payload['user_agent'] ?? '') !== $userAgent) {
            Cache::delete($key);
            return false;
        }

        Cache::set($key, $payload, self::TTL);
        return true;
    }

    public static function enabled(): bool
    {
        $enabled = env('LOG_READER_ENABLED', null);
        if ($enabled === null || $enabled === '') {
            return (bool) config('app.debug', false);
        }

        return in_array(strtolower((string) $enabled), ['1', 'true', 'on', 'yes'], true);
    }
}
