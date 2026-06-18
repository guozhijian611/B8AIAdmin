import type { WidgetMeta } from './types'

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
    type: 'art-ring-chart',
    name: '环形图',
    icon: 'ri:donut-chart-line',
    defaultRect: { x: 1240, y: 40, w: 420, h: 320, z: 1 },
    defaultOption: { showLegend: true, legendPosition: 'right' }
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
  }
]

export const getWidgetMeta = (type: string) => widgetRegistry.find((item) => item.type === type)

export const createDefaultComponent = (type: string, order: number) => {
  const meta = getWidgetMeta(type) || widgetRegistry[0]
  return {
    id: `w_${Date.now()}_${Math.floor(Math.random() * 1000)}`,
    type: meta.type,
    title: meta.name,
    rect: {
      ...meta.defaultRect,
      x: meta.defaultRect.x + order * 20,
      y: meta.defaultRect.y + order * 20
    },
    dataset: { queryTemplateId: undefined, refresh: 30 },
    option: { ...(meta.defaultOption || {}) }
  }
}
