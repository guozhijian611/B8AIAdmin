# SAI AI 插件说明

本文档说明 `saiai` 插件在 B8AIadmin 中的功能边界、配置方式、阿里云实时多模态接入、后台测试台使用和排障流程。

## 功能定位

`saiai` 是框架内置 AI 能力插件，当前包含两类能力：

| 能力 | 入口 | 说明 |
| --- | --- | --- |
| 文字对话 | `server/plugin/saiai/app/service/AiFactory.php` | 通过 `saiai_config` 中的模型配置调用 OpenAI、Gemini、DeepSeek、Generic 等文本模型通道。 |
| 阿里云实时多模态 | `plugin.saiai.saiai_realtime_gateway` 进程 | 后台浏览器连接本地 WebSocket 代理，由代理携带 API Key 连接阿里云 Qwen-Omni-Realtime。支持文本测试、麦克风音频、摄像头抽帧视频和音频输出监测。 |

后台前端位于 `saiadmin-artd/src/views/plugin/saiai`，后端插件位于 `server/plugin/saiai`。

## 核心数据

`saiai_config` 保存模型配置。实时模型复用该表，并通过 `options` 字段保存会话扩展配置。

常用字段：

| 字段 | 说明 |
| --- | --- |
| `name` | 配置名称。 |
| `type` | 平台类型。实时模型固定为 `aliyun_realtime`。 |
| `ai_url` | 上游地址。实时模型使用 `wss://dashscope.aliyuncs.com/api-ws/v1/realtime`。 |
| `ai_key` | 阿里云 DashScope API Key。也可留空并使用环境变量 `DASHSCOPE_API_KEY`。 |
| `model` | 模型名，当前默认 `qwen3-omni-flash-realtime-2025-12-01`。 |
| `options` | JSON 扩展配置，可覆盖默认会话参数。 |
| `status` | 非实时默认配置需要启用；后台实时测试页指定 `config_id` 时允许测试未启用配置。 |

实时模型初始化由迁移 `Database/migrations/20260606000600_add_saiai_aliyun_realtime.php` 完成：

- 为 `saiai_config` 增加 `options` 字段。
- 插入 `aliyun_realtime` 示例配置。
- 增加后台菜单“实时测试”和权限 `saiai:realtime:test`。

## 配置项

### 环境变量

写在 `server/.env`：

```ini
DASHSCOPE_API_KEY=
SAIAI_REALTIME_WS_PORT=8791
SAIAI_REALTIME_WS_COUNT=1
```

| 变量 | 默认值 | 说明 |
| --- | --- | --- |
| `DASHSCOPE_API_KEY` | 空 | 当 `saiai_config.ai_key` 为空时作为兜底 API Key。 |
| `SAIAI_REALTIME_WS_PORT` | `8791` | 本地实时代理 WebSocket 监听端口。 |
| `SAIAI_REALTIME_WS_COUNT` | `1` | 实时代理进程数。 |

### 平台类型

`server/plugin/saiai/config/ai.php` 控制后台可选平台类型。实时模型类型为：

```php
'aliyun_realtime'
```

### 实时模型默认值

默认值集中在 `server/plugin/saiai/app/service/AliyunRealtimeConfig.php`：

| 项 | 默认值 |
| --- | --- |
| 模型 | `qwen3-omni-flash-realtime-2025-12-01` |
| 上游 WebSocket | `wss://dashscope.aliyuncs.com/api-ws/v1/realtime` |
| 音色 | `Cherry` |
| 输出模态 | `["text", "audio"]` |
| 输入音频 | `pcm`，16 kHz PCM 流 |
| 输出音频 | `pcm`，24 kHz PCM 流 |
| VAD | `server_vad`，`threshold=0.5`，`silence_duration_ms=800` |

`options` 可覆盖会话参数，例如：

```json
{
  "modalities": ["text", "audio"],
  "voice": "Cherry",
  "instructions": "你是 B8AIadmin 的实时语音助手，请用准确、简洁、友好的中文回答用户。",
  "turn_detection": {
    "type": "server_vad",
    "threshold": 0.5,
    "silence_duration_ms": 800
  },
  "input_audio_transcription": {
    "model": "qwen3-asr-flash-realtime"
  },
  "smooth_output": true,
  "temperature": 0.9,
  "top_k": 50,
  "repetition_penalty": 1.05,
  "presence_penalty": 0
}
```

## 实时代理

实时代理进程配置在 `server/plugin/saiai/config/process.php`：

```php
'saiai_realtime_gateway' => [
    'handler' => plugin\saiai\app\process\AliyunRealtimeGateway::class,
    'listen' => 'websocket://0.0.0.0:' . env('SAIAI_REALTIME_WS_PORT', 8791),
    'count' => (int) env('SAIAI_REALTIME_WS_COUNT', 1),
    'reloadable' => true,
]
```

连接链路：

1. 后台测试台连接本地代理：`ws://<host>:8791/?token=<admin_token>&config_id=<id>`。
2. 代理校验 SaiAdmin JWT，确认 `plat=saiadmin`。
3. 代理读取 `saiai_config` 中的 `aliyun_realtime` 配置。
4. 代理连接阿里云实时端点，并用 `Authorization: Bearer <api_key>` 放在服务端请求头中。
5. 前端只与本地代理通信，不直接暴露阿里云 API Key。

修改 PHP、进程配置或 `.env` 后，需要重启 Webman：

```bash
cd server
php start.php stop
php start.php start -d
php start.php status | rg "saiai_realtime|8791|exit_status|exit_count"
```

## 后台实时测试台

入口：后台菜单 `SAIAI 管理中心 -> 实时测试`。

