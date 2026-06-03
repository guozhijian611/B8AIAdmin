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
        $this->normalizeLogReaderHtml($response);

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

    private function normalizeLogReaderHtml(Response $response): void
    {
        $contentType = $response->getHeader('Content-Type');
        $contentType = is_array($contentType) ? implode(';', $contentType) : (string) $contentType;

        if ($contentType !== '' && !str_contains(strtolower($contentType), 'text/html')) {
            return;
        }

        $body = $response->rawBody();
        if (!str_contains($body, '/log-reader')) {
            return;
        }

        $body = str_replace(' target="_blank"', '', $body);
        $body = $this->injectProxyPathFixScript($body);

        if (is_string($body)) {
            $response->withoutHeader('Content-Length');
            $response->withBody($body);
        }
    }

    private function injectProxyPathFixScript(string $body): string
    {
        if (str_contains($body, 'data-log-reader-proxy-fix')) {
            return $body;
        }

        $script = <<<'HTML'
<script data-log-reader-proxy-fix>
(function () {
    var match = window.location.pathname.match(/^(.*)\/log-reader(?:\/.*)?$/);
    var prefix = match ? match[1] : '';
    if (!prefix) {
        return;
    }
    document.querySelectorAll('a[href^="/log-reader"]').forEach(function (link) {
        link.setAttribute('href', prefix + link.getAttribute('href'));
        link.removeAttribute('target');
    });
})();
</script>
HTML;

        if (str_contains($body, '</body>')) {
            return str_replace('</body>', $script . "\n</body>", $body);
        }

        return $body . "\n" . $script;
    }
}
