<template>
  <div class="runtime-page" :style="{ background: bgColor }">
    <div v-if="loading" class="runtime-state">加载中</div>
    <div v-else-if="error" class="runtime-state">{{ error }}</div>
    <div v-else ref="viewportRef" class="runtime-viewport">
      <div class="runtime-canvas" :style="canvasStyle">
        <div
          v-for="component in layout.components"
          :key="component.id"
          class="runtime-widget"
          :style="{
            left: component.rect.x + 'px',
            top: component.rect.y + 'px',
            width: component.rect.w + 'px',
            height: component.rect.h + 'px',
            zIndex: component.rect.z
          }"
        >
          <WidgetRenderer
            :component="component"
            :rows="dataMap[component.id]?.rows || []"
            :error="dataMap[component.id]?.error || ''"
            runtime
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
  import api from '../api/runtime'
  import WidgetRenderer from '../widgets/WidgetRenderer.vue'
  import { boardCanvasStyle, normalizeBgConfig, normalizeFitMode } from '../widgets/theme'
  import type { BoardComponent, BoardLayout } from '../widgets/types'

  const route = useRoute()
  const viewportRef = ref<HTMLElement>()
  const loading = ref(true)
  const error = ref('')
  const fit = reactive({ scaleX: 1, scaleY: 1, x: 0, y: 0 })
  const timers: number[] = []
  let resizeObserver: ResizeObserver | undefined
  const screen = reactive<any>({ bg_config: normalizeBgConfig() })
  const layout = reactive<BoardLayout>({ canvas: { width: 1920, height: 1080 }, components: [] })
  const dataMap = reactive<Record<string, { rows: Record<string, any>[]; error: string }>>({})

  const code = computed(() => String(route.params.code || ''))
  const token = computed(() => String(route.query.token || ''))
  const runtimeParams = computed(() => {
    const params: Record<string, any> = {}
    for (const [key, value] of Object.entries(route.query)) {
      if (key === 'token') continue
      params[key] = value
    }
    return params
  })
  const bgColor = computed(() => screen.bg_config?.color || '#07111f')
  const fitMode = computed(() => normalizeFitMode(screen.bg_config?.fit_mode))
  const canvasStyle = computed(() => ({
    ...boardCanvasStyle(screen.bg_config),
    width: layout.canvas.width + 'px',
    height: layout.canvas.height + 'px',
    left: fit.x + 'px',
    top: fit.y + 'px',
    transform: `scale(${fit.scaleX}, ${fit.scaleY})`
  }))
  const componentNeedsData = (component: BoardComponent) => component.type !== 'decor-border'

  const loadScreen = async () => {
    loading.value = true
    error.value = ''
    try {
      const result = await api.screen(code.value, token.value)
      Object.assign(screen, result.screen, {
        bg_config: normalizeBgConfig(result.screen?.bg_config)
      })
      const nextLayout = result.screen.layout || {}
      Object.assign(layout.canvas, {
        width: Number(nextLayout.canvas?.width || result.screen.width || 1920),
        height: Number(nextLayout.canvas?.height || result.screen.height || 1080)
      })
      layout.components.splice(0, layout.components.length, ...(nextLayout.components || []))
      resetPolling()
      await Promise.all(
        layout.components
          .filter((component) => componentNeedsData(component))
          .map((component) => loadComponentData(component.id))
      )
    } catch (err: any) {
      error.value = err?.message || '大屏加载失败'
    } finally {
      loading.value = false
      await nextTick()
      observeViewport()
      window.requestAnimationFrame(updateScale)
    }
  }

  const loadComponentData = async (cid: string) => {
    try {
      const result = await api.data({
        code: code.value,
        cid,
        token: token.value,
        ...runtimeParams.value
      })
      dataMap[cid] = { rows: result.rows || [], error: '' }
    } catch (err: any) {
      dataMap[cid] = { rows: dataMap[cid]?.rows || [], error: err?.message || '取数失败' }
    }
  }

  const resetPolling = () => {
    while (timers.length) window.clearInterval(timers.pop())
    for (const component of layout.components) {
      if (!componentNeedsData(component)) continue
      const seconds = Math.max(5, Number(component.dataset?.refresh || 30))
      timers.push(window.setInterval(() => loadComponentData(component.id), seconds * 1000))
    }
  }

  const updateScale = () => {
    const el = viewportRef.value
    if (!el) return
    const canvasWidth = Math.max(1, Number(layout.canvas.width || 1920))
    const canvasHeight = Math.max(1, Number(layout.canvas.height || 1080))
    const viewportWidth = el.clientWidth
    const viewportHeight = el.clientHeight
    const scaleX = viewportWidth / canvasWidth
    const scaleY = viewportHeight / canvasHeight
    if (fitMode.value === 'stretch') {
      fit.scaleX = validScale(scaleX)
      fit.scaleY = validScale(scaleY)
      fit.x = 0
      fit.y = 0
      return
    }

    const nextScale =
      fitMode.value === 'cover' ? Math.max(scaleX, scaleY) : Math.min(scaleX, scaleY)
    const scale = validScale(nextScale)
    fit.scaleX = scale
    fit.scaleY = scale
    fit.x = (viewportWidth - canvasWidth * scale) / 2
    fit.y = (viewportHeight - canvasHeight * scale) / 2
  }

  const validScale = (value: number) => (Number.isFinite(value) && value > 0 ? value : 1)

  const observeViewport = () => {
    resizeObserver?.disconnect()
    if (!viewportRef.value || !('ResizeObserver' in window)) return
    resizeObserver = new ResizeObserver(updateScale)
    resizeObserver.observe(viewportRef.value)
  }

  onMounted(() => {
    loadScreen()
    window.addEventListener('resize', updateScale)
  })

  watch(runtimeParams, () => {
    layout.components
      .filter((component) => componentNeedsData(component))
      .forEach((component) => loadComponentData(component.id))
  })

  onBeforeUnmount(() => {
    window.removeEventListener('resize', updateScale)
    resizeObserver?.disconnect()
    while (timers.length) window.clearInterval(timers.pop())
  })
</script>

<style scoped lang="scss">
  .runtime-page,
  .runtime-viewport {
    overflow: hidden;
  }

  .runtime-page {
    position: fixed;
    inset: 0;
    width: 100vw;
    height: 100vh;
  }

  .runtime-state {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100vw;
    height: 100vh;
    color: #d7e7ff;
    background: #07111f;
  }

  .runtime-viewport {
    position: relative;
    width: 100%;
    height: 100%;
  }

  .runtime-canvas {
    position: absolute;
    overflow: hidden;
    transform-origin: left top;
  }

  .runtime-widget {
    position: absolute;
  }
</style>
