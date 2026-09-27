import { Box, Group, SimpleGrid, Text, Tooltip, UnstyledButton } from '@mantine/core'
import { useTranslation } from 'react-i18next'
import { dayjs, fmtMinutes, fmtTime, today } from '@/lib/format'
import type { CalendarDay } from '@/types/models'
import { CODE_STYLE } from './calendarCodes'


const LEGEND: [string, string][] = [['P', 'present'], ['L', 'late'], ['A', 'absent'], ['EL', 'early_leave'], ['I', 'incomplete'], ['OL', 'on_leave'], ['H', 'holiday'], ['W', 'weekend']]

export function CalendarLegend() {
  const { t } = useTranslation()
  return (
    <Group gap="md">
      {LEGEND.map(([code, status]) => (
        <Group key={code} gap={6} wrap="nowrap">
          <Box px={6} style={{ borderRadius: 4, background: CODE_STYLE[code].bg, color: CODE_STYLE[code].fg, fontSize: 11, fontWeight: 700, border: code === 'W' ? '1px dashed var(--app-border)' : undefined }}>{code}</Box>
          <Text size="xs" c="dimmed">{t(`status.${status}`)}</Text>
        </Group>
      ))}
    </Group>
  )
}

/** One employee's month; each day opens its record. */
export function MonthCalendar({ days, onSelect }: { days: CalendarDay[]; onSelect?: (day: CalendarDay) => void }) {
  const { t } = useTranslation()
  if (!days.length) return null
  const lead = dayjs(days[0].date).isoWeekday() - 1
  const now = today()

  return (
    <Box>
      <SimpleGrid cols={7} spacing={6} mb={6}>
        {['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].map((d) => <Text key={d} size="xs" c="dimmed" ta="center" fw={600}>{d}</Text>)}
      </SimpleGrid>
      <SimpleGrid cols={7} spacing={6}>
        {Array.from({ length: lead }, (_, i) => <Box key={`pad-${i}`} />)}
        {days.map((day) => {
          const style = day.code ? CODE_STYLE[day.code] : undefined
          const future = day.date > now
          const label = [
            dayjs(day.date).format('dddd, D MMMM'),
            day.status ? t(`status.${day.status}`) : future ? 'Upcoming' : 'No data',
            day.check_in ? `${fmtTime(day.check_in)}–${day.check_out ? fmtTime(day.check_out) : '…'}` : null,
            day.worked_minutes ? `worked ${fmtMinutes(day.worked_minutes)}` : null,
            day.late_minutes ? `late ${fmtMinutes(day.late_minutes)}` : null,
          ].filter(Boolean).join(' · ')

          return (
            <Tooltip key={day.date} label={label}>
              <UnstyledButton
                onClick={() => onSelect?.(day)}
                disabled={!onSelect}
                aria-label={label}
                p={6}
                mih={64}
                style={{
                  borderRadius: 8,
                  background: style?.bg ?? 'transparent',
                  border: day.date === now ? '2px solid var(--mantine-primary-color-filled)' : day.code === 'W' || !day.code ? '1px dashed var(--app-border)' : '1px solid transparent',
                  opacity: future ? 0.55 : 1,
                  display: 'flex',
                  flexDirection: 'column',
                  justifyContent: 'space-between',
                }}
              >
                <Group justify="space-between" wrap="nowrap" gap={2}>
                  <Text size="xs" fw={600} className="tabular">{dayjs(day.date).date()}</Text>
                  {day.code && <Text size="xs" fw={700} style={{ color: style?.fg }}>{day.code}</Text>}
                </Group>
                {day.check_in && (
                  <Text size="10px" c="dimmed" className="tabular" visibleFrom="sm">
                    {fmtTime(day.check_in)}{day.check_out ? `–${fmtTime(day.check_out)}` : ''}
                  </Text>
                )}
              </UnstyledButton>
            </Tooltip>
          )
        })}
      </SimpleGrid>
    </Box>
  )
}
