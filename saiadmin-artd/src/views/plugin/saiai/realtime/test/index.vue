<template>
  <div class="realtime-test">
    <section class="toolbar-band">
      <div class="toolbar-inner">
        <div class="title-block">
          <h2>阿里云实时多模态调试台</h2>
          <p>{{ statusText }}</p>
        </div>
        <div class="actions">
          <el-select
            v-model="selectedConfigId"
            placeholder="选择实时配置"
            class="config-select"
            :disabled="connected"
          >
            <el-option
              v-for="item in realtimeConfigs"
              :key="item.id"
              :label="`${item.name} / ${item.model}`"
              :value="item.id"
            />
          </el-select>
          <el-switch
            v-model="autoSessionUpdate"
            active-text="自动配置会话"
            inactive-text="手动配置"
            :disabled="connected"
          />
          <el-button :disabled="connected || !selectedConfigId" type="primary" @click="connect">
            连接
          </el-button>
          <el-button :disabled="!connected" @click="disconnect">断开</el-button>
        </div>
      </div>
    </section>

    <section class="status-strip">
      <div v-for="item in statusCards" :key="item.label" class="metric-tile">
        <span>{{ item.label }}</span>
        <strong>{{ item.value }}</strong>
        <small>{{ item.extra }}</small>
      </div>
    </section>

    <section class="control-grid">
      <div class="panel session-panel">
        <div class="panel-header">
          <h3>会话配置</h3>
          <el-tag :type="sessionUpdated ? 'success' : 'info'">
            {{ sessionUpdated ? '已生效' : '待配置' }}
          </el-tag>
        </div>

        <el-form label-position="top">
          <div class="form-grid">
            <el-form-item label="模型">
              <el-input v-model="modelName" readonly />
            </el-form-item>
            <el-form-item label="输出模态">
              <el-checkbox-group v-model="modalities">
                <el-checkbox value="text">文本</el-checkbox>
                <el-checkbox value="audio">音频</el-checkbox>
              </el-checkbox-group>
            </el-form-item>
            <el-form-item label="音色">
              <el-select v-model="voice" :disabled="!modalities.includes('audio')">
                <el-option
                  v-for="item in voiceOptions"
                  :key="item.value"
                  :label="item.label"
                  :value="item.value"
                />
              </el-select>
            </el-form-item>
            <el-form-item label="VAD 模式">
              <el-radio-group v-model="vadMode">
                <el-radio-button label="server_vad">Server VAD</el-radio-button>
                <el-radio-button label="semantic_vad">Semantic VAD</el-radio-button>
                <el-radio-button label="manual">Manual</el-radio-button>
              </el-radio-group>
            </el-form-item>
          </div>

          <div class="vad-grid" v-if="vadMode !== 'manual'">
            <el-form-item label="VAD 阈值">
              <el-slider v-model="vadThreshold" :min="-1" :max="1" :step="0.05" show-input />
            </el-form-item>
            <el-form-item label="静音判定 ms">
              <el-slider
                v-model="vadSilenceDuration"
                :min="200"
                :max="6000"
                :step="100"
                show-input
              />
            </el-form-item>
          </div>

          <div class="form-grid">
            <el-form-item label="温度">
              <el-input-number v-model="temperature" :min="0" :max="1.99" :step="0.1" />
            </el-form-item>
            <el-form-item label="Top K">
              <el-input-number v-model="topK" :min="0" :max="100" :step="1" />
            </el-form-item>
            <el-form-item label="重复惩罚">
              <el-input-number v-model="repetitionPenalty" :min="0.1" :max="2" :step="0.05" />
            </el-form-item>
            <el-form-item label="存在惩罚">
              <el-input-number v-model="presencePenalty" :min="-2" :max="2" :step="0.1" />
            </el-form-item>
          </div>

          <el-form-item label="系统提示">
            <el-input v-model="instructions" type="textarea" :rows="3" />
          </el-form-item>
          <el-form-item label="会话 JSON">
            <el-input :model-value="sessionPreview" type="textarea" :rows="8" readonly />
          </el-form-item>
        </el-form>

        <div class="button-row">
          <el-button :disabled="!upstreamReady" type="primary" @click="sendSessionUpdate">
            配置会话
          </el-button>
          <el-button :disabled="!upstreamReady" @click="sendResponseCreate">生成回复</el-button>
          <el-button :disabled="!upstreamReady" @click="cancelResponse">取消回复</el-button>
        </div>
      </div>

      <div class="panel monitor-panel">
        <div class="panel-header">
          <h3>状态监测</h3>
          <el-tag :type="responseActive ? 'warning' : 'info'">
            {{ responseActive ? '生成中' : '空闲' }}
          </el-tag>
        </div>

        <div class="health-list">
          <div class="health-row">
            <span>本地代理</span>
            <el-tag :type="connected ? 'success' : 'info'">
              {{ connected ? '已连接' : '未连接' }}
            </el-tag>
          </div>
          <div class="health-row">
            <span>阿里云上游</span>
            <el-tag :type="upstreamReady ? 'success' : 'info'">
              {{ upstreamReady ? '已就绪' : '等待中' }}
            </el-tag>
          </div>
          <div class="health-row">
            <span>VAD</span>
            <strong>{{ vadLabel }}</strong>
          </div>
          <div class="health-row">
            <span>延迟</span>
            <strong>{{ latencyText }}</strong>
          </div>
        </div>

        <div class="level-block">
          <div class="level-title">
            <span>输入音量</span>
            <strong>{{ Math.round(inputVolume * 100) }}%</strong>
          </div>
          <el-progress :percentage="Math.round(inputVolume * 100)" :show-text="false" />
        </div>
        <div class="level-block">
          <div class="level-title">
            <span>输出音量</span>
            <strong>{{ Math.round(outputVolume * 100) }}%</strong>
          </div>
          <el-progress
            :percentage="Math.round(outputVolume * 100)"
            :show-text="false"
            status="success"
          />
        </div>

        <div class="traffic-grid">
          <div>
            <span>音频片段</span>
            <strong>{{ counters.sentAudioChunks }} / {{ counters.receivedAudioChunks }}</strong>
          </div>
          <div>
            <span>视频帧</span>
            <strong>{{ counters.sentImageFrames }}</strong>
          </div>
          <div>
            <span>服务端事件</span>
            <strong>{{ counters.serverEvents }}</strong>
          </div>
          <div>
            <span>错误</span>
            <strong>{{ counters.errors }}</strong>
          </div>
        </div>

        <audio ref="outputAudioRef" class="audio-player" :src="outputAudioUrl" controls />
      </div>
    </section>

    <section class="input-grid">
      <div class="panel input-panel">
        <div class="panel-header">
          <h3>输入测试</h3>
          <el-tag>{{ activeInputName }}</el-tag>
        </div>

        <el-tabs v-model="activeInput">
          <el-tab-pane label="文本" name="text">
            <el-input
              v-model="textPrompt"
              type="textarea"
              :rows="8"
              placeholder="输入一段文本，发送为兼容文本事件"
            />
            <div class="button-row">
              <el-button
                :disabled="!upstreamReady || !textPrompt.trim()"
                type="primary"
                @click="sendTextPrompt"
              >
                发送文本并生成
              </el-button>
            </div>
          </el-tab-pane>

          <el-tab-pane label="音频" name="audio">
            <div class="media-actions">
              <el-button
                :disabled="!upstreamReady || recording"
                type="danger"
                @click="startRecording"
              >
                开始麦克风
              </el-button>
              <el-button :disabled="!recording" @click="stopRecording">停止麦克风</el-button>
              <el-button
                :disabled="!upstreamReady || !isManualMode"
                @click="commitCurrentTurn(true)"
              >
                Manual 提交本轮
              </el-button>
              <el-button :disabled="!upstreamReady" @click="clearAudioBuffer">清空缓冲</el-button>
            </div>
            <div class="audio-monitor">
              <div>
                <span>采样率</span>
                <strong>{{ audioSampleRate || '-' }}</strong>
              </div>
              <div>
                <span>发送时长</span>
                <strong>{{ sentAudioSeconds.toFixed(1) }}s</strong>
              </div>
              <div>
                <span>发送大小</span>
                <strong>{{ formatBytes(counters.sentAudioBytes) }}</strong>
              </div>
            </div>
          </el-tab-pane>

          <el-tab-pane label="音视频" name="video">
            <div class="video-layout">
              <video ref="cameraVideoRef" class="camera-preview" autoplay muted playsinline />
              <div class="frame-preview">
                <img v-if="lastFrameUrl" :src="lastFrameUrl" alt="latest frame" />
                <span v-else>等待视频帧</span>
              </div>
            </div>
            <div class="video-controls">
              <el-form label-position="top">
                <div class="form-grid">
                  <el-form-item label="抽帧频率 FPS">
                    <el-input-number v-model="frameRate" :min="0.2" :max="2" :step="0.2" />
                  </el-form-item>
                  <el-form-item label="帧宽度">
                    <el-input-number v-model="frameWidth" :min="320" :max="1280" :step="80" />
                  </el-form-item>
                  <el-form-item label="JPEG 质量">
                    <el-slider v-model="imageQuality" :min="0.4" :max="0.9" :step="0.05" />
                  </el-form-item>
                </div>
              </el-form>
              <div class="media-actions">
                <el-button
                  :disabled="!upstreamReady || recording || videoStreaming"
                  type="primary"
                  @click="startAvCall"
                >
                  开始音视频通话
                </el-button>
                <el-button :disabled="!recording && !videoStreaming" @click="stopAvCall">
                  停止音视频
                </el-button>
                <el-button :disabled="!recording || !videoStreaming" @click="captureVideoFrame">
                  发送单帧
                </el-button>
              </div>
            </div>
            <canvas ref="frameCanvasRef" class="hidden-canvas" />
          </el-tab-pane>
        </el-tabs>
      </div>

      <div class="panel output-panel">
        <div class="panel-header">
          <h3>输出日志</h3>
          <div class="button-row">
            <el-button size="small" @click="assistantText = ''">清空文本</el-button>
            <el-button size="small" @click="events = []">清空日志</el-button>
          </div>
        </div>
        <div class="transcript">{{ assistantText || '等待模型返回文本...' }}</div>
        <div class="events">
          <div v-for="(item, index) in events" :key="index" class="event-line" :class="item.level">
            <span>{{ item.time }}</span>
            <strong>{{ item.type }}</strong>
            <code>{{ item.payload }}</code>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
  import { ElMessage } from 'element-plus'
  import configApi from '../../api/config/config'
  import { useUserStore } from '@/store/modules/user'

  type VadMode = 'server_vad' | 'semantic_vad' | 'manual'
  type EventLevel = 'info' | 'send' | 'recv' | 'warn' | 'error'

  interface RealtimeConfig {
    id: number
    name: string
    model: string
    type: string
  }

  interface EventItem {
    time: string
    type: string
    payload: string
    level: EventLevel
  }

  const userStore = useUserStore()
  const realtimeConfigs = ref<RealtimeConfig[]>([])
  const selectedConfigId = ref<number>()
  const wsUrl = ref('')
  const modelName = ref('qwen3-omni-flash-realtime-2025-12-01')
  const connected = ref(false)
  const upstreamReady = ref(false)
  const sessionUpdated = ref(false)
  const responseActive = ref(false)
  const recording = ref(false)
  const videoStreaming = ref(false)
  const autoSessionUpdate = ref(true)
  const activeInput = ref('audio')

  const voice = ref('Cherry')
  const modalities = ref(['text', 'audio'])
  const instructions = ref(
    '你是 B8AIadmin 的实时语音助手，请基于用户的文本、声音和画面进行准确、简洁、友好的中文回复。'
  )
  const vadMode = ref<VadMode>('server_vad')
  const vadThreshold = ref(0.5)
  const vadSilenceDuration = ref(800)
  const temperature = ref(0.9)
  const topK = ref(50)
  const repetitionPenalty = ref(1.05)
  const presencePenalty = ref(0)

  const textPrompt = ref('')
  const assistantText = ref('')
  const events = ref<EventItem[]>([])
  const inputVolume = ref(0)
  const outputVolume = ref(0)
  const outputAudioUrl = ref('')
  const audioSampleRate = ref(0)
  const sentAudioSeconds = ref(0)
  const elapsedSeconds = ref(0)
  const latencyMs = ref<number>()
  const lastPingAt = ref<number>()
  const lastFrameUrl = ref('')

  const frameRate = ref(1)
  const frameWidth = ref(640)
  const imageQuality = ref(0.72)

  const cameraVideoRef = ref<HTMLVideoElement>()
  const frameCanvasRef = ref<HTMLCanvasElement>()
  const outputAudioRef = ref<HTMLAudioElement>()

  const counters = reactive({
    sentAudioChunks: 0,
    sentAudioBytes: 0,
    sentImageFrames: 0,
    sentImageBytes: 0,
    receivedAudioChunks: 0,
    receivedAudioBytes: 0,
    serverEvents: 0,
    errors: 0,
    textChars: 0
  })

  const voiceOptions = [
    { label: 'Cherry（Qwen3 默认）', value: 'Cherry' },
    { label: 'Ethan', value: 'Ethan' },
    { label: 'Chelsie', value: 'Chelsie' },
    { label: 'Tina', value: 'Tina' }
  ]

  let socket: WebSocket | null = null
  let audioContext: AudioContext | null = null
  let playbackContext: AudioContext | null = null
  let audioStream: MediaStream | null = null
  let sourceNode: MediaStreamAudioSourceNode | null = null
  let processorNode: ScriptProcessorNode | null = null
  let videoStream: MediaStream | null = null
  let frameTimer: number | undefined
  let elapsedTimer: number | undefined
  let pingTimer: number | undefined
  let connectionStartedAt = 0
  let outputSampleChunks: Int16Array[] = []
  let playbackCursor = 0
  let pendingResponseAfterCommit = false

  const isManualMode = computed(() => vadMode.value === 'manual')
  const vadLabel = computed(() => (isManualMode.value ? 'Manual' : vadMode.value))
  const latencyText = computed(() =>
    latencyMs.value === undefined ? '-' : `${latencyMs.value} ms`
  )
  const activeInputName = computed(() => {
    if (activeInput.value === 'text') return '文本事件'
    if (activeInput.value === 'video') return '音视频通话'
    return '麦克风 PCM'
  })

  const statusText = computed(() => {
    if (recording.value && videoStreaming.value) return '正在进行音视频实时输入'
    if (recording.value) return '正在发送麦克风 PCM 音频'
    if (videoStreaming.value) return '正在发送视频 JPEG 抽帧，等待音频输入'
    if (upstreamReady.value) return '已连接阿里云实时端点'
    if (connected.value) return '已连接本地代理，等待上游就绪'
    return '请选择 aliyun_realtime 配置后连接'
  })

  const statusCards = computed(() => [
    {
      label: '连接时长',
      value: formatDuration(elapsedSeconds.value),
      extra: upstreamReady.value ? '上游在线' : '未就绪'
    },
    {
      label: '音频上行',
      value: `${counters.sentAudioChunks}`,
      extra: `${sentAudioSeconds.value.toFixed(1)}s / ${formatBytes(counters.sentAudioBytes)}`
    },
    {
      label: '视频上行',
      value: `${counters.sentImageFrames}`,
      extra: formatBytes(counters.sentImageBytes)
    },
    {
      label: '音频下行',
      value: `${counters.receivedAudioChunks}`,
      extra: formatBytes(counters.receivedAudioBytes)
    },
    {
      label: '文本输出',
      value: `${counters.textChars}`,
      extra: 'chars'
    }
  ])

  const sessionPreview = computed(() => JSON.stringify(buildSession(), null, 2))

  onMounted(async () => {
    await loadPage()
  })

  onBeforeUnmount(() => {
    disconnect()
    revokeOutputAudioUrl()
    revokeLastFrameUrl()
  })

  async function loadPage() {
    const [testConfig, configPage] = await Promise.all([
      configApi.realtimeTestConfig(),
      configApi.list({ page: 1, limit: 100, type: 'aliyun_realtime' })
    ])

    wsUrl.value = testConfig.ws_url || ''
    modelName.value = testConfig.default_model || modelName.value
    applySessionDefaults(testConfig.default_session || {})

    const list = Array.isArray((configPage as any).data)
      ? (configPage as any).data
      : Array.isArray(configPage)
        ? configPage
        : []
    realtimeConfigs.value = list.filter((item: RealtimeConfig) => item.type === 'aliyun_realtime')
    selectedConfigId.value = realtimeConfigs.value[0]?.id
  }

  function connect() {
    if (!wsUrl.value || !selectedConfigId.value) return
    const token = userStore.accessToken
    if (!token) {
      ElMessage.error('登录状态已失效，请重新登录')
      return
    }

    disconnect()
    resetRuntimeState()

    const url = new URL(wsUrl.value)
    url.searchParams.set('token', token)
    url.searchParams.set('config_id', String(selectedConfigId.value))
    socket = new WebSocket(url.toString())

    socket.onopen = () => {
      connected.value = true
      connectionStartedAt = Date.now()
      startTimers()
      addEvent('client.open', '本地代理连接成功', 'info')
    }
    socket.onclose = () => {
      connected.value = false
      upstreamReady.value = false
      responseActive.value = false
      stopTimers()
      stopAudioNodes()
      stopVideo()
      addEvent('client.close', '连接已关闭', 'warn')
    }
    socket.onerror = () => {
      counters.errors += 1
      ElMessage.error('实时连接发生错误')
      addEvent('client.error', 'WebSocket error', 'error')
    }
    socket.onmessage = (event) => {
      handleServerMessage(String(event.data))
    }
  }

  function disconnect() {
    stopAudioNodes()
    stopVideo()
    stopTimers()
    resetPlaybackQueue()
    if (socket) {
      socket.close()
      socket = null
    }
    connected.value = false
    upstreamReady.value = false
    sessionUpdated.value = false
    responseActive.value = false
  }

  function sendSessionUpdate() {
    sendJson({
      type: 'session.update',
      event_id: createEventId('session'),
      session: buildSession()
    })
  }

  function sendResponseCreate() {
    if (!isManualMode.value) {
      addEvent('client.response_skip', 'VAD 模式由服务端自动触发响应', 'warn')
      return
    }

    sendJson({
      type: 'response.create',
      event_id: createEventId('response')
    })
  }

  function cancelResponse() {
    sendJson({
      type: 'response.cancel',
      event_id: createEventId('cancel')
    })
  }

  function sendTextPrompt() {
    const text = textPrompt.value.trim()
    if (!text) return

    sendJson({
      type: 'conversation.item.create',
      event_id: createEventId('text'),
      item: {
        type: 'message',
        role: 'user',
        content: [
          {
            type: 'input_text',
            text
          }
        ]
      }
    })
    sendJson({
      type: 'response.create',
      event_id: createEventId('text_response')
    })
  }

  async function startRecording() {
    if (!socket || socket.readyState !== WebSocket.OPEN) return
    if (recording.value) return
    assistantText.value = ''
    outputSampleChunks = []
    resetPlaybackQueue()
    audioStream = await navigator.mediaDevices.getUserMedia({ audio: true })
    audioContext = new AudioContext()
    audioSampleRate.value = audioContext.sampleRate
    sourceNode = audioContext.createMediaStreamSource(audioStream)
    processorNode = audioContext.createScriptProcessor(4096, 1, 1)

    processorNode.onaudioprocess = (event) => {
      const input = event.inputBuffer.getChannelData(0)
      inputVolume.value = calculateVolume(input)
      const pcm = floatTo16BitPcm(resample(input, audioContext?.sampleRate || 48000, 16000))
      if (pcm.byteLength > 0) {
        sendAudioPcm(pcm.buffer)
      }
    }

    sourceNode.connect(processorNode)
    processorNode.connect(audioContext.destination)
    recording.value = true
    addEvent('client.recording_start', '开始麦克风采集', 'info')
  }

  function stopRecording() {
    stopAudioNodes()
    addEvent('client.recording_stop', '麦克风已停止', 'info')
  }

  async function startVideo() {
    if (!socket || socket.readyState !== WebSocket.OPEN) return
    if (videoStreaming.value) return
    videoStream = await navigator.mediaDevices.getUserMedia({
      video: {
        width: { ideal: 1280 },
        height: { ideal: 720 },
        frameRate: { ideal: 30 }
      },
      audio: false
    })

    if (cameraVideoRef.value) {
      cameraVideoRef.value.srcObject = videoStream
      await cameraVideoRef.value.play()
    }

    videoStreaming.value = true
    addEvent('client.video_start', '开始摄像头抽帧', 'info')
    await captureVideoFrame()
    frameTimer = window.setInterval(captureVideoFrame, Math.round(1000 / frameRate.value))
  }

  function stopVideo() {
    if (frameTimer) {
      window.clearInterval(frameTimer)
      frameTimer = undefined
    }
    if (videoStream) {
      videoStream.getTracks().forEach((track) => track.stop())
      videoStream = null
    }
    if (cameraVideoRef.value) {
      cameraVideoRef.value.srcObject = null
    }
    videoStreaming.value = false
  }

  async function startAvCall() {
    activeInput.value = 'video'
    await startRecording()
    await startVideo()
    addEvent('client.av_start', '已同步开启麦克风与摄像头', 'info')
  }

  function stopAvCall() {
    stopAudioNodes()
    stopVideo()
    if (isManualMode.value) {
      commitCurrentTurn(true)
    }
    addEvent('client.av_stop', '音视频输入已停止', 'info')
  }

  function commitCurrentTurn(createResponse = false) {
    if (!isManualMode.value) {
      addEvent('client.commit_skip', 'VAD 模式由服务端自动提交音频缓冲', 'warn')
      return
    }

    pendingResponseAfterCommit = createResponse && isManualMode.value
    sendJson({
      type: 'input_audio_buffer.commit',
      event_id: createEventId('commit')
    })
  }

  function clearAudioBuffer() {
    sendJson({
      type: 'input_audio_buffer.clear',
      event_id: createEventId('clear')
    })
  }

  async function captureVideoFrame() {
    const video = cameraVideoRef.value
    const canvas = frameCanvasRef.value
    if (!video || !canvas || !video.videoWidth || !video.videoHeight || !upstreamReady.value) return

    const ratio = video.videoHeight / video.videoWidth
    canvas.width = frameWidth.value
    canvas.height = Math.round(frameWidth.value * ratio)
    const context = canvas.getContext('2d')
    if (!context) return

    context.drawImage(video, 0, 0, canvas.width, canvas.height)
    const blob = await createJpegBlob(canvas)
    const buffer = await blob.arrayBuffer()
    sendJson({
      type: 'input_image_buffer.append',
      event_id: createEventId('frame'),
      image: arrayBufferToBase64(buffer)
    })
    counters.sentImageFrames += 1
    counters.sentImageBytes += buffer.byteLength
    setLastFrameUrl(blob)
  }

  function stopAudioNodes() {
    if (processorNode) {
      processorNode.disconnect()
      processorNode.onaudioprocess = null
      processorNode = null
    }
    if (sourceNode) {
      sourceNode.disconnect()
      sourceNode = null
    }
    if (audioStream) {
      audioStream.getTracks().forEach((track) => track.stop())
      audioStream = null
    }
    if (audioContext) {
      audioContext.close()
      audioContext = null
    }
    recording.value = false
    inputVolume.value = 0
  }

  function handleServerMessage(raw: string) {
    let payload: any = raw
    try {
      payload = JSON.parse(raw)
    } catch {
      addEvent('server.raw', raw.slice(0, 500), 'recv')
      return
    }

    const type = payload.type || 'server.message'
    counters.serverEvents += 1

    if (type === 'gateway.connected') {
      modelName.value = payload.data?.model || modelName.value
      applySessionDefaults(payload.data?.session || {})
    }
    if (type === 'gateway.upstream_open') {
      upstreamReady.value = true
      ElMessage.success('阿里云实时端点已连接')
      if (autoSessionUpdate.value) {
        sendSessionUpdate()
      }
    }
    if (type === 'gateway.pong' && lastPingAt.value) {
      latencyMs.value = Math.round(performance.now() - lastPingAt.value)
    }
    if (type === 'gateway.upstream_close') {
      upstreamReady.value = false
    }
    if (type === 'session.updated') {
      sessionUpdated.value = true
      applySessionDefaults(payload.session || {})
    }
    if (type === 'response.created') {
      responseActive.value = true
    }
    if (type === 'response.done') {
      responseActive.value = false
    }
    if (type === 'input_audio_buffer.committed' && pendingResponseAfterCommit) {
      pendingResponseAfterCommit = false
      sendJson({
        type: 'response.create',
        event_id: createEventId('response')
      })
    }
    if (type === 'error' || type === 'gateway.error' || type.endsWith('.error')) {
      counters.errors += 1
    }

    appendTextDelta(payload)
    appendAudioDelta(payload)
    addEvent(type, JSON.stringify(summarizePayload(payload)).slice(0, 1200), eventLevel(type))
  }

  function appendTextDelta(payload: Record<string, any>) {
    const delta = payload.delta || payload.text || ''
    const textTypes = [
      'response.text.delta',
      'response.output_text.delta',
      'response.audio_transcript.delta',
      'response.output_audio_transcript.delta',
      'conversation.item.input_audio_transcription.completed'
    ]
    if (textTypes.includes(payload.type) && delta) {
      assistantText.value += delta
      counters.textChars += String(delta).length
    }
  }

  function appendAudioDelta(payload: Record<string, any>) {
    const audioDeltaTypes = ['response.audio.delta', 'response.output_audio.delta']
    const audioDoneTypes = ['response.audio.done', 'response.output_audio.done']

    if (audioDeltaTypes.includes(payload.type) && payload.delta) {
      const samples = base64ToInt16Array(String(payload.delta))
      outputSampleChunks.push(samples)
      queuePcmPlayback(samples)
      counters.receivedAudioChunks += 1
      counters.receivedAudioBytes += samples.byteLength
    }
    if (audioDoneTypes.includes(payload.type) && outputSampleChunks.length > 0) {
      buildOutputAudioUrl(outputSampleChunks)
      outputSampleChunks = []
    }
  }

  function sendJson(data: Record<string, any>) {
    if (!socket || socket.readyState !== WebSocket.OPEN) {
      ElMessage.warning('请先连接实时代理')
      return
    }
    socket.send(JSON.stringify(data))
    addEvent(`send.${data.type}`, JSON.stringify(summarizePayload(data)).slice(0, 800), 'send')
  }

  function sendAudioPcm(buffer: ArrayBuffer) {
    sendJson({
      type: 'input_audio_buffer.append',
      event_id: createEventId('audio'),
      audio: arrayBufferToBase64(buffer)
    })
    counters.sentAudioChunks += 1
    counters.sentAudioBytes += buffer.byteLength
    sentAudioSeconds.value += buffer.byteLength / 2 / 16000
  }

  function buildSession() {
    const session: Record<string, any> = {
      modalities: modalities.value,
      input_audio_format: 'pcm',
      output_audio_format: 'pcm',
      instructions: instructions.value,
      turn_detection: buildTurnDetection(),
      input_audio_transcription: {
        model: 'qwen3-asr-flash-realtime'
      },
      smooth_output: true,
      temperature: temperature.value,
      top_k: topK.value,
      repetition_penalty: repetitionPenalty.value,
      presence_penalty: presencePenalty.value
    }

    if (modalities.value.includes('audio')) {
      session.voice = voice.value
    }

    return session
  }

  function buildTurnDetection() {
    if (vadMode.value === 'manual') {
      return null
    }

    return {
      type: vadMode.value,
      threshold: vadThreshold.value,
      silence_duration_ms: vadSilenceDuration.value
    }
  }

  function applySessionDefaults(session: Record<string, any>) {
    if (Array.isArray(session.modalities) && session.modalities.length > 0) {
      modalities.value = session.modalities
    }
    if (session.voice) {
      voice.value = String(session.voice)
    }
    if (session.instructions) {
      instructions.value = String(session.instructions)
    }
    if (session.temperature !== undefined) {
      temperature.value = Number(session.temperature)
    }
    if (session.top_k !== undefined) {
      topK.value = Number(session.top_k)
    }
    if (session.repetition_penalty !== undefined) {
      repetitionPenalty.value = Number(session.repetition_penalty)
    }
    if (session.presence_penalty !== undefined) {
      presencePenalty.value = Number(session.presence_penalty)
    }

    if (session.turn_detection === null) {
      vadMode.value = 'manual'
      return
    }

    if (session.turn_detection && typeof session.turn_detection === 'object') {
      vadMode.value = session.turn_detection.type || 'server_vad'
      vadThreshold.value = Number(session.turn_detection.threshold ?? 0.5)
      vadSilenceDuration.value = Number(session.turn_detection.silence_duration_ms ?? 800)
      return
    }

    vadMode.value = 'server_vad'
  }

  function startTimers() {
    stopTimers()
    elapsedTimer = window.setInterval(() => {
      elapsedSeconds.value = connectionStartedAt
        ? Math.floor((Date.now() - connectionStartedAt) / 1000)
        : 0
    }, 1000)
    pingTimer = window.setInterval(sendGatewayPing, 10000)
  }

  function stopTimers() {
    if (elapsedTimer) {
      window.clearInterval(elapsedTimer)
      elapsedTimer = undefined
    }
    if (pingTimer) {
      window.clearInterval(pingTimer)
      pingTimer = undefined
    }
  }

  function sendGatewayPing() {
    if (!socket || socket.readyState !== WebSocket.OPEN) return
    lastPingAt.value = performance.now()
    socket.send(JSON.stringify({ type: 'gateway.ping' }))
  }

  function resetRuntimeState() {
    upstreamReady.value = false
    sessionUpdated.value = false
    responseActive.value = false
    assistantText.value = ''
    events.value = []
    inputVolume.value = 0
    outputVolume.value = 0
    sentAudioSeconds.value = 0
    elapsedSeconds.value = 0
    latencyMs.value = undefined
    outputSampleChunks = []
    resetPlaybackQueue()
    Object.assign(counters, {
      sentAudioChunks: 0,
      sentAudioBytes: 0,
      sentImageFrames: 0,
      sentImageBytes: 0,
      receivedAudioChunks: 0,
      receivedAudioBytes: 0,
      serverEvents: 0,
      errors: 0,
      textChars: 0
    })
  }

  function addEvent(type: string, payload: string, level: EventLevel = 'info') {
    events.value.unshift({
      time: new Date().toLocaleTimeString('zh-CN', { hour12: false }),
      type,
      payload,
      level
    })
    events.value = events.value.slice(0, 200)
  }

  function summarizePayload(data: Record<string, any>) {
    const clone = JSON.parse(JSON.stringify(data))
    if (clone.audio) {
      clone.audio = `[base64:${clone.audio.length}]`
    }
    if (clone.image) {
      clone.image = `[base64:${clone.image.length}]`
    }
    return clone
  }

  function eventLevel(type: string): EventLevel {
    if (type === 'error' || type === 'gateway.error' || type.endsWith('.error')) return 'error'
    if (type.startsWith('gateway.') || type === 'session.updated') return 'info'
    if (type.startsWith('response.') || type.startsWith('conversation.')) return 'recv'
    if (type.startsWith('input_audio_buffer.')) return 'recv'
    return 'info'
  }

  function resample(input: Float32Array, inputRate: number, outputRate: number) {
    if (inputRate === outputRate) return input
    const ratio = inputRate / outputRate
    const length = Math.floor(input.length / ratio)
    const output = new Float32Array(length)
    for (let i = 0; i < length; i++) {
      output[i] = input[Math.floor(i * ratio)] || 0
    }
    return output
  }

  function floatTo16BitPcm(input: Float32Array) {
    const output = new Int16Array(input.length)
    for (let i = 0; i < input.length; i++) {
      const s = Math.max(-1, Math.min(1, input[i]))
      output[i] = s < 0 ? s * 0x8000 : s * 0x7fff
    }
    return output
  }

  function arrayBufferToBase64(buffer: ArrayBuffer) {
    const bytes = new Uint8Array(buffer)
    let binary = ''
    const chunkSize = 0x8000
    for (let i = 0; i < bytes.length; i += chunkSize) {
      binary += String.fromCharCode(...bytes.subarray(i, i + chunkSize))
    }
    return btoa(binary)
  }

  function calculateVolume(input: Float32Array) {
    let sum = 0
    for (let i = 0; i < input.length; i++) {
      sum += input[i] * input[i]
    }
    return Math.min(1, Math.sqrt(sum / input.length) * 6)
  }

  function calculatePcmVolume(samples: Int16Array) {
    let sum = 0
    for (let i = 0; i < samples.length; i++) {
      const value = samples[i] / 0x8000
      sum += value * value
    }
    return Math.min(1, Math.sqrt(sum / Math.max(1, samples.length)) * 6)
  }

  function queuePcmPlayback(samples: Int16Array) {
    outputVolume.value = calculatePcmVolume(samples)

    if (!playbackContext) {
      playbackContext = new AudioContext({ sampleRate: 24000 })
      playbackCursor = playbackContext.currentTime + 0.03
    }

    if (playbackContext.state === 'suspended') {
      playbackContext.resume().catch(() => undefined)
    }

    const buffer = playbackContext.createBuffer(1, samples.length, 24000)
    const channel = buffer.getChannelData(0)
    for (let i = 0; i < samples.length; i++) {
      channel[i] = samples[i] / 0x8000
    }

    const source = playbackContext.createBufferSource()
    source.buffer = buffer
    source.connect(playbackContext.destination)

    const startAt = Math.max(playbackCursor, playbackContext.currentTime + 0.01)
    source.start(startAt)
    playbackCursor = startAt + buffer.duration
  }

  function buildOutputAudioUrl(chunks: Int16Array[]) {
    const samples = mergePcmSamples(chunks)
    const wav = createWavBlob(samples, 24000)
    revokeOutputAudioUrl()
    outputAudioUrl.value = URL.createObjectURL(wav)
  }

  function resetPlaybackQueue() {
    if (playbackContext) {
      playbackContext.close().catch(() => undefined)
      playbackContext = null
    }
    playbackCursor = 0
  }

  function base64ToInt16Array(chunk: string) {
    const binary = atob(chunk)
    const bytes = new Uint8Array(binary.length)
    for (let i = 0; i < binary.length; i++) {
      bytes[i] = binary.charCodeAt(i)
    }
    return new Int16Array(bytes.buffer)
  }

  function mergePcmSamples(chunks: Int16Array[]) {
    const total = chunks.reduce((sum, item) => sum + item.length, 0)
    const merged = new Int16Array(total)
    let offset = 0
    for (const item of chunks) {
      merged.set(item, offset)
      offset += item.length
    }
    return merged
  }

  function createWavBlob(samples: Int16Array, sampleRate: number) {
    const buffer = new ArrayBuffer(44 + samples.byteLength)
    const view = new DataView(buffer)
    writeAscii(view, 0, 'RIFF')
    view.setUint32(4, 36 + samples.byteLength, true)
    writeAscii(view, 8, 'WAVE')
    writeAscii(view, 12, 'fmt ')
    view.setUint32(16, 16, true)
    view.setUint16(20, 1, true)
    view.setUint16(22, 1, true)
    view.setUint32(24, sampleRate, true)
    view.setUint32(28, sampleRate * 2, true)
    view.setUint16(32, 2, true)
    view.setUint16(34, 16, true)
    writeAscii(view, 36, 'data')
    view.setUint32(40, samples.byteLength, true)
    new Uint8Array(buffer, 44).set(new Uint8Array(samples.buffer))
    return new Blob([buffer], { type: 'audio/wav' })
  }

  function writeAscii(view: DataView, offset: number, value: string) {
    for (let i = 0; i < value.length; i++) {
      view.setUint8(offset + i, value.charCodeAt(i))
    }
  }

  async function createJpegBlob(canvas: HTMLCanvasElement) {
    let quality = imageQuality.value
    let blob = await canvasToBlob(canvas, quality)
    while (blob.size > 480 * 1024 && quality > 0.45) {
      quality -= 0.08
      blob = await canvasToBlob(canvas, quality)
    }
    return blob
  }

  function canvasToBlob(canvas: HTMLCanvasElement, quality: number) {
    return new Promise<Blob>((resolve, reject) => {
      canvas.toBlob(
        (blob) => {
          if (blob) {
            resolve(blob)
            return
          }
          reject(new Error('视频帧生成失败'))
        },
        'image/jpeg',
        quality
      )
    })
  }

  function setLastFrameUrl(blob: Blob) {
    revokeLastFrameUrl()
    lastFrameUrl.value = URL.createObjectURL(blob)
  }

  function revokeLastFrameUrl() {
    if (lastFrameUrl.value) {
      URL.revokeObjectURL(lastFrameUrl.value)
      lastFrameUrl.value = ''
    }
  }

  function revokeOutputAudioUrl() {
    if (outputAudioUrl.value) {
      URL.revokeObjectURL(outputAudioUrl.value)
      outputAudioUrl.value = ''
    }
  }

  function createEventId(prefix: string) {
    return `${prefix}_${Date.now()}_${Math.random().toString(16).slice(2)}`
  }

  function formatBytes(bytes: number) {
    if (bytes < 1024) return `${bytes} B`
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`
  }

  function formatDuration(seconds: number) {
    const minute = Math.floor(seconds / 60)
    const second = seconds % 60
    return `${String(minute).padStart(2, '0')}:${String(second).padStart(2, '0')}`
  }
</script>

<style scoped>
  .realtime-test {
    display: flex;
    flex-direction: column;
    gap: 16px;
    min-height: 100%;
  }

  .toolbar-band,
  .panel,
  .metric-tile {
    border: 1px solid var(--el-border-color-light);
    border-radius: 8px;
    background: var(--el-bg-color);
  }

  .toolbar-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 18px 20px;
  }

  .title-block h2,
  .panel-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: var(--el-text-color-primary);
  }

  .title-block p {
    margin: 6px 0 0;
    color: var(--el-text-color-secondary);
  }

  .actions,
  .button-row,
  .panel-header,
  .media-actions {
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .actions,
  .media-actions {
    flex-wrap: wrap;
  }

  .actions {
    justify-content: flex-end;
  }

  .config-select {
    width: min(460px, 100%);
  }

  .status-strip,
  .traffic-grid,
  .audio-monitor {
    display: grid;
    gap: 12px;
  }

  .status-strip {
    grid-template-columns: repeat(5, minmax(0, 1fr));
  }

  .metric-tile,
  .traffic-grid > div,
  .audio-monitor > div {
    padding: 12px;
  }

  .metric-tile span,
  .traffic-grid span,
  .audio-monitor span {
    display: block;
    font-size: 12px;
    color: var(--el-text-color-secondary);
  }

  .metric-tile strong,
  .traffic-grid strong,
  .audio-monitor strong {
    display: block;
    margin-top: 4px;
    font-size: 20px;
    color: var(--el-text-color-primary);
  }

  .metric-tile small {
    display: block;
    margin-top: 4px;
    color: var(--el-text-color-secondary);
  }

  .control-grid,
  .input-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.35fr) minmax(360px, 0.65fr);
    gap: 16px;
  }

  .panel {
    padding: 16px;
  }

  .panel-header {
    justify-content: space-between;
    margin-bottom: 14px;
  }

  .form-grid,
  .vad-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
  }

  .vad-grid {
    margin-bottom: 8px;
  }

  .health-list {
    display: grid;
    gap: 10px;
    margin-bottom: 18px;
  }

  .health-row,
  .level-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
  }

  .health-row span,
  .level-title span {
    color: var(--el-text-color-secondary);
  }

  .level-block {
    margin-bottom: 16px;
  }

  .traffic-grid,
  .audio-monitor {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    margin-top: 14px;
  }

  .traffic-grid > div,
  .audio-monitor > div {
    border-radius: 6px;
    background: var(--el-fill-color-lighter);
  }

  .audio-player {
    width: 100%;
    margin-top: 16px;
  }

  .video-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(180px, 0.45fr);
    gap: 12px;
    margin-bottom: 14px;
  }

  .camera-preview,
  .frame-preview {
    width: 100%;
    aspect-ratio: 16 / 9;
    overflow: hidden;
    border-radius: 8px;
    background: var(--el-fill-color-light);
  }

  .camera-preview,
  .frame-preview img {
    object-fit: cover;
  }

  .frame-preview {
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--el-text-color-secondary);
  }

  .frame-preview img {
    width: 100%;
    height: 100%;
  }

  .video-controls {
    display: grid;
    gap: 10px;
  }

  .transcript,
  .events {
    overflow: auto;
    border-radius: 6px;
    background: var(--el-fill-color-lighter);
    padding: 12px;
    color: var(--el-text-color-primary);
  }

  .transcript {
    min-height: 130px;
    max-height: 220px;
    margin-bottom: 12px;
    line-height: 1.7;
  }

  .events {
    min-height: 360px;
    max-height: 620px;
  }

  .event-line {
    display: grid;
    grid-template-columns: 86px 210px minmax(0, 1fr);
    gap: 8px;
    align-items: start;
    padding: 7px 0;
    border-bottom: 1px solid var(--el-border-color-lighter);
    font-size: 13px;
  }

  .event-line.send strong {
    color: var(--el-color-primary);
  }

  .event-line.recv strong {
    color: var(--el-color-success);
  }

  .event-line.warn strong {
    color: var(--el-color-warning);
  }

  .event-line.error strong {
    color: var(--el-color-danger);
  }

  .event-line code {
    white-space: pre-wrap;
    word-break: break-word;
    color: var(--el-text-color-secondary);
  }

  .hidden-canvas {
    display: none;
  }

  @media (max-width: 1180px) {
    .status-strip {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .control-grid,
    .input-grid,
    .form-grid,
    .vad-grid,
    .video-layout {
      grid-template-columns: 1fr;
    }
  }

  @media (max-width: 720px) {
    .toolbar-inner,
    .actions,
    .media-actions,
    .button-row {
      align-items: stretch;
      flex-direction: column;
    }

    .status-strip,
    .traffic-grid,
    .audio-monitor {
      grid-template-columns: 1fr;
    }

    .event-line {
      grid-template-columns: 1fr;
    }
  }
</style>
