import { Group, SegmentedControl, Select } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { useTranslation } from 'react-i18next'
import type { RangePreset } from './dateRange'
import { presetRange } from './dateRange'

interface Props {
  preset: RangePreset
  from: string
  to: string
  onChange: (preset: RangePreset, from: string, to: string) => void
}

export function DateRangeFilter({ preset, from, to, onChange }: Props) {
  const { t } = useTranslation()

  const options = [
    { value: 'today', label: t('common.today') },
    { value: 'week', label: t('common.thisWeek') },
    { value: 'month', label: t('common.thisMonth') },
    { value: 'quarter', label: t('common.thisQuarter') },
    { value: 'custom', label: t('common.custom') },
  ]
  const select = (value: string | null) => {
    const p = (value ?? 'month') as RangePreset
    if (p === 'custom') onChange(p, from, to)
    else onChange(p, ...presetRange(p))
  }

  return (
    <Group gap="xs" wrap="wrap">
      {/* Segments on wide screens, a compact select on phones. */}
      <SegmentedControl value={preset} onChange={select} data={options} aria-label="Date range" visibleFrom="sm" />
      <Select value={preset} onChange={select} data={options} aria-label="Date range" hiddenFrom="sm" w={170} />
      {preset === 'custom' && (
        <DatePickerInput
          type="range"
          value={[from, to]}
          onChange={([a, b]) => a && b && onChange('custom', String(a).slice(0, 10), String(b).slice(0, 10))}
          valueFormat="DD.MM.YYYY"
          aria-label="Custom date range"
          w={240}
        />
      )}
    </Group>
  )
}
