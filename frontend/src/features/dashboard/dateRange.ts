import { tenantNow } from '@/lib/format'

export type RangePreset = 'today' | 'week' | 'month' | 'quarter' | 'custom'

export function presetRange(preset: RangePreset): [string, string] {
  const now = tenantNow()
  const f = (d: typeof now) => d.format('YYYY-MM-DD')
  switch (preset) {
    case 'today':
      return [f(now), f(now)]
    case 'week':
      return [f(now.startOf('isoWeek')), f(now)]
    case 'quarter':
      return [f(now.startOf('quarter')), f(now)]
    default:
      return [f(now.startOf('month')), f(now)]
  }
}

