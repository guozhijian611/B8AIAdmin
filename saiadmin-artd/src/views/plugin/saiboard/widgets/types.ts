export interface BoardRect {
  x: number
  y: number
  w: number
  h: number
  z: number
}

export interface BoardTableColumn {
  field: string
  label?: string
  width?: number
  align?: 'left' | 'center' | 'right'
}

export interface BoardDataset {
  queryTemplateId?: number
  refresh?: number
  mapping?: {
    labelField?: string
    valueField?: string
    tableFields?: string[]
    tableColumns?: BoardTableColumn[]
  }
}

export type WidgetType =
  | 'art-bar-chart'
  | 'art-line-chart'
  | 'art-h-bar-chart'
  | 'art-dual-bar-compare-chart'
  | 'art-k-line-chart'
  | 'art-gauge-chart'
  | 'art-funnel-chart'
  | 'art-heatmap-chart'
  | 'art-ring-chart'
  | 'art-radar-chart'
  | 'art-scatter-chart'
  | 'stat-number'
  | 'data-table'
  | 'image-carousel'
  | 'event-timeline'
  | 'geo-point-map'
  | 'decor-border'
  | 'decor-scanline'
  | 'decor-title'
  | 'decor-divider'

export interface BoardBgConfig {
  color?: string
  theme?: string
  fit_mode?: string
  image?: string
  image_fit?: string
}

export interface BoardComponent {
  id: string
  type: WidgetType | string
  title: string
  rect: BoardRect
  dataset: BoardDataset
  option?: Record<string, any>
}

export interface BoardLayout {
  canvas: {
    width: number
    height: number
  }
  bg_config?: BoardBgConfig
  components: BoardComponent[]
}

export interface WidgetMeta {
  type: WidgetType
  name: string
  icon: string
  defaultRect: BoardRect
  defaultOption?: Record<string, any>
}
