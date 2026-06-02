<?php
// +----------------------------------------------------------------------
// | saiadmin [ saiadmin快速开发框架 ]
// +----------------------------------------------------------------------
// | Author: sai <1430792918@qq.com>
// +----------------------------------------------------------------------
namespace plugin\saiadmin\app\controller\system;

use hg\apidoc\annotation as Apidoc;
use plugin\saiadmin\basic\BaseController;
use plugin\saiadmin\service\Permission;
use support\Request;
use support\Response;
use support\think\Cache;
use Webman\Http\UploadFile;

/**
 * Adminer 数据库管理控制器
 */
#[Apidoc\Group('运维管理')]
#[Apidoc\Title('Adminer 数据库管理')]
class AdminerController extends BaseController
{
    private const TICKET_TTL = 600;
    private const TICKET_COOKIE = 'saiadmin_adminer_ticket';
    private const CACHE_PREFIX = 'saiadmin:adminer:ticket:';

    protected array $noNeedLogin = ['proxy'];

    /**
     * 签发 Adminer 访问票据
     */
    #[Apidoc\Title('签发 Adminer 访问票据')]
    #[Apidoc\Url('/core/adminer/ticket')]
    #[Apidoc\Method('GET')]
    #[Apidoc\Returned('url', type: 'string', desc: 'Adminer 后端代理入口')]
    #[Apidoc\Returned('expires_in', type: 'int', desc: '票据有效秒数')]
    #[Permission('Adminer 数据库管理', 'core:adminer:ticket')]
    public function ticket(Request $request): Response
    {
        $ticket = bin2hex(random_bytes(32));

        Cache::set(self::CACHE_PREFIX . $ticket, [
            'admin_id' => $this->adminId,
            'user_agent' => (string) $request->header('user-agent', ''),
            'issued_at' => time(),
        ], self::TICKET_TTL);

        return $this->success([
            'url' => '/core/adminer/proxy?ticket=' . $ticket,
            'expires_in' => self::TICKET_TTL,
        ]);
    }

    /**
     * Adminer 后端代理入口
     */
    #[Apidoc\Title('Adminer 后端代理入口')]
    #[Apidoc\Url('/core/adminer/proxy')]
    #[Apidoc\Method('GET|POST')]
    #[Apidoc\Query('ticket', type: 'string', require: false, desc: '短时访问票据，首次打开时使用')]
    public function proxy(Request $request): Response
    {
        $ticket = (string) ($request->get('ticket') ?: $request->cookie(self::TICKET_COOKIE, ''));
        if (!$this->validateTicket($ticket, $request)) {
            return response('<h1>403 Forbidden</h1><p>Adminer 访问票据无效或已过期，请从后台菜单重新打开。</p>', 403)
                ->withHeader('Content-Type', 'text/html; charset=utf-8');
        }

        $result = $this->runAdminer($request, $ticket);
        $response = response($result['body'], $result['status'])
            ->withHeaders($this->appendTicketToRedirect($result['headers'], $ticket));

        if ($request->get('ticket')) {
            $response->cookie(self::TICKET_COOKIE, $ticket, self::TICKET_TTL, '/', '', false, true, 'Lax');
        }

        return $response;
    }

    private function validateTicket(string $ticket, Request $request): bool
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

