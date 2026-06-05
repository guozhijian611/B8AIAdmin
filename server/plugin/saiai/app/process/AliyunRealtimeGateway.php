<?php

namespace plugin\saiai\app\process;

use plugin\saiai\app\service\AliyunRealtimeConfig;
use support\Log;
use Tinywan\Jwt\JwtToken;
use Workerman\Connection\AsyncTcpConnection;
use Workerman\Connection\TcpConnection;

class AliyunRealtimeGateway
{
    private array $upstreams = [];

    public function onWebSocketConnect(TcpConnection $connection, string $httpBuffer): void
    {
        $query = $this->parseQuery($httpBuffer);
        $token = trim((string) ($query['token'] ?? ''));
        $configId = isset($query['config_id']) ? (int) $query['config_id'] : null;

        try {
            $payload = JwtToken::verify(1, $token);
            $extend = (array) ($payload['extend'] ?? []);
            if (($extend['plat'] ?? '') !== 'saiadmin') {
                throw new \RuntimeException('登录凭证平台不匹配');
            }

            $config = AliyunRealtimeConfig::resolve($configId ?: null);
        } catch (\Throwable $e) {
            $connection->send($this->json('gateway.error', [
                'message' => $e->getMessage() ?: '实时代理鉴权失败',
            ]));
            $connection->close();
            return;
        }

        $connection->realtimeConfig = [
            'id' => $config['id'],
            'name' => $config['name'],
            'model' => $config['model'],
            'options' => $config['options'],
        ];

        $connection->send($this->json('gateway.connected', [
            'config_id' => $config['id'],
            'name' => $config['name'],
            'model' => $config['model'],
            'session' => AliyunRealtimeConfig::defaultSession($config['options']),
        ]));

        $this->connectUpstream($connection, $config);
    }

    public function onMessage(TcpConnection $connection, mixed $data): void
    {
        if (!isset($this->upstreams[$connection->id])) {
            $connection->send($this->json('gateway.error', ['message' => '上游实时连接尚未就绪']));
            return;
        }

        if (is_string($data) && $this->isGatewayPing($data)) {
            $connection->send($this->json('gateway.pong', ['time' => time()]));
            return;
        }

        $this->upstreams[$connection->id]->send($data);
    }

    public function onClose(TcpConnection $connection): void
    {
        if (isset($this->upstreams[$connection->id])) {
            $this->upstreams[$connection->id]->close();
            unset($this->upstreams[$connection->id]);
        }
    }

    private function connectUpstream(TcpConnection $client, array $config): void
    {
        $target = $this->buildWorkermanWsUrl($config['apiUrl']);
        $upstream = new AsyncTcpConnection($target);
        $upstream->transport = str_starts_with((string) $config['apiUrl'], 'wss://') ? 'ssl' : 'tcp';
        $upstream->headers = [
            'Authorization' => 'Bearer ' . $config['apiKey'],
        ];

        $upstream->onConnect = function () use ($client): void {
            $client->send($this->json('gateway.upstream_open', ['time' => time()]));
        };

        $upstream->onMessage = function (AsyncTcpConnection $connection, mixed $data) use ($client): void {
            if ($client->getStatus() === TcpConnection::STATUS_ESTABLISHED) {
                $client->send($data);
            }
        };

        $upstream->onError = function (AsyncTcpConnection $connection, int $code, string $message) use ($client): void {
            Log::error('[saiai.realtime] upstream error code=' . $code . ' message=' . $this->maskSensitive($message));
            if ($client->getStatus() === TcpConnection::STATUS_ESTABLISHED) {
                $client->send($this->json('gateway.upstream_error', [
                    'code' => $code,
                    'message' => $message,
                ]));
            }
        };

        $upstream->onClose = function () use ($client): void {
            if ($client->getStatus() === TcpConnection::STATUS_ESTABLISHED) {
                $client->send($this->json('gateway.upstream_close', ['time' => time()]));
            }
        };

        $this->upstreams[$client->id] = $upstream;
        $upstream->connect();
    }

    private function buildWorkermanWsUrl(string $apiUrl): string
    {
        if (str_starts_with($apiUrl, 'wss://')) {
            return 'ws://' . substr($apiUrl, 6);
        }

        return $apiUrl;
    }

    private function parseQuery(string $httpBuffer): array
    {
        $firstLine = strtok($httpBuffer, "\r\n") ?: '';
        if (!preg_match('#\s([^\s]+)\s#', $firstLine, $matches)) {
            return [];
        }

        parse_str((string) parse_url($matches[1], PHP_URL_QUERY), $query);
        return is_array($query) ? $query : [];
    }

    private function isGatewayPing(string $data): bool
    {
        $payload = json_decode($data, true);
        return is_array($payload) && ($payload['type'] ?? '') === 'gateway.ping';
    }

    private function json(string $type, array $data = []): string
    {
        return json_encode([
            'type' => $type,
            'data' => $data,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function maskSensitive(string $message): string
    {
        return preg_replace('/Bearer\s+[A-Za-z0-9._\-]+/i', 'Bearer ***', $message) ?? $message;
    }
}
