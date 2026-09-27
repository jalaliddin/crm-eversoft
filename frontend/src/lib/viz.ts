/** Chart color roles. Status colors are reserved for attendance state and always ship with a legend. */
export const viz = {
  good: 'var(--viz-good)',
  warning: 'var(--viz-warning)',
  critical: 'var(--viz-critical)',
  series: 'var(--viz-series)',
} as const

/** Mark spec: 4px rounded data end, anchored to the baseline. */
export const barRadius: [number, number, number, number] = [4, 4, 0, 0]
export const hBarRadius: [number, number, number, number] = [0, 4, 4, 0]
