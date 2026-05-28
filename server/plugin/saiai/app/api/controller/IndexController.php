<?php

namespace plugin\saiai\app\api\controller;

use plugin\saiai\app\api\logic\ChatGroupLogic;
use plugin\saiai\app\api\logic\ChatLogic;
use plugin\saiai\app\api\logic\IndexLogic;
use plugin\saiai\app\service\AiFactory;
use plugin\saiadmin\basic\BaseController;
use support\Log;
use support\Request;
use support\Response;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Workerman\Protocols\Http\ServerSentEvents;

class IndexController extends BaseController
{
    public function __construct()
    {
        $this->logic = new IndexLogic();
        parent::__construct();
    }

    public function index(Request $request): void
    {
        $connection = $request->connection;

        $connection->send(new Response(200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Credentials' => 'true',
        ], "\r\n"));

        $connection->send(new ServerSentEvents([
            'event' => 'message',
            'data' => json_encode(['type' => 'start'], JSON_UNESCAPED_UNICODE),
        ]));

        $userMessage = $request->input('message', '你好，介绍一下自己');
        $type = $request->input('type', 'deepseek');
        $model = $request->input('model');
        $groupId = $request->input('group_id');
        $userId = $this->adminId;

        if (!$groupId) {
            $groupLogic = new ChatGroupLogic();
            $title = mb_substr($userMessage, 0, 10);
            $group = $groupLogic->createGroup($userId, $title);
            $groupId = $group->id;

            $connection->send(new ServerSentEvents([
                'event' => 'message',
                'data' => json_encode(['type' => 'session_id', 'data' => $groupId], JSON_UNESCAPED_UNICODE),
            ]));
        }

        $chatLogic = new ChatLogic();
        $chatLogic->saveChat($userId, 'user', $userMessage, $type, (string) $groupId);

        $generator = $this->chat($userMessage, $type, $model);
        $fullContent = '';

        foreach ($generator as $chunk) {
            $data = json_decode($chunk, true);
            if (is_array($data) && ($data['type'] ?? '') === 'content') {
                $fullContent .= (string) ($data['data'] ?? '');
            }

            $connection->send(new ServerSentEvents([
                'event' => 'message',
                'data' => $chunk,
            ]));
        }

        if ($fullContent !== '') {
            $chatLogic->saveChat($userId, 'assistant', $fullContent, $type, (string) $groupId);
        }

        $connection->close();
    }

    public function modelList(Request $request): Response
    {
        $list = $this->logic->modelList();
        return $this->success($list);
    }

    public function defaultModel(Request $request): Response
    {
        $data = $this->logic->getDefaultModel();
        return $this->success($data);
    }

    protected function chat(string $userMessage, string $type, ?string $model = null): \Generator
    {
        try {
            $agent = AiFactory::createAgent($type, $model, false);

            $messages = new MessageBag(
                Message::forSystem('你是一个友好的AI助手，请用中文回答用户的问题。'),
                Message::ofUser($userMessage)
            );

            $response = $agent->call($messages, [
                'temperature' => 0.7,
                'stream' => true,
            ]);

            foreach ($response->getContent() as $content) {
                $text = $this->normalizeStreamContent($content);
                if ($text !== '') {
                    yield $this->output('content', $text);
                }
            }

            yield $this->output('done', '');
        } catch (\Throwable $e) {
            Log::error(sprintf(
                '[saiai.chat] type=%s model=%s error=%s',
                $type,
                $model ?: '',
                $e->getMessage()
            ));

            yield $this->output('error', $this->formatChatError($e));
        }
    }

    protected function output(string $type, mixed $data): string
    {
        return json_encode([
            'type' => $type,
            'data' => $data,
        ], JSON_UNESCAPED_UNICODE);
    }

    protected function normalizeStreamContent(mixed $content): string
    {
        if (is_string($content)) {
            return $content;
        }

        if ($content instanceof \Stringable) {
            return (string) $content;
        }

        if (is_object($content) && method_exists($content, 'getText')) {
            return (string) $content->getText();
        }

        return '';
    }

    protected function formatChatError(\Throwable $e): string
    {
        $message = trim($e->getMessage());
        if ($message === '') {
            return 'AI 服务调用失败，请检查模型配置或稍后重试';
        }

        $lowerMessage = strtolower($message);
        if (str_contains($lowerMessage, '404') && str_contains($lowerMessage, 'page not found')) {
            return 'AI 接口地址配置不正确，请检查 ai_url 是否只填写基础地址';
        }

        if (str_contains($lowerMessage, 'no provider found for model')) {
            return '当前模型名称与所选平台不匹配，请检查后台模型配置';
        }

        return $message;
    }
}
