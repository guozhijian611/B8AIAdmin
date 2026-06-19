export type BoardFitMode = 'contain' | 'cover' | 'stretch'

export interface BoardFitOptions {
  viewportWidth: number
  viewportHeight: number
  canvasWidth: number
  canvasHeight: number
  mode?: unknown
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

  if (mode === 'stretch') {
    const nextScaleX = Math.min(maxScale, scaleX)
    const nextScaleY = Math.min(maxScale, scaleY)
    return buildFitResult(
      viewportWidth,
      viewportHeight,
      canvasWidth,
      canvasHeight,
      nextScaleX,
      nextScaleY
    )
  }

  const rawScale = mode === 'cover' ? Math.max(scaleX, scaleY) : Math.min(scaleX, scaleY)
  const scale = Math.min(maxScale, normalizeBoardScale(rawScale))
  return buildFitResult(viewportWidth, viewportHeight, canvasWidth, canvasHeight, scale, scale)
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
  scaleY: number
): BoardFitResult => {
  const scaledWidth = canvasWidth * scaleX
  const scaledHeight = canvasHeight * scaleY

  return {
    scaleX,
    scaleY,
    x: (viewportWidth - scaledWidth) / 2,
    y: (viewportHeight - scaledHeight) / 2,
    scaledWidth,
    scaledHeight
  }
}
