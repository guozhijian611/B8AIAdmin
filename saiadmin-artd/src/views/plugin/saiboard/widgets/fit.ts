export type BoardFitMode = 'contain' | 'cover' | 'stretch'
export type BoardFitAlignX = 'left' | 'center' | 'right'
export type BoardFitAlignY = 'top' | 'center' | 'bottom'

export interface BoardFitOptions {
  viewportWidth: number
  viewportHeight: number
  canvasWidth: number
  canvasHeight: number
  mode?: unknown
  alignX?: unknown
  alignY?: unknown
  padding?: number
  maxScale?: number
}

export interface BoardFitResult {
  scaleX: number
  scaleY: number
  x: number
  y: number
  scaledWidth: number
  scaledHeight: number
}

export const normalizeBoardFitMode = (mode: unknown): BoardFitMode => {
  const value = String(mode || '')
  return value === 'cover' || value === 'stretch' ? value : 'contain'
}

export const normalizeBoardScale = (value: unknown, fallback = 1) => {
  const scale = Number(value)
  return Number.isFinite(scale) && scale > 0 ? scale : fallback
}

export const normalizeBoardFitAlignX = (align: unknown): BoardFitAlignX => {
  const value = String(align || '')
  return value === 'left' || value === 'right' ? value : 'center'
}

export const normalizeBoardFitAlignY = (align: unknown): BoardFitAlignY => {
  const value = String(align || '')
  return value === 'center' || value === 'bottom' ? value : 'top'
}

export const resolveBoardFit = (options: BoardFitOptions): BoardFitResult => {
  const viewportWidth = normalizeSize(options.viewportWidth)
  const viewportHeight = normalizeSize(options.viewportHeight)
  const canvasWidth = normalizeSize(options.canvasWidth)
  const canvasHeight = normalizeSize(options.canvasHeight)
  const padding = Math.max(0, Number(options.padding || 0))
  const availableWidth = Math.max(1, viewportWidth - padding * 2)
  const availableHeight = Math.max(1, viewportHeight - padding * 2)
  const scaleX = normalizeBoardScale(availableWidth / canvasWidth)
  const scaleY = normalizeBoardScale(availableHeight / canvasHeight)
  const maxScale = normalizeMaxScale(options.maxScale)
  const mode = normalizeBoardFitMode(options.mode)
  const alignX = normalizeBoardFitAlignX(options.alignX)
  const alignY = normalizeBoardFitAlignY(options.alignY)

  if (mode === 'stretch') {
    const nextScaleX = Math.min(maxScale, scaleX)
    const nextScaleY = Math.min(maxScale, scaleY)
    return buildFitResult(
      viewportWidth,
      viewportHeight,
      canvasWidth,
      canvasHeight,
      nextScaleX,
      nextScaleY,
      alignX,
      alignY,
      padding
    )
  }

  const rawScale = mode === 'cover' ? Math.max(scaleX, scaleY) : Math.min(scaleX, scaleY)
  const scale = Math.min(maxScale, normalizeBoardScale(rawScale))
  return buildFitResult(
    viewportWidth,
    viewportHeight,
    canvasWidth,
    canvasHeight,
    scale,
    scale,
    alignX,
    alignY,
    padding
  )
}

const normalizeSize = (value: unknown) => {
  const size = Number(value)
  return Number.isFinite(size) && size > 0 ? size : 1
}

const normalizeMaxScale = (value: unknown) => {
  if (value === undefined || value === null) return Number.POSITIVE_INFINITY
  const scale = Number(value)
  return Number.isFinite(scale) && scale > 0 ? scale : Number.POSITIVE_INFINITY
}

const buildFitResult = (
  viewportWidth: number,
  viewportHeight: number,
  canvasWidth: number,
  canvasHeight: number,
  scaleX: number,
  scaleY: number,
  alignX: BoardFitAlignX,
  alignY: BoardFitAlignY,
  padding: number
): BoardFitResult => {
  const scaledWidth = canvasWidth * scaleX
  const scaledHeight = canvasHeight * scaleY
  const availableWidth = Math.max(1, viewportWidth - padding * 2)
  const availableHeight = Math.max(1, viewportHeight - padding * 2)

  return {
    scaleX,
    scaleY,
    x: padding + resolveOffset(availableWidth, scaledWidth, alignX),
    y: padding + resolveOffset(availableHeight, scaledHeight, alignY),
    scaledWidth,
    scaledHeight
  }
}

const resolveOffset = (
  viewportSize: number,
  scaledSize: number,
  align: BoardFitAlignX | BoardFitAlignY
) => {
  if (align === 'left' || align === 'top') return 0
  if (align === 'right' || align === 'bottom') return viewportSize - scaledSize
  return (viewportSize - scaledSize) / 2
}
