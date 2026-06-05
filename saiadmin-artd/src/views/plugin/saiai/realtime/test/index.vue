<template>
  <div class="realtime-test">
    <section class="toolbar-band">
      <div class="toolbar-inner">
        <div class="title-block">
          <h2>阿里云实时语音测试</h2>
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
          <el-button :disabled="connected || !selectedConfigId" type="primary" @click="connect">
            连接
          </el-button>
          <el-button :disabled="!connected" @click="disconnect">断开</el-button>
        </div>
      </div>
    </section>

    <section class="main-grid">
      <div class="panel">
        <div class="panel-header">
          <h3>会话控制</h3>
          <el-tag :type="connected ? 'success' : 'info'">{{ connected ? '已连接' : '未连接' }}</el-tag>
        </div>
        <el-form label-width="100px">
          <el-form-item label="WebSocket">
            <el-input v-model="wsUrl" readonly />
          </el-form-item>
          <el-form-item label="模型">
            <el-input v-model="modelName" readonly />
          </el-form-item>
          <el-form-item label="输出模式">
            <el-checkbox-group v-model="modalities">
              <el-checkbox value="text">文本</el-checkbox>
              <el-checkbox value="audio">音频</el-checkbox>
            </el-checkbox-group>
          </el-form-item>
          <el-form-item label="音色">
            <el-input v-model="voice" />
          </el-form-item>
          <el-form-item label="系统提示">
            <el-input v-model="instructions" type="textarea" :rows="4" />
          </el-form-item>
        </el-form>
        <div class="button-row">
          <el-button :disabled="!upstreamReady" type="primary" @click="sendSessionUpdate">
            配置会话
          </el-button>
          <el-button :disabled="!upstreamReady" @click="sendResponseCreate">生成回复</el-button>
        </div>
      </div>

      <div class="panel">
        <div class="panel-header">
          <h3>音频输入</h3>
          <el-tag :type="recording ? 'danger' : 'info'">{{ recording ? '录音中' : '待录音' }}</el-tag>
        </div>
        <div class="meter">
          <div class="meter-bar" :style="{ width: `${Math.round(volume * 100)}%` }"></div>
        </div>
        <div class="button-row">
          <el-button :disabled="!upstreamReady || recording" type="danger" @click="startRecording">
            开始录音
          </el-button>
          <el-button :disabled="!recording" @click="stopRecording">停止并提交</el-button>
        </div>
        <el-alert
          title="浏览器会请求麦克风权限。停止录音后会发送 commit 和 response.create。"
          type="info"
          :closable="false"
          show-icon
        />
      </div>
    </section>

    <section class="event-grid">
      <div class="panel">
        <div class="panel-header">
          <h3>实时文本</h3>
          <el-button size="small" @click="assistantText = ''">清空</el-button>
        </div>
        <div class="transcript">{{ assistantText || '等待模型返回文本...' }}</div>
      </div>
      <div class="panel">
        <div class="panel-header">
          <h3>事件日志</h3>
          <el-button size="small" @click="events = []">清空</el-button>
        </div>
        <div class="events">
          <div v-for="(item, index) in events" :key="index" class="event-line">
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
  }

  const userStore = useUserStore()
  const realtimeConfigs = ref<RealtimeConfig[]>([])
  const selectedConfigId = ref<number>()
  const wsUrl = ref('')
  const modelName = ref('qwen3-omni-flash-realtime-2025-12-01')
  const connected = ref(false)
  const upstreamReady = ref(false)
  const recording = ref(false)
  const voice = ref('Ethan')
  const modalities = ref(['text', 'audio'])
  const instructions = ref('你是 B8AIadmin 的实时语音助手，请用准确、简洁、友好的中文回答用户。')
  const assistantText = ref('')
  const events = ref<EventItem[]>([])
  const volume = ref(0)

  let socket: WebSocket | null = null
  let audioContext: AudioContext | null = null
  let mediaStream: MediaStream | null = null
  let sourceNode: MediaStreamAudioSourceNode | null = null
  let processorNode: ScriptProcessorNode | null = null
  let audioChunks: string[] = []

  const statusText = computed(() => {
    if (recording.value) return '正在上传麦克风 PCM 音频'
    if (upstreamReady.value) return '已连接阿里云实时端点'
    if (connected.value) return '已连接本地代理，等待上游就绪'
    return '请选择 aliyun_realtime 配置后连接'
  })

  onMounted(async () => {
    await loadPage()
  })

  onBeforeUnmount(() => {
    disconnect()
  })

  async function loadPage() {
    const [testConfig, configPage] = await Promise.all([
      configApi.realtimeTestConfig(),
      configApi.list({ page: 1, limit: 100, type: 'aliyun_realtime' })
    ])

    wsUrl.value = testConfig.ws_url || ''
    modelName.value = testConfig.default_model || modelName.value
    const session = testConfig.default_session || {}
    voice.value = session.voice || voice.value
    instructions.value = session.instructions || instructions.value
    modalities.value = session.modalities || modalities.value

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
    const url = new URL(wsUrl.value)
    url.searchParams.set('token', token)
    url.searchParams.set('config_id', String(selectedConfigId.value))
    socket = new WebSocket(url.toString())

    socket.onopen = () => {
      connected.value = true
      addEvent('client.open', '本地代理连接成功')
    }
    socket.onclose = () => {
      connected.value = false
      upstreamReady.value = false
      recording.value = false
      addEvent('client.close', '连接已关闭')
    }
    socket.onerror = () => {
      ElMessage.error('实时连接发生错误')
      addEvent('client.error', 'WebSocket error')
    }
    socket.onmessage = (event) => {
      handleServerMessage(String(event.data))
    }
  }

  function disconnect() {
    stopAudioNodes()
    if (socket) {
      socket.close()
      socket = null
    }
    connected.value = false
    upstreamReady.value = false
  }

  function sendSessionUpdate() {
    sendJson({
      type: 'session.update',
      session: {
        modalities: modalities.value,
        voice: voice.value,
        input_audio_format: 'pcm',
        output_audio_format: 'pcm',
        instructions: instructions.value,
        turn_detection: null,
        input_audio_transcription: {
          model: 'qwen3-asr-flash-realtime'
        }
      }
    })
  }

  function sendResponseCreate() {
    sendJson({ type: 'response.create' })
  }

  async function startRecording() {
    if (!socket || socket.readyState !== WebSocket.OPEN) return
    audioChunks = []
    assistantText.value = ''
    mediaStream = await navigator.mediaDevices.getUserMedia({ audio: true })
    audioContext = new AudioContext()
    sourceNode = audioContext.createMediaStreamSource(mediaStream)
    processorNode = audioContext.createScriptProcessor(4096, 1, 1)

    processorNode.onaudioprocess = (event) => {
      const input = event.inputBuffer.getChannelData(0)
      volume.value = calculateVolume(input)
      const pcm = floatTo16BitPcm(resample(input, audioContext?.sampleRate || 48000, 16000))
      if (pcm.byteLength > 0) {
        sendJson({
          type: 'input_audio_buffer.append',
          audio: arrayBufferToBase64(pcm.buffer)
        })
      }
    }

    sourceNode.connect(processorNode)
    processorNode.connect(audioContext.destination)
    recording.value = true
    addEvent('client.recording_start', '开始录音')
  }

  function stopRecording() {
    stopAudioNodes()
    sendJson({ type: 'input_audio_buffer.commit' })
    sendResponseCreate()
    addEvent('client.recording_stop', '录音已提交')
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
    if (mediaStream) {
      mediaStream.getTracks().forEach((track) => track.stop())
      mediaStream = null
    }
    if (audioContext) {
      audioContext.close()
      audioContext = null
    }
    recording.value = false
    volume.value = 0
  }

  function handleServerMessage(raw: string) {
    let payload: any = raw
    try {
      payload = JSON.parse(raw)
    } catch {
      addEvent('server.raw', raw.slice(0, 200))
      return
    }

    const type = payload.type || 'server.message'
    if (type === 'gateway.connected') {
      modelName.value = payload.data?.model || modelName.value
    }
    if (type === 'gateway.upstream_open') {
      upstreamReady.value = true
      ElMessage.success('阿里云实时端点已连接')
    }
    if (type === 'gateway.upstream_close') {
      upstreamReady.value = false
    }
    if (type === 'response.text.delta') {
      assistantText.value += payload.delta || ''
    }
    if (type === 'response.audio_transcript.delta') {
      assistantText.value += payload.delta || ''
    }
    if (type === 'response.audio.delta' && payload.delta) {
      audioChunks.push(payload.delta)
    }
    if (type === 'response.audio.done' && audioChunks.length > 0) {
      playPcmAudio(audioChunks)
      audioChunks = []
    }

    addEvent(type, JSON.stringify(payload).slice(0, 500))
  }

  function sendJson(data: Record<string, any>) {
    if (!socket || socket.readyState !== WebSocket.OPEN) {
      ElMessage.warning('请先连接实时代理')
      return
    }
    socket.send(JSON.stringify(data))
    addEvent(`send.${data.type}`, JSON.stringify(data).slice(0, 300))
  }

  function addEvent(type: string, payload: string) {
    events.value.unshift({
      time: new Date().toLocaleTimeString('zh-CN', { hour12: false }),
      type,
      payload
    })
    events.value = events.value.slice(0, 120)
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

  function playPcmAudio(chunks: string[]) {
    const bytes = base64ChunksToBytes(chunks)
    const samples = new Int16Array(bytes.buffer)
    const context = new AudioContext({ sampleRate: 24000 })
    const buffer = context.createBuffer(1, samples.length, 24000)
    const channel = buffer.getChannelData(0)
    for (let i = 0; i < samples.length; i++) {
      channel[i] = samples[i] / 0x8000
    }
    const source = context.createBufferSource()
    source.buffer = buffer
    source.connect(context.destination)
    source.start()
  }

  function base64ChunksToBytes(chunks: string[]) {
    const byteParts = chunks.map((chunk) => {
      const binary = atob(chunk)
      const bytes = new Uint8Array(binary.length)
      for (let i = 0; i < binary.length; i++) {
        bytes[i] = binary.charCodeAt(i)
      }
      return bytes
    })
    const total = byteParts.reduce((sum, item) => sum + item.length, 0)
    const merged = new Uint8Array(total)
    let offset = 0
    for (const item of byteParts) {
      merged.set(item, offset)
      offset += item.length
    }
    return merged
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
  .panel {
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
  .panel-header {
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .actions {
    flex-wrap: wrap;
    justify-content: flex-end;
  }

  .config-select {
    width: min(460px, 100%);
  }

  .main-grid,
  .event-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    gap: 16px;
  }

  .panel {
    padding: 16px;
  }

  .panel-header {
    justify-content: space-between;
    margin-bottom: 14px;
  }

  .meter {
    height: 12px;
    overflow: hidden;
    border-radius: 6px;
    background: var(--el-fill-color-light);
    margin-bottom: 16px;
  }

  .meter-bar {
    height: 100%;
    background: var(--el-color-danger);
    transition: width 0.08s linear;
  }

  .transcript,
  .events {
    min-height: 260px;
    max-height: 420px;
    overflow: auto;
    border-radius: 6px;
    background: var(--el-fill-color-lighter);
    padding: 12px;
    line-height: 1.7;
    color: var(--el-text-color-primary);
  }

  .event-line {
    display: grid;
    grid-template-columns: 86px 190px minmax(0, 1fr);
    gap: 8px;
    align-items: start;
    padding: 6px 0;
    border-bottom: 1px solid var(--el-border-color-lighter);
    font-size: 13px;
  }

  .event-line code {
    white-space: pre-wrap;
    word-break: break-word;
    color: var(--el-text-color-secondary);
  }

  @media (max-width: 980px) {
    .toolbar-inner,
    .actions {
      align-items: stretch;
      flex-direction: column;
    }

    .main-grid,
    .event-grid {
      grid-template-columns: 1fr;
    }

    .event-line {
      grid-template-columns: 1fr;
    }
  }
</style>
