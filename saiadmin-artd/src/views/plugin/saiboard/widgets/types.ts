export interface BoardRect {
  x: number
  y: number
  w: number
  h: number
  z: number
}

export interface BoardDataset {
  queryTemplateId?: number
  refresh?: number
  mapping?: {
    labelField?: string
    valueField?: string
    tableFields?: string[]
  }
}

export interface BoardComponent {
  id: string
  type: string
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
  components: BoardComponent[]
}

export interface WidgetMeta {
  type: string
  name: string
  icon: string
  defaultRect: BoardRect
  defaultOption?: Record<string, any>
}
