<template>
  <Vue3DraggableResizable
    :x="component.rect.x"
    :y="component.rect.y"
    :w="component.rect.w"
    :h="component.rect.h"
    :z="component.rect.z"
    :active="selected"
    :parent="true"
    :draggable="true"
    :resizable="resizable"
    :min-w="120"
    :min-h="80"
    class-name="saiboard-draggable"
    class-name-active="saiboard-draggable-active"
    @mousedown.capture="handlePointerSelect"
    @activated="handleActivated"
    @drag-start="handleDragStart"
    @drag-end="handleDragEnd"
    @resize-start="handleResizeStart"
    @resize-end="handleResizeEnd"
    @dragging="handleDragging"
    @resizing="handleResizing"
  >
    <WidgetRenderer :component="component" :rows="rows" :error="error" />
  </Vue3DraggableResizable>
</template>

<script setup lang="ts">
  import Vue3DraggableResizable from 'vue3-draggable-resizable'
  import 'vue3-draggable-resizable/dist/Vue3DraggableResizable.css'
  import { normalizeBoardScale } from './fit'
  import WidgetRenderer from './WidgetRenderer.vue'
  import type { BoardComponent } from './types'

  interface DragPayload {
    x: number
    y: number
  }

  interface ResizePayload extends DragPayload {
    w: number
    h: number
  }

  const props = withDefaults(
    defineProps<{
      component: BoardComponent
      rows?: Record<string, any>[]
      error?: string
      selected?: boolean
      resizable?: boolean
      zoom?: number
    }>(),
    {
      rows: () => [],
      error: '',
      selected: false,
      resizable: true,
      zoom: 1
    }
  )

  const emit = defineEmits<{
    (e: 'select', id: string, additive: boolean): void
    (e: 'resize-start', id: string, rect: ResizePayload): void
    (e: 'update', component: BoardComponent): void
  }>()

  let lastPointerSelectWasAdditive = false
  let dragStart: DragPayload | undefined
  let resizeStart: ResizePayload | undefined

  function handlePointerSelect(event: MouseEvent) {
    lastPointerSelectWasAdditive = event.shiftKey || event.metaKey || event.ctrlKey
    emit('select', props.component.id, lastPointerSelectWasAdditive)
  }

  function handleActivated() {
    if (lastPointerSelectWasAdditive) {
      lastPointerSelectWasAdditive = false
      return
    }
    emit('select', props.component.id, false)
  }

  function handleDragStart(payload: DragPayload) {
    dragStart = normalizeRawDragPayload(payload)
  }

  function handleDragEnd() {
    dragStart = undefined
  }

  function handleResizeStart(rect: ResizePayload) {
    resizeStart = normalizeRawResizePayload(rect)
    emit('resize-start', props.component.id, resizeStart)
  }

  function handleResizeEnd() {
    resizeStart = undefined
  }

  function handleDragging(payload: DragPayload) {
    const rect = normalizeDragPayload(payload)
    emit('update', {
      ...props.component,
      rect: {
        ...props.component.rect,
        x: rect.x,
        y: rect.y
      }
    })
  }

  function handleResizing(payload: ResizePayload) {
    const rect = normalizeResizePayload(payload)
    emit('update', {
      ...props.component,
      rect: {
        ...props.component.rect,
        x: rect.x,
        y: rect.y,
        w: rect.w,
        h: rect.h
      }
    })
  }

  function normalizeDragPayload(payload: DragPayload): DragPayload {
    const raw = normalizeRawDragPayload(payload)
    if (!dragStart) return raw
    const scale = normalizeBoardScale(props.zoom)

    return {
      x: Math.max(0, Math.round(dragStart.x + (raw.x - dragStart.x) / scale)),
      y: Math.max(0, Math.round(dragStart.y + (raw.y - dragStart.y) / scale))
    }
  }

  function normalizeResizePayload(payload: ResizePayload): ResizePayload {
    const raw = normalizeRawResizePayload(payload)
    if (!resizeStart) return raw
    const scale = normalizeBoardScale(props.zoom)

    return {
      x: Math.max(0, Math.round(resizeStart.x + (raw.x - resizeStart.x) / scale)),
      y: Math.max(0, Math.round(resizeStart.y + (raw.y - resizeStart.y) / scale)),
      w: Math.max(120, Math.round(resizeStart.w + (raw.w - resizeStart.w) / scale)),
      h: Math.max(80, Math.round(resizeStart.h + (raw.h - resizeStart.h) / scale))
    }
  }

  function normalizeRawDragPayload(payload: DragPayload): DragPayload {
    return {
      x: Math.max(0, Math.round(Number(payload.x || 0))),
      y: Math.max(0, Math.round(Number(payload.y || 0)))
    }
  }

  function normalizeRawResizePayload(payload: ResizePayload): ResizePayload {
    return {
      x: Math.max(0, Math.round(Number(payload.x || 0))),
      y: Math.max(0, Math.round(Number(payload.y || 0))),
      w: Math.max(120, Math.round(Number(payload.w || 120))),
      h: Math.max(80, Math.round(Number(payload.h || 80)))
    }
  }
</script>

<style scoped lang="scss">
  :global(.saiboard-draggable) {
    position: absolute;
  }

  :global(.saiboard-draggable-active) {
    outline: 1px solid #4ea1ff;
  }
</style>
