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
    @resize-start="handleResizeStart"
    @dragging="handleDragging"
    @resizing="handleResizing"
  >
    <WidgetRenderer :component="component" :rows="rows" :error="error" />
  </Vue3DraggableResizable>
</template>

<script setup lang="ts">
  import Vue3DraggableResizable from 'vue3-draggable-resizable'
  import 'vue3-draggable-resizable/dist/Vue3DraggableResizable.css'
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
    }>(),
    {
      rows: () => [],
      error: '',
      selected: false,
      resizable: true
    }
  )

  const emit = defineEmits<{
    (e: 'select', id: string, additive: boolean): void
    (e: 'resize-start', id: string, rect: ResizePayload): void
    (e: 'update', component: BoardComponent): void
  }>()

  let lastPointerSelectWasAdditive = false

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

  function handleResizeStart(rect: ResizePayload) {
    emit('resize-start', props.component.id, normalizeResizePayload(rect))
  }

  function handleDragging(payload: DragPayload) {
    emit('update', {
      ...props.component,
      rect: {
        ...props.component.rect,
        x: Math.max(0, Math.round(Number(payload.x || 0))),
        y: Math.max(0, Math.round(Number(payload.y || 0)))
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

  function normalizeResizePayload(payload: ResizePayload): ResizePayload {
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
