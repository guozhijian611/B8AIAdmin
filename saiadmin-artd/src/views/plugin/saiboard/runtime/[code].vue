<template>
  <div class="runtime-page" :style="{ background: bgColor }">
    <div v-if="loading" class="runtime-state">加载中</div>
    <div v-else-if="error" class="runtime-state">{{ error }}</div>
    <div v-else ref="viewportRef" class="runtime-viewport">
      <div
        class="runtime-canvas"
        :style="{
          width: layout.canvas.width + 'px',
          height: layout.canvas.height + 'px',
          transform: `translate(-50%, -50%) scale(${scale})`,
          background: bgColor
        }"
      >
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
  import type { BoardLayout } from '../widgets/types'

  const route = useRoute()
  const viewportRef = ref<HTMLElement>()
  const loading = ref(true)
  const error = ref('')
  const scale = ref(1)
  const timers: number[] = []
  const screen = reactive<any>({ bg_config: { color: '#07111f' } })
  const layout = reactive<BoardLayout>({ canvas: { width: 1920, height: 1080 }, components: [] })
  const dataMap = reactive<Record<string, { rows: Record<string, any>[]; error: string }>>({})

  const code = computed(() => String(route.params.code || ''))
  const token = computed(() => String(route.query.token || ''))
  const bgColor = computed(() => screen.bg_config?.color || '#07111f')

  const loadScreen = async () => {
    loading.value = true
    error.value = ''
    try {
      const result = await api.screen(code.value, token.value)
      Object.assign(screen, result.screen)
      const nextLayout = result.screen.layout || {}
      Object.assign(layout.canvas, {
        width: Number(nextLayout.canvas?.width || result.screen.width || 1920),
        height: Number(nextLayout.canvas?.height || result.screen.height || 1080)
      })
      layout.components.splice(0, layout.components.length, ...(nextLayout.components || []))
      resetPolling()
      await Promise.all(layout.components.map((component) => loadComponentData(component.id)))
      nextTick(updateScale)
    } catch (err: any) {
      error.value = err?.message || '大屏加载失败'
    } finally {
      loading.value = false
    }
  }

  const loadComponentData = async (cid: string) => {
    try {
      const result = await api.data({ code: code.value, cid, token: token.value })
      dataMap[cid] = { rows: result.rows || [], error: '' }
    } catch (err: any) {
      dataMap[cid] = { rows: dataMap[cid]?.rows || [], error: err?.message || '取数失败' }
    }
  }

  const resetPolling = () => {
    while (timers.length) window.clearInterval(timers.pop())
    for (const component of layout.components) {
      const seconds = Math.max(5, Number(component.dataset?.refresh || 30))
      timers.push(window.setInterval(() => loadComponentData(component.id), seconds * 1000))
    }
  }

  const updateScale = () => {
    const el = viewportRef.value
    if (!el) return
    scale.value = Math.min(el.clientWidth / layout.canvas.width, el.clientHeight / layout.canvas.height)
  }

  onMounted(() => {
    loadScreen()
    window.addEventListener('resize', updateScale)
  })

  onBeforeUnmount(() => {
    window.removeEventListener('resize', updateScale)
    while (timers.length) window.clearInterval(timers.pop())
  })
</script>

<style scoped lang="scss">
  .runtime-page,
  .runtime-viewport {
    width: 100vw;
    height: 100vh;
    overflow: hidden;
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
  }

  .runtime-canvas {
    position: absolute;
    top: 50%;
    left: 50%;
    overflow: hidden;
    transform-origin: center center;
  }

  .runtime-widget {
    position: absolute;
  }
</style>
