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
          :key="column.field"
          :prop="column.field"
          :label="column.label"
          :width="column.width"
          :align="column.align"
          :header-align="column.align"
          show-overflow-tooltip
        />
      </ElTable>
      <div v-else-if="component.type === 'image-carousel'" class="image-carousel">
        <ElCarousel
          v-if="carouselSlides.length"
          height="100%"
          :interval="carouselInterval"
          :indicator-position="carouselIndicatorPosition"
          arrow="hover"
        >
          <ElCarouselItem v-for="(slide, index) in carouselSlides" :key="`${index}-${slide.title}`">
            <div class="carousel-slide">
              <img
                v-if="slide.image"
                class="carousel-slide__image"
                :src="slide.image"
                :alt="slide.title || `slide-${index + 1}`"
                :style="{ objectFit: carouselImageFit }"
              />
              <div v-else class="carousel-slide__fallback">
                {{ slide.title || `轮播 ${index + 1}` }}
              </div>
              <div v-if="slide.title" class="carousel-slide__title">{{ slide.title }}</div>
            </div>
          </ElCarouselItem>
        </ElCarousel>
        <div v-else class="carousel-empty">暂无图片</div>
      </div>
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
  import type { BoardComponent, BoardTableColumn } from './types'

  type TableColumnAlign = NonNullable<BoardTableColumn['align']>
  type RuntimeTableColumn = Required<Pick<BoardTableColumn, 'field' | 'label' | 'align'>> &
    Pick<BoardTableColumn, 'width'>

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
  const tableColumns = computed<RuntimeTableColumn[]>(() => {
    const first = tableRows.value[0] || {}
    const mapped = normalizeTableFields(mapping.value.tableFields)
    const configured = normalizeTableColumns(mapping.value.tableColumns)
    const configuredMap = new Map(configured.map((column) => [column.field, column]))
    const configuredFields = configured.map((column) => column.field)
    const available = Object.keys(first)
    const fields = mapped.length ? mapped : configuredFields.length ? configuredFields : available

    return fields
      .filter((field) => !available.length || available.includes(field))
      .slice(0, 8)
      .map((field) => {
        const config = configuredMap.get(field)

        return {
          field,
          label: config?.label || field,
          width: normalizeTableColumnWidth(config?.width),
          align: normalizeTableColumnAlign(config?.align)
        }
      })
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

  const carouselSlides = computed(() => {
    const slides = normalizeCarouselSlides()
    if (slides.length) return slides
    if (hasBoundDataset.value) return []

    return [
      { image: '', title: '示例轮播 1' },
      { image: '', title: '示例轮播 2' },
      { image: '', title: '示例轮播 3' }
    ]
  })
  const carouselInterval = computed(() =>
    normalizeCarouselInterval(props.component.option?.interval)
  )
  const carouselImageFit = computed(() =>
    normalizeCarouselImageFit(props.component.option?.imageFit)
  )
  const carouselIndicatorPosition = computed(() =>
    props.component.option?.showDots === false ? 'none' : ''
  )

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

  function normalizeTableFields(value: unknown): string[] {
    if (!Array.isArray(value)) return []

    return Array.from(
      new Set(value.map((field) => String(field || '').trim()).filter(Boolean))
    ).slice(0, 8)
  }

  function normalizeTableColumns(value: unknown): BoardTableColumn[] {
    if (!Array.isArray(value)) return []

    const columns: BoardTableColumn[] = []
    const seen = new Set<string>()
    for (const item of value) {
      const field =
        typeof item === 'string'
          ? item.trim()
          : String((item as Record<string, any>)?.field || '').trim()
      if (!field || seen.has(field)) continue
      seen.add(field)
      columns.push({
        field,
        label:
          typeof (item as Record<string, any>)?.label === 'string'
            ? String((item as Record<string, any>).label).trim()
            : '',
        width: normalizeTableColumnWidth((item as Record<string, any>)?.width),
        align: normalizeTableColumnAlign((item as Record<string, any>)?.align)
      })
    }

    return columns
  }

  function normalizeTableColumnWidth(value: unknown) {
    if (value === '' || value === null || value === undefined) return undefined
    const width = Number(value)
    if (!Number.isFinite(width) || width <= 0) return undefined
    return Math.min(600, Math.max(60, Math.round(width)))
  }

  function normalizeTableColumnAlign(value: unknown): TableColumnAlign {
    return value === 'center' || value === 'right' ? value : 'left'
  }

  function normalizeCarouselSlides() {
    if (!tableRows.value.length) return []

    const imageKey = findOptionalKey(props.component.option?.imageField, [
      'image',
      'image_url',
      'cover',
      'cover_url',
      'thumbnail',
      'thumb',
      'picture',
      'pic',
      'img',
      'poster',
      'poster_url',
      'banner',
      'banner_url',
      'src',
      'url',
      'avatar'
    ])
    if (!imageKey) return []

    const titleKey = findOptionalKey(props.component.option?.titleField, [
      'title',
      'name',
      'label',
      'summary',
      'description'
    ])

    return tableRows.value
      .map((row, index) => ({
        image: String(row[imageKey] ?? '').trim(),
        title: titleKey ? String(row[titleKey] ?? '').trim() : `#${index + 1}`
      }))
      .filter((slide) => slide.image)
      .slice(0, 20)
  }

  function normalizeCarouselInterval(value: unknown) {
    const interval = Number(value ?? 3000)
    if (!Number.isFinite(interval) || interval <= 0) return 3000
    return Math.min(60000, Math.max(1000, Math.round(interval)))
  }

  function normalizeCarouselImageFit(value: unknown) {
    return value === 'contain' || value === 'fill' ? value : 'cover'
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

  function findOptionalKey(mapped: string | undefined, preferred: string[]) {
    const first = tableRows.value[0] || {}
    if (mapped && mapped in first) return mapped
    return preferred.find((key) => key in first) || ''
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

  .image-carousel,
  :deep(.image-carousel .el-carousel),
  :deep(.image-carousel .el-carousel__container) {
    height: 100%;
  }

  .carousel-slide {
    position: relative;
    width: 100%;
    height: 100%;
    overflow: hidden;
    background:
      linear-gradient(135deg, rgb(104 166 255 / 22%), transparent 52%), rgb(7 17 31 / 86%);
    border-radius: 4px;
  }

  .carousel-slide__image {
    display: block;
    width: 100%;
    height: 100%;
  }

  .carousel-slide__fallback {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    padding: 16px;
    overflow: hidden;
    font-size: 20px;
    font-weight: 600;
    color: #f8fbff;
    text-align: center;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .carousel-slide__title {
    position: absolute;
    right: 0;
    bottom: 0;
    left: 0;
    padding: 18px 14px 10px;
    overflow: hidden;
    font-size: 14px;
    font-weight: 600;
    color: #fff;
    text-overflow: ellipsis;
    white-space: nowrap;
    background: linear-gradient(180deg, transparent, rgb(0 0 0 / 62%));
  }

  .carousel-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: rgb(215 231 255 / 72%);
    background: rgb(255 255 255 / 4%);
    border: 1px dashed rgb(255 255 255 / 16%);
    border-radius: 4px;
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
