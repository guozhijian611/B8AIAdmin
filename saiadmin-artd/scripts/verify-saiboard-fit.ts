import { resolveBoardFit } from '../src/views/plugin/saiboard/widgets/fit'

interface ExpectedFit {
  scaleX: number
  scaleY: number
  x: number
  y: number
  scaledWidth: number
  scaledHeight: number
}

const assertAlmostEqual = (label: string, actual: number, expected: number) => {
  if (Math.abs(actual - expected) > 0.000001) {
    throw new Error(`${label}: expected ${expected}, got ${actual}`)
  }
}

const assertFit = (label: string, actual: ExpectedFit, expected: ExpectedFit) => {
  for (const key of Object.keys(expected) as Array<keyof ExpectedFit>) {
    assertAlmostEqual(`${label}.${key}`, actual[key], expected[key])
  }
}

assertFit(
  'contain keeps the whole canvas visible and centers horizontal letterbox space',
  resolveBoardFit({
    viewportWidth: 2048,
    viewportHeight: 990,
    canvasWidth: 1920,
    canvasHeight: 1080,
    mode: 'contain'
  }),
  {
    scaleX: 11 / 12,
    scaleY: 11 / 12,
    x: 144,
    y: 0,
    scaledWidth: 1760,
    scaledHeight: 990
  }
)

assertFit(
  'cover fills the viewport and keeps the top edge visible by default',
  resolveBoardFit({
    viewportWidth: 2048,
    viewportHeight: 990,
    canvasWidth: 1920,
    canvasHeight: 1080,
    mode: 'cover'
  }),
  {
    scaleX: 16 / 15,
    scaleY: 16 / 15,
    x: 0,
    y: 0,
    scaledWidth: 2048,
    scaledHeight: 1152
  }
)

assertFit(
  'cover can still center vertical cropping when requested',
  resolveBoardFit({
    viewportWidth: 2048,
    viewportHeight: 990,
    canvasWidth: 1920,
    canvasHeight: 1080,
    mode: 'cover',
    alignY: 'center'
  }),
  {
    scaleX: 16 / 15,
    scaleY: 16 / 15,
    x: 0,
    y: -81,
    scaledWidth: 2048,
    scaledHeight: 1152
  }
)

assertFit(
  'stretch uses independent horizontal and vertical scales',
  resolveBoardFit({
    viewportWidth: 1366,
    viewportHeight: 768,
    canvasWidth: 1920,
    canvasHeight: 1080,
    mode: 'stretch'
  }),
  {
    scaleX: 1366 / 1920,
    scaleY: 768 / 1080,
    x: 0,
    y: 0,
    scaledWidth: 1366,
    scaledHeight: 768
  }
)

assertFit(
  'maxScale prevents over-zooming in editor auto mode',
  resolveBoardFit({
    viewportWidth: 3840,
    viewportHeight: 2160,
    canvasWidth: 1920,
    canvasHeight: 1080,
    mode: 'contain',
    maxScale: 1
  }),
  {
    scaleX: 1,
    scaleY: 1,
    x: 960,
    y: 0,
    scaledWidth: 1920,
    scaledHeight: 1080
  }
)

assertFit(
  'padding reduces available viewport before fitting',
  resolveBoardFit({
    viewportWidth: 1024,
    viewportHeight: 768,
    canvasWidth: 800,
    canvasHeight: 600,
    mode: 'contain',
    padding: 32
  }),
  {
    scaleX: 88 / 75,
    scaleY: 88 / 75,
    x: 128 / 3,
    y: 32,
    scaledWidth: 2816 / 3,
    scaledHeight: 704
  }
)

assertFit(
  'invalid sizes fall back to one pixel and still return finite numbers',
  resolveBoardFit({
    viewportWidth: 0,
    viewportHeight: Number.NaN,
    canvasWidth: 0,
    canvasHeight: -1,
    mode: 'unknown'
  }),
  {
    scaleX: 1,
    scaleY: 1,
    x: 0,
    y: 0,
    scaledWidth: 1,
    scaledHeight: 1
  }
)

console.log('SAI Board fit verification passed')
