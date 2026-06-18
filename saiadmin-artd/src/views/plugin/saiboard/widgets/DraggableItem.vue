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
    :resizable="true"
    :min-width="120"
    :min-height="80"
    class-name="saiboard-draggable"
    class-name-active="saiboard-draggable-active"
    @activated="$emit('select', component.id)"
    @dragging="handleDragging"
    @resizing="handleResizing"
  >
    <WidgetRenderer :component="component" :rows="rows" />
  </Vue3DraggableResizable>
</template>

<script setup lang="ts">
  import Vue3DraggableResizable from 'vue3-draggable-resizable'
  import 'vue3-draggable-resizable/dist/Vue3DraggableResizable.css'
  import WidgetRenderer from './WidgetRenderer.vue'
  import type { BoardComponent } from './types'

  const props = withDefaults(
    defineProps<{
      component: BoardComponent
      rows?: Record<string, any>[]
      selected?: boolean
    }>(),
    {
      rows: () => [],
      selected: false
    }
  )

  const emit = defineEmits<{
    (e: 'select', id: string): void
    (e: 'update', component: BoardComponent): void
  }>()

  function handleDragging(x: number, y: number) {
    emit('update', {
      ...props.component,
      rect: { ...props.component.rect, x: Math.max(0, Math.round(x)), y: Math.max(0, Math.round(y)) }
    })
  }

  function handleResizing(x: number, y: number, w: number, h: number) {
    emit('update', {
      ...props.component,
      rect: {
        ...props.component.rect,
        x: Math.max(0, Math.round(x)),
        y: Math.max(0, Math.round(y)),
        w: Math.max(120, Math.round(w)),
        h: Math.max(80, Math.round(h))
      }
    })
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
