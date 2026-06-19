<!-- 热力图 -->
<template>
  <div
    ref="chartRef"
    class="relative w-full"
    :style="{ height: props.height }"
    v-loading="props.loading"
  ></div>
</template>

<script setup lang="ts">
  import type { EChartsOption } from '@/plugins/echarts'
  import { useChartOps, useChartComponent } from '@/hooks/core/useChart'
  import type { HeatmapChartProps, HeatmapDataItem } from '@/types/component/chart'

  defineOptions({ name: 'ArtHeatmapChart' })

  const props = withDefaults(defineProps<HeatmapChartProps>(), {
    height: useChartOps().chartHeight,
    loading: false,
    isEmpty: false,
    colors: () => useChartOps().colors,
    data: () => [],
    xAxisData: () => [],
    yAxisData: () => [],
    showLabel: false,
    showVisualMap: true,
    showAxisLabel: true,
    showAxisLine: true,
    showSplitLine: true,
    min: 0
  })

  const chartData = computed(() => normalizeHeatmapData(props.data || []))
  const maxValue = computed(() => {
    const configured = Number(props.max)
    if (Number.isFinite(configured) && configured > Number(props.min ?? 0)) return configured
    const max = Math.max(1, ...chartData.value.map((item) => item.value[2]))
    return Math.ceil(max)
  })
  const minValue = computed(() => {
    const configured = Number(props.min ?? 0)
    return Number.isFinite(configured) ? configured : 0
  })
  const heatmapColors = computed(() => {
    const colors = props.colors?.length ? props.colors : useChartOps().colors
    return [colors[2] || '#EDF2FF', colors[1] || '#4ABEFF', colors[0] || '#1677ff']
  })

  const {
    chartRef,
    isDark,
    getAxisLineStyle,
    getAxisLabelStyle,
    getAxisTickStyle,
    getSplitLineStyle,
    getAnimationConfig,
    getTooltipStyle
  } = useChartComponent({
    props,
    checkEmpty: () =>
      !props.xAxisData?.length || !props.yAxisData?.length || !chartData.value.length,
    watchSources: [
      () => props.data,
      () => props.xAxisData,
      () => props.yAxisData,
      () => props.colors,
      () => props.showLabel,
      () => props.showVisualMap,
      () => props.showAxisLabel,
      () => props.showAxisLine,
      () => props.showSplitLine,
      () => props.min,
      () => props.max
    ],
    generateOptions: (): EChartsOption => {
      const visualMapBottom = props.showVisualMap ? 22 : 8

      return {
        grid: {
          top: 18,
          right: 18,
          bottom: props.showVisualMap ? 58 : visualMapBottom,
          left: 10,
          containLabel: true
        },
        tooltip: getTooltipStyle('item', {
          formatter: (params: { value?: unknown }) => {
            const [xIndex, yIndex, value] = getCallbackValue(params.value)
            const xName = props.xAxisData?.[xIndex] || xIndex
            const yName = props.yAxisData?.[yIndex] || yIndex
            return `${xName}<br/>${yName}: ${value}`
          }
        }),
        visualMap: props.showVisualMap
          ? {
              min: minValue.value,
              max: maxValue.value,
              calculable: true,
              orient: 'horizontal',
              left: 'center',
              bottom: 0,
              textStyle: {
                color: isDark.value ? '#dce8ff' : '#3f4b5f'
              },
              inRange: {
                color: heatmapColors.value
              }
            }
          : undefined,
        xAxis: {
          type: 'category',
          data: props.xAxisData,
          axisTick: getAxisTickStyle(),
          axisLine: getAxisLineStyle(props.showAxisLine),
          axisLabel: {
            ...getAxisLabelStyle(props.showAxisLabel),
            interval: 0
          },
          splitArea: {
            show: true
          }
        },
        yAxis: {
          type: 'category',
          data: props.yAxisData,
          axisTick: getAxisTickStyle(),
          axisLine: getAxisLineStyle(props.showAxisLine),
          axisLabel: getAxisLabelStyle(props.showAxisLabel),
          splitLine: getSplitLineStyle(props.showSplitLine),
          splitArea: {
            show: true
          }
        },
        series: [
          {
            type: 'heatmap',
            data: chartData.value,
            label: {
              show: props.showLabel,
              color: '#fff',
              fontSize: 12,
              formatter: (params: { value?: unknown }) => String(getCallbackValue(params.value)[2])
            },
            emphasis: {
              itemStyle: {
                shadowBlur: 10,
                shadowColor: 'rgba(0, 0, 0, 0.25)'
              }
            },
            itemStyle: {
              borderColor: isDark.value ? 'rgba(10, 23, 40, 0.8)' : 'rgba(255, 255, 255, 0.8)',
              borderWidth: 1
            },
            ...getAnimationConfig(20, 1200)
          }
        ]
      }
    }
  })

  function normalizeHeatmapData(data: HeatmapDataItem[]) {
    return data
      .map((item) => {
        const [x, y, value] = item.value || []
        return {
          value: [toIndexNumber(x), toIndexNumber(y), toFiniteNumber(value)] as [
            number,
            number,
            number
          ]
        }
      })
      .filter(
        (item) =>
          item.value[0] >= 0 &&
          item.value[1] >= 0 &&
          item.value[0] < (props.xAxisData?.length || 0) &&
          item.value[1] < (props.yAxisData?.length || 0)
      )
  }

  function toIndexNumber(value: unknown) {
    const number = Number(value)
    return Number.isFinite(number) ? Math.max(0, Math.round(number)) : -1
  }

  function toFiniteNumber(value: unknown) {
    const normalized = typeof value === 'string' ? value.replace(/,/g, '').trim() : value
    const number = Number(normalized)
    return Number.isFinite(number) ? number : 0
  }

  function getCallbackValue(value: unknown): [number, number, number] {
    if (!Array.isArray(value)) return [0, 0, 0]
    return [toIndexNumber(value[0]), toIndexNumber(value[1]), toFiniteNumber(value[2])]
  }
</script>
