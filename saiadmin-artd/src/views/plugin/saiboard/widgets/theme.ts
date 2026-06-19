import type { CSSProperties } from 'vue'
import { normalizeBoardFitAlignY, normalizeBoardFitMode } from './fit'
import type { BoardBgConfig } from './types'

export const boardThemeOptions = [
  { label: '深海蓝', value: 'midnight' },
  { label: '青绿', value: 'teal' },
  { label: '琥珀', value: 'amber' }
]

export const backgroundFitOptions = [
  { label: '铺满裁切', value: 'cover' },
  { label: '完整显示', value: 'contain' },
  { label: '拉伸', value: 'stretch' },
  { label: '平铺', value: 'repeat' }
]

export const boardFitAlignOptions = [
  { label: '顶部对齐', value: 'top' },
  { label: '居中对齐', value: 'center' },
  { label: '底部对齐', value: 'bottom' }
]

const themes: Record<string, Record<string, string>> = {
  midnight: {
    accent: '#69b7ff',
    text: '#e5eefb',
    panelBg: 'rgb(8 18 32 / 78%)',
    panelBorder: 'rgb(104 166 255 / 24%)'
  },
  teal: {
    accent: '#36d6b7',
    text: '#ecfffb',
    panelBg: 'rgb(7 28 30 / 78%)',
    panelBorder: 'rgb(54 214 183 / 24%)'
  },
  amber: {
    accent: '#f4b860',
    text: '#fff7e8',
    panelBg: 'rgb(34 24 12 / 78%)',
    panelBorder: 'rgb(244 184 96 / 26%)'
  }
}

export const normalizeBoardTheme = (theme: unknown) => {
  const value = String(theme || '')
  return Object.keys(themes).includes(value) ? value : 'midnight'
}

export const normalizeBackgroundFit = (fit: unknown) => {
  const value = String(fit || '')
  return ['cover', 'contain', 'stretch', 'repeat'].includes(value) ? value : 'cover'
}

export const normalizeBgConfig = (config: BoardBgConfig = {}) =>
  ({
    ...(config || {}),
    color: config?.color || '#07111f',
    theme: normalizeBoardTheme(config?.theme),
    fit_mode: normalizeFitMode(config?.fit_mode),
    fit_align: normalizeFitAlign(config?.fit_align),
    image: String(config?.image || '').trim(),
    image_fit: normalizeBackgroundFit(config?.image_fit)
  }) satisfies BoardBgConfig

export const normalizeFitMode = (mode: unknown) => {
  return normalizeBoardFitMode(mode)
}

export const normalizeFitAlign = (align: unknown) => {
  return normalizeBoardFitAlignY(align)
}

export const boardCanvasStyle = (config: BoardBgConfig = {}) => {
  const bg = normalizeBgConfig(config)
  const theme = themes[bg.theme] || themes.midnight
  const bgImage = String(bg.image || '').replaceAll('"', '\\"')
  const imageStyle = bg.image
    ? {
        backgroundImage: `url("${bgImage}")`,
        backgroundRepeat: bg.image_fit === 'repeat' ? 'repeat' : 'no-repeat',
        backgroundPosition: 'center center',
        backgroundSize:
          bg.image_fit === 'stretch'
            ? '100% 100%'
            : bg.image_fit === 'repeat'
              ? 'auto'
              : bg.image_fit
      }
    : {}

  return {
    ...imageStyle,
    backgroundColor: bg.color,
    '--saiboard-accent': theme.accent,
    '--saiboard-text': theme.text,
    '--saiboard-panel-bg': theme.panelBg,
    '--saiboard-panel-border': theme.panelBorder
  } as CSSProperties
}
