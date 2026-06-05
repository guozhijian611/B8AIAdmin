<?php

namespace plugin\saiai\app\realtime;

class RealtimeSessionState
{
    public string $sessionId;
    public string $provider;
    public string $model;
    public array $session;
    public array $config;
    public bool $upstreamReady = false;
    public bool $responding = false;
    public int $turnIndex = 0;
    public int $audioChunks = 0;
    public int $imageFrames = 0;
    public ?string $responseId = null;

    public function __construct(string $provider, string $model, array $session, array $config = [])
    {
        $this->sessionId = self::newId('sess');
        $this->provider = $provider;
        $this->model = $model;
        $this->session = $session;
        $this->config = $config;
    }

    public static function newId(string $prefix): string
    {
        return $prefix . '_' . bin2hex(random_bytes(8));
    }
}
