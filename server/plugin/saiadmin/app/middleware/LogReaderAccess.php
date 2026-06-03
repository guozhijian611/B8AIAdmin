<?php

declare(strict_types=1);

namespace plugin\saiadmin\app\middleware;

use plugin\saiadmin\app\service\LogReaderTicketService;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

class LogReaderAccess implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        if (!LogReaderTicketService::enabled()) {
            return $this->forbidden('日志查看器未启用。');
        }

        $ticket = LogReaderTicketService::ticketFromRequest($request);
        if (!LogReaderTicketService::validate($ticket, $request)) {
            return $this->forbidden('日志查看器访问票据无效或已过期，请从后台菜单重新打开。');
        }

        $response = $handler($request);
        if ($request->get('ticket')) {
            $response->cookie(LogReaderTicketService::COOKIE, $ticket, LogReaderTicketService::TTL, '/', '', false, true, 'Lax');
        }

        return $response;
    }

    private function forbidden(string $message): Response
    {
        return response('<h1>403 Forbidden</h1><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>', 403)
            ->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
