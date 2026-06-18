<template>
  <div ref="widgetRef" class="board-widget" :class="{ 'is-runtime': runtime }">
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
        <span v-if="component.option?.prefix" class="metric-prefix">{{
          component.option.prefix
        }}</span>
        <span class="metric-value">{{ metricValue }}</span>
        <span class="metric-unit">{{ component.option?.unit || '' }}</span>
      </div>
      <ElTable
        v-else-if="component.type === 'data-table'"
        :data="tableRows"
        size="small"
        height="100%"
        :stripe="Boolean(component.option?.rowStripe)"
        empty-text="暂无数据"
      >
        <ElTableColumn v-if="component.option?.showIndex" type="index" label="#" width="52" />
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

  const chartTypes = new Set(['art-bar-chart', 'art-line-chart', 'art-ring-chart'])

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

  const widgetRef = ref<HTMLElement>()
  let widgetResizeObserver: ResizeObserver | undefined
  let chartResizeFrame = 0

  const tableRows = computed(() => {
    const rows = props.rows || []
    const maxRows = Number(props.component.option?.maxRows || 0)
    return maxRows > 0 ? rows.slice(0, maxRows) : rows
  })
  const mapping = computed(() => props.component.dataset?.mapping || {})
  const hasBoundDataset = computed(() => Number(props.component.dataset?.queryTemplateId || 0) > 0)
  const tableColumns = computed(() => {
    const first = tableRows.value[0] || {}
    const mapped = Array.isArray(mapping.value.tableFields) ? mapping.value.tableFields : []
    const available = Object.keys(first)
    if (mapped.length) {
      return mapped.filter((field) => available.includes(field)).slice(0, 8)
    }
    return available.slice(0, 8)
  })

  const axisKey = computed(() =>
    findKey(mapping.value.labelField, ['label', 'name', 'date', 'time', 'category', 'x'])
  )
  const valueKeys = computed(() =>
    findNumberKeys(mapping.value.valueField, ['value', 'count', 'total', 'amount', 'y'])
  )
  const valueKey = computed(() => valueKeys.value[0] || 'value')

  const axisData = computed(() => {
    if (!tableRows.value.length) return hasBoundDataset.value ? [] : ['A', 'B', 'C', 'D', 'E']
    return tableRows.value.map((row, index) => String(row[axisKey.value] ?? `#${index + 1}`))
  })

  const seriesData = computed(() => {
    if (!tableRows.value.length) return hasBoundDataset.value ? [] : [12, 28, 19, 35, 24]
    if (valueKeys.value.length <= 1) {
      return tableRows.value.map((row) => Number(row[valueKey.value] ?? 0))
    }

    return valueKeys.value.map((key) => ({
      name: key,
      data: tableRows.value.map((row) => Number(row[key] ?? 0))
    }))
  })

  const ringData = computed(() => {
    if (!tableRows.value.length) {
      if (hasBoundDataset.value) return []
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
    if (!tableRows.value.length) return hasBoundDataset.value ? '-' : '0'
    const raw = tableRows.value[0][valueKey.value]
    const value = Number(raw ?? 0)
    if (!Number.isFinite(value)) return String(raw ?? '-')

    const decimals = normalizeDecimals(props.component.option?.decimals)
    return value.toLocaleString(undefined, {
      minimumFractionDigits: decimals,
      maximumFractionDigits: decimals
    })
  })

  function normalizeDecimals(value: unknown) {
    const decimals = Number(value ?? 0)
    if (!Number.isFinite(decimals)) return 0
    return Math.min(6, Math.max(0, Math.round(decimals)))
  }

  function notifyChartResize() {
    if (!chartTypes.has(props.component.type)) return
    if (chartResizeFrame) window.cancelAnimationFrame(chartResizeFrame)
    chartResizeFrame = window.requestAnimationFrame(() => {
      chartResizeFrame = 0
      window.dispatchEvent(new Event('resize'))
    })
  }

  function findKey(mapped: string | undefined, preferred: string[]) {
    const first = tableRows.value[0] || {}
    if (mapped && mapped in first) return mapped
    for (const key of preferred) {
      if (key in first) return key
    }
    return (
      Object.keys(first).find((key) => typeof first[key] === 'string') ||
      Object.keys(first)[0] ||
      'label'
    )
  }

  function findNumberKeys(mapped: string | undefined, preferred: string[]) {
    const first = tableRows.value[0] || {}
    if (mapped && mapped in first) return [mapped]

    const numericKeys = Object.keys(first).filter(
      (key) =>
        key !== axisKey.value && tableRows.value.some((row) => Number.isFinite(Number(row[key])))
    )
    if (numericKeys.length > 1) return numericKeys

    for (const key of preferred) {
      if (key in first) return [key]
    }

    return numericKeys.length ? [numericKeys[0]] : [Object.keys(first)[1] || 'value']
  }

  onMounted(() => {
    if (widgetRef.value && 'ResizeObserver' in window) {
      widgetResizeObserver = new ResizeObserver(notifyChartResize)
      widgetResizeObserver.observe(widgetRef.value)
    }
    notifyChartResize()
  })

  onBeforeUnmount(() => {
    widgetResizeObserver?.disconnect()
    if (chartResizeFrame) window.cancelAnimationFrame(chartResizeFrame)
  })

  watch(
    () => [props.component.type, props.component.rect?.w, props.component.rect?.h],
    notifyChartResize
  )
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
    min-width: 0;
    gap: 8px;
    overflow: hidden;
    color: #f8fbff;
  }

  .metric-value {
    min-width: 0;
    overflow: hidden;
    font-size: 42px;
    font-weight: 700;
    line-height: 1;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .metric-prefix,
  .metric-unit {
    flex: 0 0 auto;
    font-size: 15px;
    color: #8ab4f8;
  }

  .metric-unit {
    margin-left: 0;
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