前端文件：`saiadmin-artd/src/views/plugin/saiai/realtime/test/index.vue`。

后端配置接口：

```http
GET /app/saiai/admin/config/AiConfig/realtimeTestConfig
```

测试台能力：

| 模块 | 说明 |
| --- | --- |
| 会话配置 | 切换输出模态、音色、VAD、采样参数、系统提示，并发送 `session.update`。 |
| 状态监测 | 展示本地代理、阿里云上游、会话状态、生成状态、延迟、错误数。 |
| 文本测试 | 发送兼容文本事件并观察服务端是否接受。 |
| 音频测试 | 采集麦克风，转换为 16 kHz PCM 后通过 `input_audio_buffer.append` 发送。 |
| 音视频测试 | 同时开启麦克风和摄像头，从视频流抽 JPEG 帧通过 `input_image_buffer.append` 发送。 |
| 音频输出 | 收到 `response.audio.delta` 后流式播放，`audio.done` 后生成完整 WAV 供回放。 |
| 日志监测 | 按发送、接收、错误分类展示事件，并摘要音频和图片 Base64。 |

## 输入与输出流程

### 音频

浏览器采集麦克风后转换为单声道 16 kHz PCM，并持续发送：

```json
{
  "type": "input_audio_buffer.append",
  "audio": "<base64 pcm>"
}
```

### 视频

WebSocket 模式不直接传 RTP 视频流。测试台从摄像头视频流按 FPS 抽取 JPEG 图像帧，并发送：

```json
{
  "type": "input_image_buffer.append",
  "image": "<base64 jpeg>"
}
```

注意：

- 图像帧需要与音频一起形成一轮输入；不要把“纯视频”当成主要测试路径。
- 当前后台测试台的主流程是“开始音视频通话”，会同时开启麦克风和摄像头。
- “发送单帧”只用于调试抽帧效果，必须在麦克风和摄像头都开启后使用。

### 输出

服务端可能返回：

| 事件 | 处理 |
| --- | --- |
| `response.text.delta` / `response.output_text.delta` | 追加到实时文本区。 |
| `response.audio_transcript.delta` / `response.output_audio_transcript.delta` | 追加到实时文本区。 |
| `response.audio.delta` / `response.output_audio.delta` | 流式播放 PCM 音频，并统计音频下行。 |
| `response.audio.done` / `response.output_audio.done` | 生成完整 WAV 回放文件。 |
| `error` / `gateway.error` | 计入错误数并写入事件日志。 |

## VAD 模式

| 模式 | 说明 | 适用场景 |
| --- | --- | --- |
| `server_vad` | 默认模式，服务端根据声音能量判断说话开始和结束，并自动提交本轮输入。 | 实时语音和音视频通话。 |
| `semantic_vad` | 语义断句模式，可减少附和声、短停顿和背景音误触发。 | 仅 `qwen3.5-omni-realtime` 系列支持；使用 `qwen3-omni-flash-realtime-2025-12-01` 时不要默认选择。 |
| `manual` | 关闭服务端 VAD，由前端手动 `input_audio_buffer.commit` 后再 `response.create`。 | 按住说话、语音留言、单轮录音测试。 |

VAD 模式下服务端会自动创建响应，测试台不会手动发送 `response.create`。Manual 模式才启用“Manual 提交本轮”。

## 常见问题

### 页面提示实时连接错误

检查实时代理是否启动：

```bash
cd server
php start.php status | rg "saiai_realtime|8791|exit_status|exit_count"
```

如果进程不存在或退出，重启 Webman，并查看 `server/runtime/logs/workerman.log`。

### 上游连接失败

检查：

- `saiai_config.ai_key` 或 `DASHSCOPE_API_KEY` 是否填写。
- `ai_url` 是否为 `wss://dashscope.aliyuncs.com/api-ws/v1/realtime`。
- `model` 是否为当前地域支持的实时模型。
- API Key 是否属于北京地域或对应地域。

### 视频没有反应

WebSocket 实时视频不是单独的视频流，必须与音频输入一起形成一轮上下文。请使用“音视频 -> 开始音视频通话”，不要只发送单帧。

### 只看到文字，听不到声音

检查会话配置中的 `modalities` 是否包含 `audio`，音色是否有效，浏览器是否允许自动播放音频。测试台收到 `response.audio.delta` 后会流式播放，并在 `audio.done` 后生成完整 WAV。

### Manual 模式没有回复

Manual 模式必须手动提交：

1. 开始麦克风或音视频输入。
2. 停止输入。
3. 点击“Manual 提交本轮”。

VAD 模式不需要点击提交。

## 维护要求

- 新增或修改实时端点时，同步更新：
  - `server/plugin/saiai/config/ai.php`
  - `server/plugin/saiai/app/service/AliyunRealtimeConfig.php`
  - `server/plugin/saiai/app/process/AliyunRealtimeGateway.php`
  - 后台测试台页面
  - `OpenAPI/saiai/openapi.yaml`
  - 本文档
- 后端 PHP 变更至少执行 `php -l`，进程或路由变更需重启 Webman 并检查 `php start.php status`。
- 前端变更至少执行 `pnpm exec vue-tsc --noEmit`，页面级变更建议执行单文件 ESLint。
- 日志和操作记录必须脱敏 `ai_key`、`api_key`、`token`、`secret`、`authorization` 等字段。

## 参考资料

- 阿里云 Qwen-Omni-Realtime 文档：https://help.aliyun.com/zh/model-studio/realtime
- 阿里云实时 API 客户端事件：https://www.alibabacloud.com/help/en/model-studio/client-events