        Cache::set($key, $payload, self::TICKET_TTL);
        return true;
    }

    private function runAdminer(Request $request, string $ticket): array
    {
        $resourcePath = base_path() . '/plugin/saiadmin/resource/adminer';
        $runner = $resourcePath . '/runner.php';
        $metaFile = tempnam(runtime_path(), 'adminer_meta_');

        $get = $request->get();
        unset($get['ticket']);

        $payload = [
            'meta_file' => $metaFile,
            'get' => $get,
            'post' => $request->post(),
            'cookie' => $request->cookie(),
            'files' => $this->normalizeFiles($request->file()),
            'server' => $this->buildServer($request, $get),
            'database_config' => $this->databaseConfig(),
            'adminer_session_id' => $this->adminerSessionId($ticket),
        ];

        $process = proc_open([PHP_BINARY, $runner], [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, $resourcePath);

        if (!is_resource($process)) {
            return [
                'status' => 500,
                'headers' => ['Content-Type' => 'text/html; charset=utf-8'],
                'body' => '<h1>500 Internal Server Error</h1><p>Adminer 子进程启动失败。</p>',
            ];
        }

        fwrite($pipes[0], json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        fclose($pipes[0]);

        $body = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        $meta = is_file($metaFile) ? json_decode((string) file_get_contents($metaFile), true) : [];
        @unlink($metaFile);

        if ($exitCode !== 0 && $body === '') {
            return [
                'status' => 500,
                'headers' => ['Content-Type' => 'text/html; charset=utf-8'],
                'body' => '<h1>500 Internal Server Error</h1><p>' . htmlspecialchars($error ?: 'Adminer 执行失败', ENT_QUOTES, 'UTF-8') . '</p>',
            ];
        }

        return [
            'status' => (int) ($meta['status'] ?? 200),
            'headers' => $this->normalizeHeaders($meta['headers'] ?? []),
            'body' => $body,
        ];
    }

    private function buildServer(Request $request, array $get): array
    {
        $headers = [];
        foreach ($request->header() as $name => $value) {
            $serverName = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
            $headers[$serverName] = is_array($value) ? implode(',', $value) : (string) $value;
        }

        $queryString = http_build_query($get);

        return array_merge($headers, [
            'REQUEST_METHOD' => strtoupper($request->method()),
            'REQUEST_URI' => $request->path() . ($queryString !== '' ? '?' . $queryString : ''),
            'QUERY_STRING' => $queryString,
            'SCRIPT_NAME' => $request->path(),
            'PHP_SELF' => $request->path(),
            'SERVER_NAME' => $request->host(true) ?: 'localhost',
            'HTTP_HOST' => $request->host(true) ?: 'localhost',
            'REMOTE_ADDR' => $request->getRealIp(),
            'HTTP_ACCEPT_LANGUAGE' => 'zh-CN,zh;q=0.9,en;q=0.8',
        ]);
    }

    private function databaseConfig(): array
    {
        $config = config('database.connections.mysql', []);
        $host = (string) ($config['host'] ?? env('DB_HOST', '127.0.0.1'));
        $port = (string) ($config['port'] ?? env('DB_PORT', '3306'));

        return [
            'server' => $port !== '' && $port !== '3306' ? $host . ':' . $port : $host,
            'username' => (string) ($config['username'] ?? env('DB_USER', 'root')),
            'password' => (string) ($config['password'] ?? env('DB_PASSWORD', '')),
            'database' => (string) ($config['database'] ?? env('DB_NAME', '')),
        ];
    }

    private function adminerSessionId(string $ticket): string
    {
        return 'adm' . substr(hash('sha256', $ticket), 0, 29);
    }

    private function appendTicketToRedirect(array $headers, string $ticket): array
    {
        foreach ($headers as $name => $value) {
            if (strtolower((string) $name) !== 'location') {
                continue;
            }

            if (is_array($value)) {
                $headers[$name] = array_map(fn (string $location): string => $this->appendTicketToLocation($location, $ticket), $value);
            } else {
                $headers[$name] = $this->appendTicketToLocation((string) $value, $ticket);
            }
        }

        return $headers;
    }

    private function appendTicketToLocation(string $location, string $ticket): string
    {
        if ($location === '' || str_contains($location, 'ticket=')) {
            return $location;
        }

        return $location . (str_contains($location, '?') ? '&' : '?') . 'ticket=' . rawurlencode($ticket);
    }

    private function normalizeFiles(array|UploadFile|null $files): array
    {
        if ($files instanceof UploadFile) {
            return [
                'name' => $files->getUploadName(),
                'type' => $files->getUploadMimeType(),
                'tmp_name' => $files->getPathname(),
                'error' => $files->getUploadErrorCode(),
                'size' => $files->getSize(),
            ];
        }

        if (!is_array($files)) {
            return [];
        }

        $normalized = [];
        foreach ($files as $key => $file) {
            $normalized[$key] = $this->normalizeFiles($file);
        }
        return $normalized;
    }

    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $header) {
            if (!is_string($header) || !str_contains($header, ':')) {
                continue;
            }

            [$name, $value] = array_map('trim', explode(':', $header, 2));
            $lower = strtolower($name);
            if (in_array($lower, ['content-length', 'connection', 'x-frame-options'], true)) {
                continue;
            }

            if ($lower === 'set-cookie') {
                $value = preg_replace('/;\s*path=[^;]*/i', '; Path=/', $value);
                if (!preg_match('/;\s*path=/i', $value)) {
                    $value .= '; Path=/';
                }
                $normalized['Set-Cookie'] ??= [];
                $normalized['Set-Cookie'][] = $value;
                continue;
            }

            if (isset($normalized[$name])) {
                $normalized[$name] = (array) $normalized[$name];
                $normalized[$name][] = $value;
            } else {
                $normalized[$name] = $value;
            }
        }

        return $normalized ?: ['Content-Type' => 'text/html; charset=utf-8'];
    }
}
