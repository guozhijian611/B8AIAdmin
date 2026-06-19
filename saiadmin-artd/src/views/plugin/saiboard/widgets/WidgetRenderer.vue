<template>
  <div
    ref="widgetRef"
    class="board-widget"
    :class="{ 'is-runtime': runtime, 'is-decor': decorTypes.has(component.type) }"
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
      <ArtDualBarCompareChart
        v-else-if="component.type === 'art-dual-bar-compare-chart' && dualCompareReady"
        height="100%"
        :positive-data="dualPositiveData"
        :negative-data="dualNegativeData"
        :x-axis-data="axisData"
        :positive-name="component.option?.positiveName || '正向'"
        :negative-name="component.option?.negativeName || '负向'"
        :y-axis-min="dualAxisMin"
        :y-axis-max="dualAxisMax"
        v-bind="component.option"
      />
      <div v-else-if="component.type === 'art-dual-bar-compare-chart'" class="chart-empty">
        {{ dualCompareEmptyText }}
      </div>
      <ArtKLineChart
        v-else-if="component.type === 'art-k-line-chart' && kLineData.length"
        height="100%"
        :data="kLineData"
        v-bind="component.option"
      />
      <div v-else-if="component.type === 'art-k-line-chart'" class="chart-empty">
        {{ kLineEmptyText }}
      </div>
      <ArtGaugeChart
        v-else-if="component.type === 'art-gauge-chart'"
        height="100%"
        v-bind="component.option"
        :value="gaugeValue"
        :name="gaugeName"
      />
      <ArtFunnelChart
        v-else-if="component.type === 'art-funnel-chart'"
        height="100%"
        :data="ringData"
        v-bind="component.option"
      />
      <ArtHeatmapChart
        v-else-if="component.type === 'art-heatmap-chart' && heatmapReady"
        height="100%"
        :data="heatmapData"
        :x-axis-data="heatmapXAxisData"
        :y-axis-data="heatmapYAxisData"
        v-bind="component.option"
      />
      <div v-else-if="component.type === 'art-heatmap-chart'" class="chart-empty">
        {{ heatmapEmptyText }}
      </div>
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
        v-else-if="component.type === 'event-timeline'"
        class="event-timeline"
        :style="timelineStyleVars"
      >
        <template v-if="timelineItems.length">
          <div
            v-for="(item, index) in timelineItems"
            :key="`${item.time}-${item.title}-${index}`"
            class="event-timeline__item"
            :class="`is-${item.tone}`"
          >
            <span class="event-timeline__dot"></span>
            <div class="event-timeline__content">
              <div class="event-timeline__meta">
                <span v-if="component.option?.showTime !== false" class="event-timeline__time">
                  {{ item.time }}
                </span>
                <span v-if="item.status" class="event-timeline__status">{{ item.status }}</span>
              </div>
              <div class="event-timeline__title">{{ item.title }}</div>
              <div
                v-if="component.option?.showContent !== false && item.content"
                class="event-timeline__desc"
              >
                {{ item.content }}
              </div>
            </div>
          </div>
        </template>
        <div v-else class="timeline-empty">{{ timelineEmptyText }}</div>
      </div>
      <div v-else-if="component.type === 'geo-point-map'" class="geo-map">
        <div class="geo-map__grid"></div>
        <div class="geo-map__region">{{ geoRegionName }}</div>
        <template v-if="geoPoints.length">
          <div
            v-for="(point, index) in geoPoints"
            :key="`${point.name}-${index}`"
            class="geo-map__point"
            :style="geoPointStyle(point)"
          >
            <span class="geo-map__dot"></span>
            <span v-if="point.name && component.option?.showLabel !== false" class="geo-map__label">
              {{ point.name }}
              <strong v-if="point.value !== ''">{{ point.value }}</strong>
            </span>
          </div>
        </template>
        <div v-else class="geo-map__empty">{{ geoMapEmptyText }}</div>
      </div>
      <div
        v-else-if="component.type === 'decor-border'"
        class="decor-border"
        :class="`is-${decorStyle}`"
        :style="decorStyleVars"
      ></div>
      <div
        v-else-if="component.type === 'decor-scanline'"
        class="decor-scanline"
        :class="`is-${scanlineDirection}`"
        :style="scanlineStyleVars"
      >
        <span class="decor-scanline__beam"></span>
      </div>
      <div
        v-else-if="component.type === 'decor-title'"
        class="decor-title"
        :class="[`is-${decorTitleVariant}`, `is-${decorTitleAlign}`]"
        :style="decorTitleStyleVars"
      >
        <div class="decor-title__content">
          <div class="decor-title__text">{{ decorTitleText }}</div>
          <div v-if="decorTitleSubtitle" class="decor-title__subtitle">
            {{ decorTitleSubtitle }}
          </div>
        </div>
      </div>
      <div
        v-else-if="component.type === 'decor-divider'"
        class="decor-divider"
        :class="[`is-${dividerDirection}`, `is-${dividerVariant}`]"
        :style="dividerStyleVars"
      >
        <span class="decor-divider__line"></span>
      </div>
      <div v-else class="unsupported">组件不可用</div>
    </div>
    <div v-if="error" class="widget-error">{{ error }}</div>
  </div>
</template>

