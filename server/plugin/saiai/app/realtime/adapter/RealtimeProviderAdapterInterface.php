<?php

namespace plugin\saiai\app\realtime\adapter;

use plugin\saiai\app\realtime\RealtimeSessionState;

interface RealtimeProviderAdapterInterface
{
    public function name(): string;

    public function upstreamUrl(array $config): string;

    public function upstreamHeaders(array $config): array;

    public function defaultSession(array $options = []): array;

    public function toProviderEvents(array $event, RealtimeSessionState $state): array;

    public function fromProviderEvent(array $event, RealtimeSessionState $state): array;
}
