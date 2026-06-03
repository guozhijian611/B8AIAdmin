<?php

namespace app\controller;

use OpenB8\WebmanOtelTrace\Support\Trace;
use support\Request;

class IndexController
{
    public function index(Request $request)
    {
        $trace = Trace::span('demo.index.request', function () use ($request) {
            Trace::setAttributes([
                'demo.controller' => self::class,
                'demo.action' => 'index',
                'http.request.method' => $request->method(),
                'url.path' => '/' . ltrim($request->path(), '/'),
            ]);
            Trace::addEvent('demo.index.render');

            return Trace::context();
        }, [
            'demo.source' => 'default-index',
        ]);

        $traceId = htmlspecialchars((string)($trace['trace_id'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $spanId = htmlspecialchars((string)($trace['span_id'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return <<<EOF
<!-- trace-demo trace_id="{$traceId}" span_id="{$spanId}" span_name="demo.index.request" -->
<style>
  * {
    padding: 0;
    margin: 0;
  }
  iframe {
    border: none;
    overflow: scroll;
  }
</style>
<iframe
  src="https://www.workerman.net/wellcome"
  width="100%"
  height="100%"
  allow="clipboard-write"
  sandbox="allow-scripts allow-same-origin allow-popups"
></iframe>
EOF;
    }

    public function view(Request $request)
    {
        return view('index/view', ['name' => 'webman']);
    }

    public function json(Request $request)
    {
        return b8_success(['msg' => 'ok']);
    }

}