<script setup lang="ts">
  import ArtBarChart from '@/components/core/charts/art-bar-chart/index.vue'
  import ArtDualBarCompareChart from '@/components/core/charts/art-dual-bar-compare-chart/index.vue'
  import ArtFunnelChart from '@/components/core/charts/art-funnel-chart/index.vue'
  import ArtGaugeChart from '@/components/core/charts/art-gauge-chart/index.vue'
  import ArtHBarChart from '@/components/core/charts/art-h-bar-chart/index.vue'
  import ArtHeatmapChart from '@/components/core/charts/art-heatmap-chart/index.vue'
  import ArtKLineChart from '@/components/core/charts/art-k-line-chart/index.vue'
  import ArtLineChart from '@/components/core/charts/art-line-chart/index.vue'
  import ArtRadarChart from '@/components/core/charts/art-radar-chart/index.vue'
  import ArtRingChart from '@/components/core/charts/art-ring-chart/index.vue'
  import ArtScatterChart from '@/components/core/charts/art-scatter-chart/index.vue'
  import type { HeatmapDataItem, KLineDataItem } from '@/types/component/chart'
  import { decorWidgetTypes } from './registry'
  import type { BoardComponent, BoardTableColumn } from './types'

  type TableColumnAlign = NonNullable<BoardTableColumn['align']>
  type RuntimeTableColumn = Required<Pick<BoardTableColumn, 'field' | 'label' | 'align'>> &
    Pick<BoardTableColumn, 'width'>
  interface GeoPoint {
    x: number
    y: number
    name: string
    value: string
    size: number
  }
  interface TimelineItem {
    time: string
    title: string
    content: string
    status: string
    tone: 'primary' | 'success' | 'warning' | 'danger' | 'info'
    timestamp: number
    index: number
  }

  const chartTypes = new Set([
    'art-bar-chart',
    'art-line-chart',
    'art-h-bar-chart',
    'art-dual-bar-compare-chart',
    'art-k-line-chart',
    'art-gauge-chart',
    'art-funnel-chart',
    'art-heatmap-chart',
    'art-ring-chart',
    'art-radar-chart',
    'art-scatter-chart'
  ])
  const decorTypes = decorWidgetTypes

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
  const sampleKLineData: KLineDataItem[] = [
    { time: '周一', open: 102, close: 108, high: 112, low: 98 },
    { time: '周二', open: 108, close: 104, high: 111, low: 101 },
    { time: '周三', open: 104, close: 116, high: 120, low: 103 },
    { time: '周四', open: 116, close: 121, high: 128, low: 113 },
    { time: '周五', open: 121, close: 118, high: 126, low: 115 }
  ]
  const sampleHeatmapXAxisData = ['周一', '周二', '周三', '周四', '周五']
  const sampleHeatmapYAxisData = ['浏览', '下单', '支付']
  const sampleHeatmapData: HeatmapDataItem[] = [
    { value: [0, 0, 120] },
    { value: [1, 0, 180] },
    { value: [2, 0, 150] },
    { value: [3, 0, 220] },
    { value: [4, 0, 260] },
    { value: [0, 1, 42] },
    { value: [1, 1, 58] },
    { value: [2, 1, 46] },
    { value: [3, 1, 74] },
    { value: [4, 1, 88] },
    { value: [0, 2, 18] },
    { value: [1, 2, 26] },
    { value: [2, 2, 21] },
    { value: [3, 2, 32] },
    { value: [4, 2, 39] }
  ]
  const sampleTimelineItems: TimelineItem[] = [
    {
      time: '09:00',
      title: '数据源连接',
      content: '完成订单库连接测试',
      status: '成功',
      tone: 'success',
      timestamp: 9 * 3600,
      index: 0
    },
    {
      time: '10:30',
      title: '订单同步',
      content: '同步最近 1 小时订单数据',
      status: '进行中',
      tone: 'primary',
      timestamp: 10 * 3600 + 30 * 60,
      index: 1
    },
    {
      time: '11:20',
      title: '支付告警',
      content: '微信支付失败率超过阈值',
      status: '告警',
      tone: 'warning',
      timestamp: 11 * 3600 + 20 * 60,
      index: 2
    }
  ]

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
  const dualPositiveKey = computed(() =>
    findOptionalKey(props.component.option?.positiveField, [
      'positive',
      'in',
      'income',
      'current',
      'value',
      'count',
      'total',
      'amount'
    ])
  )
  const dualEffectivePositiveKey = computed(
    () => dualPositiveKey.value || valueKeys.value[0] || valueKey.value
  )
  const dualNegativeKey = computed(() =>
    findOptionalKey(props.component.option?.negativeField, [
      'negative',
      'out',
      'expense',
      'previous',
      'last',
      'contrast',
      'compare'
    ])
  )

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
  const dualPositiveData = computed(() => {
    if (!tableRows.value.length) return hasBoundDataset.value ? [] : [12, 28, 19, 35, 24]
    return tableRows.value.map((row) => toFiniteNumber(row[dualEffectivePositiveKey.value]))
  })
  const dualNegativeData = computed(() => {
    if (!tableRows.value.length) return hasBoundDataset.value ? [] : [8, 16, 13, 21, 18]
    const key =
      dualNegativeKey.value ||
      valueKeys.value.find((item) => item !== dualEffectivePositiveKey.value)
    if (key) return tableRows.value.map((row) => toFiniteNumber(row[key]))
    return []
  })
  const dualAxisMax = computed(() => {
    const configured = Number(props.component.option?.yAxisMax)
    if (Number.isFinite(configured) && configured > 0) return configured
    const values = [...dualPositiveData.value, ...dualNegativeData.value.map(Math.abs)].filter(
      (value) => Number.isFinite(value)
    )
    const max = Math.max(1, ...values)
    return Math.ceil(max * 1.2)
  })
  const dualAxisMin = computed(() => -dualAxisMax.value)
  const dualCompareReady = computed(
    () => dualPositiveData.value.length > 0 && dualNegativeData.value.length > 0
  )
  const dualCompareEmptyText = computed(() =>
    hasBoundDataset.value ? '请配置两个数值字段' : '暂无对比数据'
  )
  const kLineKeys = computed(() => ({
    time: findOptionalKey(props.component.option?.timeField, [
      'time',
      'date',
      'day',
      'trade_date',
      'created_at',
      'create_time',
      'label',
      '名称'
    ]),
    open: findOptionalKey(props.component.option?.openField, [
      'open',
      'open_price',
      'opening',
      '开盘',
      '开盘价'
    ]),
    close: findOptionalKey(props.component.option?.closeField, [
      'close',
      'close_price',
      'closing',
      '收盘',
      '收盘价'
    ]),
    high: findOptionalKey(props.component.option?.highField, [
      'high',
      'high_price',
      'highest',
      '最高',
      '最高价'
    ]),
    low: findOptionalKey(props.component.option?.lowField, [
      'low',
      'low_price',
      'lowest',
      '最低',
      '最低价'
    ])
  }))
  const kLineData = computed<KLineDataItem[]>(() => {
    if (!tableRows.value.length) return hasBoundDataset.value ? [] : sampleKLineData
    const keys = kLineKeys.value
    if (!keys.time || !keys.open || !keys.close || !keys.high || !keys.low) {
      return hasBoundDataset.value ? [] : sampleKLineData
    }

    const rows = tableRows.value
      .map((row, index) => {
        const open = toOptionalNumber(row[keys.open])
        const close = toOptionalNumber(row[keys.close])
        const high = toOptionalNumber(row[keys.high])
        const low = toOptionalNumber(row[keys.low])
        if ([open, close, high, low].some((value) => value === undefined)) return undefined

        return {
          time: String(row[keys.time] ?? `#${index + 1}`),
          open,
          close,
          high,
          low
        }
      })
      .filter(Boolean) as KLineDataItem[]

    return rows.length || hasBoundDataset.value ? rows : sampleKLineData
  })
  const kLineEmptyText = computed(() => {
    if (!hasBoundDataset.value || !tableRows.value.length) return '暂无K线数据'
    const keys = kLineKeys.value
    if (!keys.time || !keys.open || !keys.close || !keys.high || !keys.low) {
      return '请配置时间/开盘/收盘/最高/最低字段'
    }
    return '暂无有效K线数据'
  })
  const gaugeValue = computed(() => {
    if (!hasBoundDataset.value) return 72
    if (!tableRows.value.length) return Number.NaN
    return toOptionalNumber(tableRows.value[0]?.[valueKey.value]) ?? Number.NaN
  })
  const gaugeName = computed(() => {
    const configuredName = String(props.component.option?.name || '').trim()
    if (configuredName) return configuredName
    if (hasBoundDataset.value && tableRows.value.length) {
      const label = String(tableRows.value[0]?.[axisKey.value] ?? '').trim()
      if (label) return label
    }
    return props.component.title || '指标'
  })
  const heatmapXKey = computed(() =>
    findDimensionKey(props.component.option?.xField, [
      'x',
      'date',
      'day',
      'time',
      'hour',
      'label',
      'category',
      '日期',
      '时间'
    ])
  )
  const heatmapYKey = computed(() =>
    findDimensionKey(
      props.component.option?.yField,
      ['y', 'type', 'status', 'group', 'series', 'channel', 'category2', 'dimension', '类型'],
      [heatmapXKey.value]
    )
  )
  const heatmapValueKey = computed(
    () =>
      findOptionalKey(mapping.value.valueField, [
        'value',
        'count',
        'total',
        'amount',
        'num',
        '数量'
      ]) ||
      valueKeys.value.find((key) => key !== heatmapXKey.value && key !== heatmapYKey.value) ||
      valueKey.value
  )
  const heatmapXAxisData = computed(() => {
    if (!tableRows.value.length) return hasBoundDataset.value ? [] : sampleHeatmapXAxisData
    if (!heatmapXKey.value) return []
    return uniqueStringValues(tableRows.value, heatmapXKey.value)
  })
  const heatmapYAxisData = computed(() => {
    if (!tableRows.value.length) return hasBoundDataset.value ? [] : sampleHeatmapYAxisData
    if (!heatmapYKey.value) return []
    return uniqueStringValues(tableRows.value, heatmapYKey.value)
  })
  const heatmapData = computed<HeatmapDataItem[]>(() => {
    if (!tableRows.value.length) return hasBoundDataset.value ? [] : sampleHeatmapData
    if (!heatmapXKey.value || !heatmapYKey.value) return []

    const xIndexMap = new Map(heatmapXAxisData.value.map((name, index) => [name, index]))
    const yIndexMap = new Map(heatmapYAxisData.value.map((name, index) => [name, index]))
    const valueMap = new Map<string, number>()

    for (const row of tableRows.value) {
      const xName = String(row[heatmapXKey.value] ?? '').trim()
      const yName = String(row[heatmapYKey.value] ?? '').trim()
      const xIndex = xIndexMap.get(xName)
      const yIndex = yIndexMap.get(yName)
      if (xIndex === undefined || yIndex === undefined) continue
      const key = `${xIndex}:${yIndex}`
      valueMap.set(key, (valueMap.get(key) || 0) + toFiniteNumber(row[heatmapValueKey.value]))
    }

    return Array.from(valueMap.entries()).map(([key, value]) => {
      const [x, y] = key.split(':').map(Number)
      return { value: [x, y, value] }
    })
  })
  const heatmapReady = computed(
    () =>
      heatmapXAxisData.value.length > 0 &&
      heatmapYAxisData.value.length > 0 &&
      heatmapData.value.length > 0
  )
  const heatmapEmptyText = computed(() => {
    if (!hasBoundDataset.value || !tableRows.value.length) return '暂无热力图数据'
    if (!heatmapXKey.value || !heatmapYKey.value) return '请配置 X/Y 字段'
    return '暂无有效热力图数据'
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
  const timelineTimeKey = computed(() =>
    findOptionalKey(props.component.option?.timeField, [
      'time',
      'date',
      'datetime',
      'event_time',
      'created_at',
      'create_time',
      'updated_at',
      'published_at',
      'timestamp',
      '日期',
      '时间'
    ])
  )
  const timelineTitleKey = computed(() =>
    findOptionalKey(props.component.option?.titleField, [
      'title',
      'name',
      'label',
      'event',
      'action',
      'subject',
      'order_name',
      '标题',
      '名称'
    ])
  )
  const timelineContentKey = computed(() =>
    findOptionalKey(props.component.option?.contentField, [
      'content',
      'description',
      'desc',
      'summary',
      'remark',
      'message',
      'body',
      '内容',
      '说明'
    ])
  )
  const timelineStatusKey = computed(() =>
    findOptionalKey(props.component.option?.statusField, [
      'status',
      'state',
      'type',
      'level',
      'result',
      '状态',
      '类型'
    ])
  )
  const timelineItems = computed<TimelineItem[]>(() => {
    if (!hasBoundDataset.value && !(props.rows || []).length) return sampleTimelineItems
    if (!timelineTimeKey.value || !timelineTitleKey.value) return []

    const rows = props.rows || []
    const items = rows
      .map((row, index) => createTimelineItem(row, index))
      .filter(Boolean) as TimelineItem[]

    const sortOrder = String(props.component.option?.sortOrder || 'desc')
    const sorted = items.sort((a, b) => {
      const left = Number.isFinite(a.timestamp) ? a.timestamp : a.index
      const right = Number.isFinite(b.timestamp) ? b.timestamp : b.index
      return sortOrder === 'asc' ? left - right : right - left
    })
    const maxRows = normalizeTimelineMaxRows(props.component.option?.maxRows)
    return maxRows > 0 ? sorted.slice(0, maxRows) : sorted
  })
  const timelineEmptyText = computed(() => {
    if (!hasBoundDataset.value || !(props.rows || []).length) return '暂无事件'
    if (!timelineTimeKey.value || !timelineTitleKey.value) return '请配置时间/标题字段'
    return '暂无有效事件'
  })
  const timelineStyleVars = computed(() => ({
    '--timeline-accent': String(props.component.option?.accent || 'var(--saiboard-accent, #69b7ff)')
  }))
  const geoPoints = computed(() => {
    const points = normalizeGeoPoints()
    if (points.length) return points
    if (hasBoundDataset.value) return []

    return [
      createGeoPoint(116.4, 39.9, '北京', '128'),
      createGeoPoint(121.47, 31.23, '上海', '96'),
      createGeoPoint(113.26, 23.13, '广州', '88')
    ].filter(Boolean) as GeoPoint[]
  })
  const geoRegionName = computed(() =>
    props.component.option?.region === 'world' ? 'World' : 'China'
  )
  const geoMapEmptyText = computed(() => {
    if (!hasBoundDataset.value || !tableRows.value.length) return '暂无点位'
    if (!hasGeoCoordinateFields()) return '未识别经纬度字段'
    return '暂无有效点位'
  })

  const decorStyle = computed(() => {
    const value = String(props.component.option?.borderStyle || 'corner')
    return ['corner', 'line', 'glow'].includes(value) ? value : 'corner'
  })
  const decorStyleVars = computed(() => ({
    '--decor-accent': String(props.component.option?.accent || 'var(--saiboard-accent, #69b7ff)'),
    '--decor-opacity': String(normalizeOpacity(props.component.option?.opacity))
  }))
  const scanlineDirection = computed(() => {
    const value = String(props.component.option?.direction || 'horizontal')
    return value === 'vertical' ? 'vertical' : 'horizontal'
  })
  const scanlineStyleVars = computed(() => ({
    '--scanline-accent': String(
      props.component.option?.accent || 'var(--saiboard-accent, #23d8ff)'
    ),
    '--scanline-opacity': String(normalizeOpacity(props.component.option?.opacity ?? 0.68)),
    '--scanline-duration': `${normalizeScanlineSpeed(props.component.option?.speed)}s`
  }))
  const decorTitleText = computed(() =>
    normalizeDecorText(props.component.option?.text || props.component.title, '数据总览')
  )
  const decorTitleSubtitle = computed(() =>
    normalizeDecorText(props.component.option?.subtitle, '')
  )
  const decorTitleVariant = computed(() => {
    const value = String(props.component.option?.variant || 'bar')
    return ['bar', 'glow', 'bracket'].includes(value) ? value : 'bar'
  })
  const decorTitleAlign = computed(() => {
    const value = String(props.component.option?.align || 'center')
    return ['left', 'center', 'right'].includes(value) ? value : 'center'
  })
  const decorTitleStyleVars = computed(() => ({
    '--decor-title-accent': String(
      props.component.option?.accent || 'var(--saiboard-accent, #69b7ff)'
    ),
    '--decor-title-align': decorTitleAlign.value
  }))
  const dividerDirection = computed(() => {
    const value = String(props.component.option?.direction || 'horizontal')
    return value === 'vertical' ? 'vertical' : 'horizontal'
  })
  const dividerVariant = computed(() => {
    const value = String(props.component.option?.variant || 'pulse')
    return ['line', 'double', 'pulse'].includes(value) ? value : 'pulse'
  })
  const dividerStyleVars = computed(() => ({
    '--decor-divider-accent': String(
      props.component.option?.accent || 'var(--saiboard-accent, #23d8ff)'
    ),
    '--decor-divider-opacity': String(normalizeOpacity(props.component.option?.opacity ?? 0.72)),
    '--decor-divider-duration': `${normalizeScanlineSpeed(props.component.option?.speed)}s`
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

  function normalizeScanlineSpeed(value: unknown) {
    const speed = Number(value ?? 4)
    if (!Number.isFinite(speed) || speed <= 0) return 4
    return Math.min(12, Math.max(1, speed))
  }

  function normalizeDecorText(value: unknown, fallback: string) {
    const text = String(value ?? '').trim()
    return text || fallback
  }

  function toFiniteNumber(value: unknown, fallback = 0) {
    const normalized = typeof value === 'string' ? value.replace(/,/g, '').trim() : value
    const number = Number(normalized)
    return Number.isFinite(number) ? number : fallback
  }

  function toOptionalNumber(value: unknown) {
    const normalized = typeof value === 'string' ? value.replace(/,/g, '').trim() : value
    const number = Number(normalized)
    return Number.isFinite(number) ? number : undefined
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

  function uniqueStringValues(rows: Record<string, any>[], field: string, limit = 80) {
    const values: string[] = []
    const seen = new Set<string>()
    for (const row of rows) {
      const value = String(row[field] ?? '').trim()
      if (!value || seen.has(value)) continue
      seen.add(value)
      values.push(value)
      if (values.length >= limit) break
    }
    return values
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

  function createTimelineItem(row: Record<string, any>, index: number) {
    const rawTime = String(row[timelineTimeKey.value] ?? '').trim()
    const title = String(row[timelineTitleKey.value] ?? '').trim()
    if (!rawTime || !title) return undefined

    const status = timelineStatusKey.value ? String(row[timelineStatusKey.value] ?? '').trim() : ''

    return {
      time: rawTime,
      title,
      content: timelineContentKey.value ? String(row[timelineContentKey.value] ?? '').trim() : '',
      status,
      tone: timelineTone(status),
      timestamp: parseTimelineTimestamp(rawTime),
      index
    }
  }

  function normalizeTimelineMaxRows(value: unknown) {
    const maxRows = Number(value ?? 8)
    if (!Number.isFinite(maxRows) || maxRows < 0) return 8
    return Math.min(50, Math.round(maxRows))
  }

  function parseTimelineTimestamp(value: string) {
    const numeric = Number(value)
    if (Number.isFinite(numeric)) return numeric > 100000000000 ? numeric : numeric * 1000
    const timeOnly = value.match(/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/)
    if (timeOnly) {
      const hours = Number(timeOnly[1])
      const minutes = Number(timeOnly[2])
      const seconds = Number(timeOnly[3] || 0)
      if (hours < 24 && minutes < 60 && seconds < 60) {
        return hours * 3600 + minutes * 60 + seconds
      }
    }
    const timestamp = Date.parse(value.replace(/-/g, '/'))
    return Number.isFinite(timestamp) ? timestamp : Number.NaN
  }

  function timelineTone(status: string): TimelineItem['tone'] {
    const normalized = status.toLowerCase()
    if (
      ['success', 'done', 'completed', 'ok', '1', '成功', '完成', '已完成'].includes(normalized)
    ) {
      return 'success'
    }
    if (['warning', 'warn', 'pending', '2', '告警', '警告', '待处理'].includes(normalized)) {
      return 'warning'
    }
    if (['error', 'fail', 'failed', 'danger', '0', '失败', '异常', '错误'].includes(normalized)) {
      return 'danger'
    }
    if (['info', 'notice', '通知', '提示'].includes(normalized)) return 'info'
    return 'primary'
  }

  function normalizeGeoPoints() {
    if (!tableRows.value.length) return []

    const lngKey = geoLongitudeKey()
    const latKey = geoLatitudeKey()
    if (!lngKey || !latKey) return []

    const nameKey = findOptionalKey(props.component.option?.nameField, [
      'name',
      'title',
      'label',
      'city',
      'province',
      'area',
      '名称'
    ])
    const geoValueKey = findOptionalKey(props.component.option?.valueField, [
      'value',
      'count',
      'total',
      'amount',
      'num',
      '数量'
    ])

    return tableRows.value
      .map((row, index) =>
        createGeoPoint(
          Number(row[lngKey]),
          Number(row[latKey]),
          nameKey ? String(row[nameKey] ?? '').trim() : `#${index + 1}`,
          geoValueKey ? String(row[geoValueKey] ?? '').trim() : ''
        )
      )
      .filter(Boolean) as GeoPoint[]
  }

  function hasGeoCoordinateFields() {
    return Boolean(geoLongitudeKey() && geoLatitudeKey())
  }

  function geoLongitudeKey() {
    return findOptionalKey(props.component.option?.lngField, [
      'lng',
      'lon',
      'longitude',
      'x',
      '经度'
    ])
  }

  function geoLatitudeKey() {
    return findOptionalKey(props.component.option?.latField, ['lat', 'latitude', 'y', '纬度'])
  }

  function createGeoPoint(lng: number, lat: number, name: string, value: string) {
    if (!Number.isFinite(lng) || !Number.isFinite(lat)) return undefined
    const bounds = geoBounds()
    if (lng < bounds.minLng || lng > bounds.maxLng || lat < bounds.minLat || lat > bounds.maxLat) {
      return undefined
    }

    return {
      x: ((lng - bounds.minLng) / (bounds.maxLng - bounds.minLng)) * 100,
      y: ((bounds.maxLat - lat) / (bounds.maxLat - bounds.minLat)) * 100,
      name,
      value,
      size: normalizeGeoPointSize(props.component.option?.pointSize)
    }
  }

  function geoBounds() {
    if (props.component.option?.region === 'world') {
      return { minLng: -180, maxLng: 180, minLat: -60, maxLat: 85 }
    }

    return { minLng: 73, maxLng: 135, minLat: 18, maxLat: 54 }
  }

  function normalizeGeoPointSize(value: unknown) {
    const size = Number(value ?? 12)
    if (!Number.isFinite(size) || size <= 0) return 12
    return Math.min(28, Math.max(6, Math.round(size)))
  }

  function geoPointStyle(point: GeoPoint) {
    return {
      left: `${point.x}%`,
      top: `${point.y}%`,
      '--geo-point-size': `${point.size}px`
    }
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

  function findDimensionKey(
    mapped: string | undefined,
    preferred: string[],
    exclude: string[] = []
  ) {
    const first = tableRows.value[0] || {}
    const excluded = new Set(exclude.filter(Boolean))
    if (mapped && mapped in first && !excluded.has(mapped)) return mapped

    for (const key of preferred) {
      if (key in first && !excluded.has(key)) return key
    }

    return (
      Object.keys(first).find(
        (key) =>
          !excluded.has(key) && tableRows.value.some((row) => !Number.isFinite(Number(row[key])))
      ) || ''
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
    min-width: 0;
    height: 100%;
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
    gap: 8px;
    align-items: baseline;
    justify-content: center;
    min-width: 0;
    height: 100%;
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

  .event-timeline {
    height: 100%;
    min-height: 0;
    padding: 2px 4px 2px 2px;
    overflow: auto;
  }

  .event-timeline__item {
    position: relative;
    display: grid;
    grid-template-columns: 18px minmax(0, 1fr);
    column-gap: 8px;
    padding-bottom: 13px;
    color: #d7e7ff;
  }

  .event-timeline__item::before {
    position: absolute;
    top: 12px;
    bottom: 0;
    left: 7px;
    width: 1px;
    content: '';
    background: rgb(104 166 255 / 22%);
  }

  .event-timeline__item:last-child {
    padding-bottom: 0;
  }

  .event-timeline__item:last-child::before {
    display: none;
  }

  .event-timeline__dot {
    position: relative;
    z-index: 1;
    width: 9px;
    height: 9px;
    margin-top: 5px;
    margin-left: 3px;
    background: var(--timeline-accent);
    border: 2px solid rgb(255 255 255 / 82%);
    border-radius: 50%;
    box-shadow: 0 0 12px color-mix(in srgb, var(--timeline-accent) 62%, transparent);
  }

  .event-timeline__item.is-success {
    --timeline-accent: #14deba;
  }

  .event-timeline__item.is-warning {
    --timeline-accent: #ffaf20;
  }

  .event-timeline__item.is-danger {
    --timeline-accent: #fa8a6c;
  }

  .event-timeline__item.is-info {
    --timeline-accent: #a8b4c6;
  }

  .event-timeline__content {
    min-width: 0;
    padding: 8px 10px;
    background: rgb(255 255 255 / 4%);
    border: 1px solid rgb(104 166 255 / 12%);
    border-radius: 4px;
  }

  .event-timeline__meta {
    display: flex;
    gap: 8px;
    align-items: center;
    justify-content: space-between;
    min-width: 0;
    margin-bottom: 4px;
    font-size: 12px;
    color: rgb(215 231 255 / 64%);
  }

  .event-timeline__time {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .event-timeline__status {
    flex: 0 0 auto;
    max-width: 80px;
    overflow: hidden;
    color: var(--timeline-accent);
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .event-timeline__title {
    min-width: 0;
    overflow: hidden;
    font-size: 14px;
    font-weight: 700;
    color: #f8fbff;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .event-timeline__desc {
    display: -webkit-box;
    margin-top: 4px;
    overflow: hidden;
    font-size: 12px;
    line-height: 1.45;
    color: rgb(215 231 255 / 72%);
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
  }

  .timeline-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: rgb(215 231 255 / 72%);
    background: rgb(255 255 255 / 4%);
    border: 1px dashed rgb(255 255 255 / 16%);
    border-radius: 4px;
  }

  .chart-empty {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: rgb(215 231 255 / 72%);
    background: rgb(255 255 255 / 4%);
    border: 1px dashed rgb(255 255 255 / 16%);
    border-radius: 4px;
  }

  .geo-map {
    position: relative;
    height: 100%;
    overflow: hidden;
    background:
      radial-gradient(circle at 50% 42%, rgb(104 166 255 / 18%), transparent 42%),
      linear-gradient(135deg, rgb(255 255 255 / 5%), transparent 44%), rgb(7 17 31 / 88%);
    border: 1px solid rgb(104 166 255 / 16%);
    border-radius: 4px;
  }

  .geo-map::before {
    position: absolute;
    inset: 12% 10%;
    pointer-events: none;
    content: '';
    border: 1px solid rgb(104 166 255 / 28%);
    border-radius: 44% 56% 48% 52%;
    box-shadow: inset 0 0 28px rgb(104 166 255 / 12%);
    transform: rotate(-8deg);
  }

  .geo-map__grid {
    position: absolute;
    inset: 0;
    pointer-events: none;
    background:
      linear-gradient(rgb(255 255 255 / 5%) 1px, transparent 1px),
      linear-gradient(90deg, rgb(255 255 255 / 5%) 1px, transparent 1px);
    background-size: 40px 40px;
    mask-image: radial-gradient(circle at center, #000 42%, transparent 78%);
  }

  .geo-map__region {
    position: absolute;
    top: 10px;
    left: 12px;
    font-size: 12px;
    font-weight: 700;
    color: rgb(215 231 255 / 68%);
    text-transform: uppercase;
    letter-spacing: 0;
  }

  .geo-map__point {
    position: absolute;
    z-index: 2;
    transform: translate(-50%, -50%);
  }

  .geo-map__dot {
    position: relative;
    display: block;
    width: var(--geo-point-size);
    height: var(--geo-point-size);
    background: var(--saiboard-accent, #8ab4f8);
    border: 2px solid rgb(255 255 255 / 86%);
    border-radius: 50%;
    box-shadow:
      0 0 0 5px rgb(104 166 255 / 18%),
      0 0 18px rgb(104 166 255 / 72%);
  }

  .geo-map__label {
    position: absolute;
    top: calc(var(--geo-point-size) + 5px);
    left: 50%;
    max-width: 120px;
    padding: 3px 6px;
    overflow: hidden;
    font-size: 12px;
    color: #f8fbff;
    text-overflow: ellipsis;
    white-space: nowrap;
    background: rgb(7 17 31 / 72%);
    border: 1px solid rgb(104 166 255 / 22%);
    border-radius: 4px;
    transform: translateX(-50%);
  }

  .geo-map__label strong {
    margin-left: 4px;
    color: var(--saiboard-accent, #8ab4f8);
  }

  .geo-map__empty {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: rgb(215 231 255 / 72%);
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
    border: 1px solid color-mix(in srgb, var(--decor-accent) 42%, transparent);
    border-radius: 4px;
    opacity: var(--decor-opacity);
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

  .decor-scanline {
    position: relative;
    width: 100%;
    height: 100%;
    overflow: hidden;
    background:
      linear-gradient(rgb(255 255 255 / 5%) 1px, transparent 1px),
      linear-gradient(90deg, rgb(255 255 255 / 5%) 1px, transparent 1px),
      radial-gradient(
        circle at 50% 50%,
        color-mix(in srgb, var(--scanline-accent) 18%, transparent),
        transparent 58%
      );
    background-size:
      28px 28px,
      28px 28px,
      100% 100%;
    border: 1px solid color-mix(in srgb, var(--scanline-accent) 24%, transparent);
    border-radius: 4px;
    box-shadow: inset 0 0 24px color-mix(in srgb, var(--scanline-accent) 14%, transparent);
    opacity: var(--scanline-opacity);
  }

  .decor-scanline__beam {
    position: absolute;
    display: block;
    pointer-events: none;
    background: var(--scanline-accent);
    box-shadow: 0 0 22px var(--scanline-accent);
  }

  .decor-scanline.is-horizontal .decor-scanline__beam {
    right: 0;
    left: 0;
    height: 2px;
    animation: saiboard-scanline-y var(--scanline-duration) linear infinite;
  }

  .decor-scanline.is-vertical .decor-scanline__beam {
    top: 0;
    bottom: 0;
    width: 2px;
    animation: saiboard-scanline-x var(--scanline-duration) linear infinite;
  }

  .decor-title {
    position: relative;
    display: flex;
    align-items: center;
    width: 100%;
    height: 100%;
    padding: 0 24px;
    overflow: hidden;
    color: #eff7ff;
    text-align: var(--decor-title-align);
  }

  .decor-title.is-left {
    justify-content: flex-start;
  }

  .decor-title.is-center {
    justify-content: center;
  }

  .decor-title.is-right {
    justify-content: flex-end;
  }

  .decor-title::before,
  .decor-title::after {
    position: absolute;
    pointer-events: none;
    content: '';
  }

  .decor-title__content {
    position: relative;
    z-index: 1;
    min-width: 0;
    max-width: 100%;
  }

  .decor-title__text {
    overflow: hidden;
    font-size: 26px;
    font-weight: 700;
    line-height: 1.18;
    text-overflow: ellipsis;
    text-shadow: 0 0 14px color-mix(in srgb, var(--decor-title-accent) 44%, transparent);
    letter-spacing: 0;
    white-space: nowrap;
  }

  .decor-title__subtitle {
    margin-top: 6px;
    overflow: hidden;
    font-size: 12px;
    line-height: 1.2;
    color: rgb(215 231 255 / 72%);
    text-overflow: ellipsis;
    letter-spacing: 0;
    white-space: nowrap;
  }

  .decor-title.is-bar {
    background:
      linear-gradient(
          90deg,
          transparent,
          color-mix(in srgb, var(--decor-title-accent) 18%, transparent),
          transparent
        )
        center bottom / 100% 1px no-repeat,
      linear-gradient(
        90deg,
        color-mix(in srgb, var(--decor-title-accent) 16%, transparent),
        transparent 28%,
        transparent 72%,
        color-mix(in srgb, var(--decor-title-accent) 16%, transparent)
      );
  }

  .decor-title.is-bar::after {
    right: 18px;
    bottom: 0;
    left: 18px;
    height: 2px;
    background: linear-gradient(90deg, transparent, var(--decor-title-accent), transparent);
  }

  .decor-title.is-glow {
    background: radial-gradient(
      circle at 50% 50%,
      color-mix(in srgb, var(--decor-title-accent) 22%, transparent),
      transparent 62%
    );
  }

  .decor-title.is-glow::after {
    inset: 10px 16px;
    border: 1px solid color-mix(in srgb, var(--decor-title-accent) 22%, transparent);
    box-shadow: inset 0 0 22px color-mix(in srgb, var(--decor-title-accent) 18%, transparent);
  }

  .decor-title.is-bracket::before,
  .decor-title.is-bracket::after {
    top: 20%;
    bottom: 20%;
    width: 28px;
    border-color: var(--decor-title-accent);
    border-style: solid;
  }

  .decor-title.is-bracket::before {
    left: 8px;
    border-width: 2px 0 2px 2px;
  }

  .decor-title.is-bracket::after {
    right: 8px;
    border-width: 2px 2px 2px 0;
  }

  .decor-divider {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    overflow: hidden;
    opacity: var(--decor-divider-opacity);
  }

  .decor-divider__line {
    position: absolute;
    display: block;
    background: linear-gradient(90deg, transparent, var(--decor-divider-accent), transparent);
    box-shadow: 0 0 18px color-mix(in srgb, var(--decor-divider-accent) 45%, transparent);
  }

  .decor-divider.is-horizontal .decor-divider__line {
    right: 12px;
    left: 12px;
    height: 2px;
  }

  .decor-divider.is-vertical .decor-divider__line {
    top: 12px;
    bottom: 12px;
    width: 2px;
    background: linear-gradient(180deg, transparent, var(--decor-divider-accent), transparent);
  }

  .decor-divider.is-double.is-horizontal .decor-divider__line {
    height: 1px;
    box-shadow:
      0 -6px 0 color-mix(in srgb, var(--decor-divider-accent) 42%, transparent),
      0 6px 0 color-mix(in srgb, var(--decor-divider-accent) 42%, transparent),
      0 0 18px color-mix(in srgb, var(--decor-divider-accent) 45%, transparent);
  }

  .decor-divider.is-double.is-vertical .decor-divider__line {
    width: 1px;
    box-shadow:
      -6px 0 0 color-mix(in srgb, var(--decor-divider-accent) 42%, transparent),
      6px 0 0 color-mix(in srgb, var(--decor-divider-accent) 42%, transparent),
      0 0 18px color-mix(in srgb, var(--decor-divider-accent) 45%, transparent);
  }

  .decor-divider.is-pulse .decor-divider__line {
    overflow: hidden;
  }

  .decor-divider.is-pulse .decor-divider__line::after {
    position: absolute;
    content: '';
    background: #fff;
    box-shadow: 0 0 18px #fff;
  }

  .decor-divider.is-pulse.is-horizontal .decor-divider__line::after {
    top: 0;
    bottom: 0;
    width: 72px;
    animation: saiboard-divider-pulse-x var(--decor-divider-duration) linear infinite;
  }

  .decor-divider.is-pulse.is-vertical .decor-divider__line::after {
    right: 0;
    left: 0;
    height: 72px;
    animation: saiboard-divider-pulse-y var(--decor-divider-duration) linear infinite;
  }

  @keyframes saiboard-scanline-y {
    from {
      top: -2px;
    }

    to {
      top: 100%;
    }
  }

  @keyframes saiboard-scanline-x {
    from {
      left: -2px;
    }

    to {
      left: 100%;
    }
  }

  @keyframes saiboard-divider-pulse-x {
    from {
      left: -72px;
    }

    to {
      left: 100%;
    }
  }

  @keyframes saiboard-divider-pulse-y {
    from {
      top: -72px;
    }

    to {
      top: 100%;
    }
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
