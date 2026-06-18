<template>
  <div class="board-widget" :class="{ 'is-runtime': runtime }">
    <div v-if="component.title" class="widget-title">{{ component.title }}</div>
    <div class="widget-body">
      <ArtBarChart
        v-if="component.type === 'art-bar-chart'"
        height="100%"
        :data="seriesData"
        :x-axis-data="axisData"
        v-bind="component.option"
      />
      <ArtLineChart
        v-else-if="component.type === 'art-line-chart'"
        height="100%"
        :data="seriesData"
        :x-axis-data="axisData"
        v-bind="component.option"
      />
      <ArtRingChart
        v-else-if="component.type === 'art-ring-chart'"
        height="100%"
        :data="ringData"
        v-bind="component.option"
      />
      <div v-else-if="component.type === 'stat-number'" class="metric">
        <span class="metric-value">{{ metricValue }}</span>
        <span class="metric-unit">{{ component.option?.unit || '' }}</span>
      </div>
      <ElTable v-else-if="component.type === 'data-table'" :data="tableRows" size="small" height="100%">
        <ElTableColumn
          v-for="column in tableColumns"
          :key="column"
          :prop="column"
          :label="column"
          show-overflow-tooltip
        />
      </ElTable>
      <div v-else class="unsupported">组件不可用</div>
    </div>
    <div v-if="error" class="widget-error">{{ error }}</div>
  </div>
</template>

<script setup lang="ts">
  import ArtBarChart from '@/components/core/charts/art-bar-chart/index.vue'
  import ArtLineChart from '@/components/core/charts/art-line-chart/index.vue'
  import ArtRingChart from '@/components/core/charts/art-ring-chart/index.vue'
  import type { BoardComponent } from './types'

  const props = withDefaults(
    defineProps<{
      component: BoardComponent
      rows?: Record<string, any>[]
      error?: string
      runtime?: boolean
    }>(),
    {
      rows: () => [],
      error: '',
      runtime: false
    }
  )

  const tableRows = computed(() => props.rows || [])
  const tableColumns = computed(() => {
    const first = tableRows.value[0] || {}
    return Object.keys(first).slice(0, 8)
  })

  const axisKey = computed(() => findKey(['label', 'name', 'date', 'time', 'category', 'x']))
  const valueKey = computed(() => findNumberKey(['value', 'count', 'total', 'amount', 'y']))

  const axisData = computed(() => {
    if (!tableRows.value.length) return ['A', 'B', 'C', 'D', 'E']
    return tableRows.value.map((row, index) => String(row[axisKey.value] ?? `#${index + 1}`))
  })

  const seriesData = computed(() => {
    if (!tableRows.value.length) return [12, 28, 19, 35, 24]
    return tableRows.value.map((row) => Number(row[valueKey.value] ?? 0))
  })

  const ringData = computed(() => {
    if (!tableRows.value.length) {
      return [
        { name: 'A', value: 35 },
        { name: 'B', value: 28 },
        { name: 'C', value: 18 }
      ]
    }
    return tableRows.value.map((row, index) => ({
      name: String(row[axisKey.value] ?? `#${index + 1}`),
      value: Number(row[valueKey.value] ?? 0)
    }))
  })

  const metricValue = computed(() => {
    if (!tableRows.value.length) return '0'
    const raw = tableRows.value[0][valueKey.value]
    const value = Number(raw ?? 0)
    return Number.isFinite(value) ? value.toLocaleString() : String(raw ?? '-')
  })

  function findKey(preferred: string[]) {
    const first = tableRows.value[0] || {}
    for (const key of preferred) {
      if (key in first) return key
    }
    return Object.keys(first).find((key) => typeof first[key] === 'string') || Object.keys(first)[0] || 'label'
  }

  function findNumberKey(preferred: string[]) {
    const first = tableRows.value[0] || {}
    for (const key of preferred) {
      if (key in first) return key
    }
    return (
      Object.keys(first).find((key) => Number.isFinite(Number(first[key]))) ||
      Object.keys(first)[1] ||
      'value'
    )
  }
</script>

<style scoped lang="scss">
  .board-widget {
    position: relative;
    display: flex;
    flex-direction: column;
    width: 100%;
    height: 100%;
    min-width: 0;
    min-height: 0;
    overflow: hidden;
    color: #e5eefb;
    background: rgb(8 18 32 / 78%);
    border: 1px solid rgb(104 166 255 / 24%);
    border-radius: 6px;
  }

  .widget-title {
    height: 34px;
    padding: 8px 12px 0;
    overflow: hidden;
    font-size: 15px;
    font-weight: 600;
    line-height: 24px;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .widget-body {
    flex: 1;
    min-height: 0;
    padding: 8px 10px 10px;
  }

  .metric {
    display: flex;
    align-items: baseline;
    justify-content: center;
    height: 100%;
    color: #f8fbff;
  }

  .metric-value {
    font-size: 42px;
    font-weight: 700;
    line-height: 1;
  }

  .metric-unit {
    margin-left: 8px;
    font-size: 15px;
    color: #8ab4f8;
  }

  .widget-error,
  .unsupported {
    position: absolute;
    right: 10px;
    bottom: 8px;
    left: 10px;
    overflow: hidden;
    font-size: 12px;
    color: #ffb4ab;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  :deep(.el-table) {
    --el-table-bg-color: transparent;
    --el-table-tr-bg-color: transparent;
    --el-table-header-bg-color: rgb(255 255 255 / 6%);
    --el-table-border-color: rgb(255 255 255 / 8%);
    --el-table-text-color: #d7e7ff;
    --el-table-header-text-color: #9fc4ff;
  }
</style>
