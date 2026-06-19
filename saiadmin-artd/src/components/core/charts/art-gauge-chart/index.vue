<!-- 仪表盘 -->
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
  import type { GaugeChartProps } from '@/types/component/chart'

  defineOptions({ name: 'ArtGaugeChart' })

  const props = withDefaults(defineProps<GaugeChartProps>(), {
    height: useChartOps().chartHeight,
    loading: false,
    isEmpty: false,
    colors: () => useChartOps().colors,
    value: 0,
    name: '完成率',
    min: 0,
    max: 100,
    unit: '%',
    decimals: 0,
    showPointer: true,
    showProgress: true
  })

  const normalizedMin = computed(() => normalizeNumber(props.min, 0))
  const normalizedMax = computed(() => {
    const max = normalizeNumber(props.max, 100)
    return max > normalizedMin.value ? max : normalizedMin.value + 100
  })
  const normalizedValue = computed(() =>
    clampNumber(normalizeNumber(props.value, Number.NaN), normalizedMin.value, normalizedMax.value)
  )
  const normalizedDecimals = computed(() => {
    const decimals = Number(props.decimals ?? 0)
    if (!Number.isFinite(decimals)) return 0
    return Math.min(6, Math.max(0, Math.round(decimals)))
  })
  const chartColors = computed(() => (props.colors?.length ? props.colors : useChartOps().colors))

  const { chartRef, isDark, getAnimationConfig, getTooltipStyle } = useChartComponent({
    props,
    checkEmpty: () => !Number.isFinite(Number(props.value)),
    watchSources: [
      () => props.value,
      () => props.name,
      () => props.min,
      () => props.max,
      () => props.unit,
      () => props.decimals,
      () => props.showPointer,
      () => props.showProgress,
      () => props.colors
    ],
    generateOptions: (): EChartsOption => {
      const primaryColor = chartColors.value[0] || '#4c87f3'
      const labelColor = isDark.value ? '#dce8ff' : '#3f4b5f'
      const axisLabelColor = isDark.value ? 'rgba(220, 232, 255, 0.66)' : '#8a94a6'
      const trackColor = isDark.value ? 'rgba(255, 255, 255, 0.12)' : '#edf2ff'

      return {
        tooltip: getTooltipStyle('item', {
          formatter: `{b}: {c}${props.unit || ''}`
        }),
        series: [
          {
            type: 'gauge',
            min: normalizedMin.value,
            max: normalizedMax.value,
            startAngle: 210,
            endAngle: -30,
            radius: '92%',
            center: ['50%', '55%'],
            splitNumber: 5,
            progress: {
              show: props.showProgress,
              width: 14,
              roundCap: true,
              itemStyle: {
                color: primaryColor
              }
            },
            axisLine: {
              roundCap: true,
              lineStyle: {
                width: 14,
                color: [[1, trackColor]]
              }
            },
            axisTick: {
              distance: -22,
              length: 5,
              lineStyle: {
                color: axisLabelColor,
                width: 1
              }
            },
            splitLine: {
              distance: -25,
              length: 10,
              lineStyle: {
                color: axisLabelColor,
                width: 2
              }
            },
            axisLabel: {
              distance: -12,
              color: axisLabelColor,
              fontSize: 11
            },
            pointer: {
              show: props.showPointer,
              length: '58%',
              width: 4,
              itemStyle: {
                color: primaryColor
              }
            },
            anchor: {
              show: props.showPointer,
              showAbove: true,
              size: 9,
              itemStyle: {
                color: primaryColor
              }
            },
            title: {
              offsetCenter: [0, '54%'],
              color: axisLabelColor,
              fontSize: 12,
              fontWeight: 500
            },
            detail: {
              valueAnimation: true,
              offsetCenter: [0, '28%'],
              color: labelColor,
              fontSize: 26,
              fontWeight: 700,
              formatter: (value: number) =>
                `${value.toFixed(normalizedDecimals.value)}${props.unit || ''}`
            },
            data: [
              {
                value: normalizedValue.value,
                name: props.name || ''
              }
            ],
            ...getAnimationConfig(0, 1200)
          }
        ]
      }
    }
  })

  function normalizeNumber(value: unknown, fallback: number) {
    const normalized = typeof value === 'string' ? value.replace(/,/g, '').trim() : value
    const number = Number(normalized)
    return Number.isFinite(number) ? number : fallback
  }

  function clampNumber(value: number, min: number, max: number) {
    if (!Number.isFinite(value)) return value
    return Math.min(max, Math.max(min, value))
  }
</script>
