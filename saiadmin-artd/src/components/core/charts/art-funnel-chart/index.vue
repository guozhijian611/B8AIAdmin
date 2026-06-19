<!-- 漏斗图 -->
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
  import type { FunnelChartProps } from '@/types/component/chart'

  defineOptions({ name: 'ArtFunnelChart' })

  const props = withDefaults(defineProps<FunnelChartProps>(), {
    height: useChartOps().chartHeight,
    loading: false,
    isEmpty: false,
    colors: () => useChartOps().colors,
    data: () => [],
    showTooltip: true,
    showLegend: false,
    legendPosition: 'right',
    showLabel: true,
    sort: 'descending',
    minSize: '20%',
    maxSize: '82%',
    gap: 4
  })

  const chartData = computed(() =>
    [...(props.data || [])].map((item) => ({
      name: String(item.name || ''),
      value: normalizeNumber(item.value)
    }))
  )
  const normalizedGap = computed(() => {
    const gap = Number(props.gap ?? 4)
    if (!Number.isFinite(gap)) return 4
    return Math.min(20, Math.max(0, Math.round(gap)))
  })
  const chartColors = computed(() => (props.colors?.length ? props.colors : useChartOps().colors))

  const { chartRef, isDark, getAnimationConfig, getTooltipStyle, getLegendStyle } =
    useChartComponent({
      props,
      checkEmpty: () => !chartData.value.length || chartData.value.every((item) => item.value <= 0),
      watchSources: [
        () => props.data,
        () => props.colors,
        () => props.showLegend,
        () => props.legendPosition,
        () => props.showLabel,
        () => props.sort,
        () => props.minSize,
        () => props.maxSize,
        () => props.gap
      ],
      generateOptions: (): EChartsOption => {
        const legendPosition = props.legendPosition || 'right'
        const layout = getFunnelLayout(props.showLegend, legendPosition)
        const labelColor = isDark.value ? '#dce8ff' : '#3f4b5f'

        return {
          tooltip: props.showTooltip
            ? getTooltipStyle('item', {
                formatter: '{b}: {c}'
              })
            : undefined,
          legend: props.showLegend ? getLegendStyle(legendPosition) : undefined,
          series: [
            {
              name: '漏斗',
              type: 'funnel',
              left: layout.left,
              top: layout.top,
              width: layout.width,
              height: layout.height,
              min: 0,
              minSize: props.minSize,
              maxSize: props.maxSize,
              sort: props.sort,
              gap: normalizedGap.value,
              orient: 'vertical',
              label: {
                show: props.showLabel,
                position: 'inside',
                color: '#fff',
                fontSize: 12,
                formatter: '{b}'
              },
              labelLine: {
                show: false
              },
              itemStyle: {
                borderColor: isDark.value ? '#0a1728' : '#fff',
                borderWidth: 1
              },
              emphasis: {
                label: {
                  color: labelColor,
                  fontWeight: 600
                },
                itemStyle: {
                  shadowBlur: 12,
                  shadowColor: 'rgba(0, 0, 0, 0.22)'
                }
              },
              data: chartData.value,
              color: chartColors.value,
              ...getAnimationConfig(90, 1400)
            }
          ]
        }
      }
    })

  function normalizeNumber(value: unknown) {
    const normalized = typeof value === 'string' ? value.replace(/,/g, '').trim() : value
    const number = Number(normalized)
    return Number.isFinite(number) ? number : 0
  }

  function getFunnelLayout(
    showLegend: boolean,
    legendPosition: NonNullable<FunnelChartProps['legendPosition']>
  ) {
    if (!showLegend) return { left: '8%', top: '8%', width: '84%', height: '84%' }

    switch (legendPosition) {
      case 'left':
        return { left: '32%', top: '8%', width: '62%', height: '84%' }
      case 'right':
        return { left: '6%', top: '8%', width: '62%', height: '84%' }
      case 'top':
        return { left: '8%', top: '20%', width: '84%', height: '70%' }
      case 'bottom':
        return { left: '8%', top: '6%', width: '84%', height: '70%' }
      default:
        return { left: '8%', top: '8%', width: '84%', height: '84%' }
    }
  }
</script>
