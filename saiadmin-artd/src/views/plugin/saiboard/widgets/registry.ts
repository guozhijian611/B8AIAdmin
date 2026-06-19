import type { WidgetMeta, WidgetType } from './types'

export const widgetRegistry: WidgetMeta[] = [
  {
    type: 'art-bar-chart',
    name: '柱状图',
    icon: 'ri:bar-chart-2-line',
    defaultRect: { x: 40, y: 40, w: 560, h: 320, z: 1 },
    defaultOption: { showLegend: false }
  },
  {
    type: 'art-line-chart',
    name: '折线图',
    icon: 'ri:line-chart-line',
    defaultRect: { x: 640, y: 40, w: 560, h: 320, z: 1 },
    defaultOption: { showAreaColor: true }
  },
  {
    type: 'art-h-bar-chart',
    name: '横向柱图',
    icon: 'ri:bar-chart-horizontal-line',
    defaultRect: { x: 640, y: 400, w: 560, h: 320, z: 1 },
    defaultOption: { showLegend: false }
  },
  {
    type: 'art-dual-bar-compare-chart',
    name: '双向对比柱图',
    icon: 'ri:bar-chart-grouped-line',
    defaultRect: { x: 40, y: 760, w: 620, h: 320, z: 1 },
    defaultOption: {
      positiveField: '',
      negativeField: '',
      positiveName: '正向',
      negativeName: '负向',
      showLegend: true,
      showDataLabel: false,
      barWidth: 16
    }
  },
  {
    type: 'art-k-line-chart',
    name: 'K线图',
    icon: 'ri:line-chart-line',
    defaultRect: { x: 680, y: 760, w: 620, h: 320, z: 1 },
    defaultOption: {
      timeField: '',
      openField: '',
      closeField: '',
      highField: '',
      lowField: '',
      showDataZoom: true,
      dataZoomStart: 0,
      dataZoomEnd: 100
    }
  },
  {
    type: 'art-gauge-chart',
    name: '仪表盘',
    icon: 'ri:dashboard-3-line',
    defaultRect: { x: 1320, y: 760, w: 360, h: 280, z: 1 },
    defaultOption: {
      name: '完成率',
      min: 0,
      max: 100,
      unit: '%',
      decimals: 0,
      showPointer: true,
      showProgress: true
    }
  },
  {
    type: 'art-funnel-chart',
    name: '漏斗图',
    icon: 'ri:filter-3-line',
    defaultRect: { x: 360, y: 1120, w: 460, h: 320, z: 1 },
    defaultOption: {
      showLegend: false,
      legendPosition: 'right',
      showLabel: true,
      sort: 'descending',
      minSize: '20%',
      maxSize: '82%',
      gap: 4
    }
  },
  {
    type: 'art-heatmap-chart',
    name: '热力图',
    icon: 'ri:grid-line',
    defaultRect: { x: 860, y: 1120, w: 560, h: 340, z: 1 },
    defaultOption: {
      xField: '',
      yField: '',
      showLabel: false,
      showVisualMap: true,
      showAxisLabel: true,
      showAxisLine: true,
      showSplitLine: true,
      min: 0,
      max: undefined
    }
  },
  {
    type: 'art-ring-chart',
    name: '环形图',
    icon: 'ri:donut-chart-line',
    defaultRect: { x: 1240, y: 40, w: 420, h: 320, z: 1 },
    defaultOption: { showLegend: true, legendPosition: 'right' }
  },
  {
    type: 'art-radar-chart',
    name: '雷达图',
    icon: 'ri:radar-line',
    defaultRect: { x: 1240, y: 400, w: 420, h: 320, z: 1 },
    defaultOption: { showLegend: false }
  },
  {
    type: 'art-scatter-chart',
    name: '散点图',
    icon: 'ri:bubble-chart-line',
    defaultRect: { x: 40, y: 600, w: 560, h: 320, z: 1 },
    defaultOption: { showLegend: false }
  },
  {
    type: 'stat-number',
    name: '指标',
    icon: 'ri:number-1',
    defaultRect: { x: 40, y: 400, w: 320, h: 160, z: 1 },
    defaultOption: { prefix: '', unit: '', decimals: 0 }
  },
  {
    type: 'data-table',
    name: '表格',
    icon: 'ri:table-line',
    defaultRect: { x: 400, y: 400, w: 640, h: 320, z: 1 },
    defaultOption: { showIndex: false, rowStripe: false, maxRows: 0 }
  },
  {
    type: 'progress-rank',
    name: '进度排行',
    icon: 'ri:list-check-3',
    defaultRect: { x: 920, y: 1120, w: 520, h: 360, z: 1 },
    defaultOption: {
      labelField: '',
      valueField: '',
      targetField: '',
      statusField: '',
      maxRows: 8,
      sortOrder: 'desc',
      unit: '%',
      decimals: 0,
      showRank: true,
      showValue: true,
      accent: '#23d8ff'
    }
  },
  {
    type: 'status-matrix',
    name: '状态矩阵',
    icon: 'ri:grid-fill',
    defaultRect: { x: 1480, y: 1120, w: 520, h: 360, z: 1 },
    defaultOption: {
      labelField: '',
      statusField: '',
      valueField: '',
      groupField: '',
      maxRows: 12,
      columns: 3,
      unit: '',
      decimals: 0,
      showValue: true,
      showStatus: true,
      accent: '#69b7ff'
    }
  },
  {
    type: 'image-carousel',
    name: '图片轮播',
    icon: 'ri:image-line',
    defaultRect: { x: 1080, y: 400, w: 520, h: 300, z: 1 },
    defaultOption: {
      imageField: '',
      titleField: '',
      interval: 3000,
      imageFit: 'cover',
      showDots: true
    }
  },
  {
    type: 'event-timeline',
    name: '时间轴',
    icon: 'ri:timeline-view',
    defaultRect: { x: 40, y: 1120, w: 520, h: 360, z: 1 },
    defaultOption: {
      timeField: '',
      titleField: '',
      contentField: '',
      statusField: '',
      maxRows: 8,
      sortOrder: 'desc',
      showTime: true,
      showContent: true,
      accent: '#69b7ff'
    }
  },
  {
    type: 'alarm-list',
    name: '告警列表',
    icon: 'ri:alarm-warning-line',
    defaultRect: { x: 600, y: 1120, w: 520, h: 360, z: 1 },
    defaultOption: {
      timeField: '',
      titleField: '',
      contentField: '',
      levelField: '',
      maxRows: 8,
      sortOrder: 'desc',
      showTime: true,
      showContent: true,
      accent: '#ffcf5a'
    }
  },
  {
    type: 'geo-point-map',
    name: '点位地图',
    icon: 'ri:map-pin-line',
    defaultRect: { x: 1080, y: 40, w: 560, h: 360, z: 1 },
    defaultOption: {
      lngField: '',
      latField: '',
      nameField: '',
      valueField: '',
      region: 'china',
      pointSize: 12,
      showLabel: true
    }
  },
  {
    type: 'decor-border',
    name: '装饰边框',
    icon: 'ri:rounded-corner',
    defaultRect: { x: 1080, y: 760, w: 480, h: 180, z: 1 },
    defaultOption: { borderStyle: 'corner', accent: '#69b7ff', opacity: 0.85 }
  },
  {
    type: 'decor-flow-border',
    name: '流光边框',
    icon: 'ri:focus-2-line',
    defaultRect: { x: 1080, y: 960, w: 480, h: 220, z: 1 },
    defaultOption: {
      variant: 'orbit',
      accent: '#23d8ff',
      secondary: '#ffcf5a',
      opacity: 0.86,
      speed: 5,
      thickness: 2,
      glow: true
    }
  },
  {
    type: 'decor-scanline',
    name: '扫描线',
    icon: 'ri:scan-line',
    defaultRect: { x: 720, y: 760, w: 520, h: 180, z: 1 },
    defaultOption: {
      direction: 'horizontal',
      speed: 4,
      accent: '#23d8ff',
      opacity: 0.68
    }
  },
  {
    type: 'decor-title',
    name: '标题装饰',
    icon: 'ri:text-block',
    defaultRect: { x: 720, y: 980, w: 560, h: 96, z: 1 },
    defaultOption: {
      text: '数据总览',
      subtitle: '',
      variant: 'bar',
      align: 'center',
      accent: '#69b7ff'
    }
  },
  {
    type: 'decor-divider',
    name: '分割线',
    icon: 'ri:separator',
    defaultRect: { x: 720, y: 1100, w: 560, h: 48, z: 1 },
    defaultOption: {
      direction: 'horizontal',
      variant: 'pulse',
      accent: '#23d8ff',
      opacity: 0.72,
      speed: 4
    }
  }
]

export const getWidgetMeta = (type: string) => widgetRegistry.find((item) => item.type === type)

export const decorWidgetTypes = new Set<string>([
  'decor-border',
  'decor-flow-border',
  'decor-scanline',
  'decor-title',
  'decor-divider'
])

export const createDefaultComponent = (type: WidgetType | string, order: number) => {
  const meta = getWidgetMeta(type) || widgetRegistry[0]
  const isDecor = decorWidgetTypes.has(meta.type)
  return {
    id: `w_${Date.now()}_${Math.floor(Math.random() * 1000)}`,
    type: meta.type,
    title: isDecor ? '' : meta.name,
    rect: {
      ...meta.defaultRect,
      x: meta.defaultRect.x + order * 20,
      y: meta.defaultRect.y + order * 20
    },
    dataset: isDecor ? {} : { queryTemplateId: undefined, refresh: 30 },
    option: { ...(meta.defaultOption || {}) }
  }
}
