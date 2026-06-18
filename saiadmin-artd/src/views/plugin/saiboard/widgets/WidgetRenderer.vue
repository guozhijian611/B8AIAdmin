<template>
  <div
    ref="widgetRef"
    class="board-widget"
    :class="{ 'is-runtime': runtime, 'is-decor': component.type === 'decor-border' }"
  >
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
      <ArtHBarChart
        v-else-if="component.type === 'art-h-bar-chart'"
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
      <ArtRadarChart
        v-else-if="component.type === 'art-radar-chart'"
        height="100%"
        :data="radarData"
        :indicator="radarIndicators"
        v-bind="component.option"
      />
      <ArtScatterChart
        v-else-if="component.type === 'art-scatter-chart'"
        height="100%"
        :data="scatterData"
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
      <div
        v-else-if="component.type === 'decor-border'"
        class="decor-border"
        :class="`is-${decorStyle}`"
        :style="decorStyleVars"
      ></div>
      <div v-else class="unsupported">组件不可用</div>
    </div>
    <div v-if="error" class="widget-error">{{ error }}</div>
  </div>
</template>

<script setup lang="ts">
  import ArtBarChart from '@/components/core/charts/art-bar-chart/index.vue'
  import ArtHBarChart from '@/components/core/charts/art-h-bar-chart/index.vue'
  import ArtLineChart from '@/components/core/charts/art-line-chart/index.vue'
  import ArtRadarChart from '@/components/core/charts/art-radar-chart/index.vue'
  import ArtRingChart from '@/components/core/charts/art-ring-chart/index.vue'
  import ArtScatterChart from '@/components/core/charts/art-scatter-chart/index.vue'
  import type { BoardComponent } from './types'

  const chartTypes = new Set([
    'art-bar-chart',
    'art-line-chart',
    'art-h-bar-chart',
    'art-ring-chart',
    'art-radar-chart',
    'art-scatter-chart'
  ])

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

  const radarIndicators = computed(() => {
    const rows = tableRows.value
    if (!rows.length) {
      return hasBoundDataset.value ? [] : axisData.value.map((name) => ({ name, max: 40 }))
    }

    return axisData.value.map((name, index) => {
      const max = Math.max(1, ...valueKeys.value.map((key) => Number(rows[index]?.[key] ?? 0)))
      return { name, max: Math.ceil(max * 1.2) }
    })
  })

  const radarData = computed(() => {
    if (!tableRows.value.length) {
      return hasBoundDataset.value
        ? []
        : [{ name: props.component.title || '指标', value: [12, 28, 19, 35, 24] }]
    }

    return valueKeys.value.map((key) => ({
      name: key,
      value: tableRows.value.map((row) => Number(row[key] ?? 0))
    }))
  })

  const scatterData = computed(() => {
    if (!tableRows.value.length) {
      return hasBoundDataset.value
        ? []
        : [
            { value: [0, 12] },
            { value: [1, 28] },
            { value: [2, 19] },
            { value: [3, 35] },
            { value: [4, 24] }
          ]
    }

    const xKey = axisKey.value
    return tableRows.value.map((row, index) => ({
      value: [
        Number.isFinite(Number(row[xKey])) ? Number(row[xKey]) : index,
        Number(row[valueKey.value] ?? 0)
      ]
    }))
  })

  const decorStyle = computed(() => {
    const value = String(props.component.option?.borderStyle || 'corner')
    return ['corner', 'line', 'glow'].includes(value) ? value : 'corner'
  })
  const decorStyleVars = computed(() => ({
    '--decor-accent': String(props.component.option?.accent || 'var(--saiboard-accent, #69b7ff)'),
    '--decor-opacity': String(normalizeOpacity(props.component.option?.opacity))
  }))

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

  function normalizeOpacity(value: unknown) {
    const opacity = Number(value ?? 0.85)
    if (!Number.isFinite(opacity)) return 0.85
    return Math.min(1, Math.max(0.1, opacity))
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
    color: var(--saiboard-text, #e5eefb);
    background: var(--saiboard-panel-bg, rgb(8 18 32 / 78%));
    border: 1px solid var(--saiboard-panel-border, rgb(104 166 255 / 24%));
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

  .board-widget.is-decor {
    background: transparent;
    border: 0;
  }

  .board-widget.is-decor .widget-body {
    padding: 0;
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
    color: var(--saiboard-accent, #8ab4f8);
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

  .decor-border {
    position: relative;
    width: 100%;
    height: 100%;
    opacity: var(--decor-opacity);
    border: 1px solid color-mix(in srgb, var(--decor-accent) 42%, transparent);
    border-radius: 4px;
  }

  .decor-border::before,
  .decor-border::after {
    position: absolute;
    inset: 10px;
    pointer-events: none;
    content: '';
    border: 1px solid color-mix(in srgb, var(--decor-accent) 18%, transparent);
  }

  .decor-border.is-corner {
    background:
      linear-gradient(var(--decor-accent), var(--decor-accent)) left top / 42px 2px no-repeat,
      linear-gradient(var(--decor-accent), var(--decor-accent)) left top / 2px 42px no-repeat,
      linear-gradient(var(--decor-accent), var(--decor-accent)) right top / 42px 2px no-repeat,
      linear-gradient(var(--decor-accent), var(--decor-accent)) right top / 2px 42px no-repeat,
      linear-gradient(var(--decor-accent), var(--decor-accent)) left bottom / 42px 2px no-repeat,
      linear-gradient(var(--decor-accent), var(--decor-accent)) left bottom / 2px 42px no-repeat,
      linear-gradient(var(--decor-accent), var(--decor-accent)) right bottom / 42px 2px no-repeat,
      linear-gradient(var(--decor-accent), var(--decor-accent)) right bottom / 2px 42px no-repeat;
  }

  .decor-border.is-line {
    background:
      linear-gradient(90deg, transparent, var(--decor-accent), transparent) top / 100% 1px no-repeat,
      linear-gradient(90deg, transparent, var(--decor-accent), transparent) bottom / 100% 1px
        no-repeat;
  }

  .decor-border.is-glow {
    box-shadow:
      inset 0 0 22px color-mix(in srgb, var(--decor-accent) 28%, transparent),
      0 0 28px color-mix(in srgb, var(--decor-accent) 18%, transparent);
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
