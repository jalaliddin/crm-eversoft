import { Box, Group, Text, Tooltip } from '@mantine/core'
import { dayjs, fmtMinutes, tz } from '@/lib/format'

interface Props {
  /** Scheduled window as local "HH:mm". */
  start: string | null
  end: string | null
  /** Actual punches as UTC ISO strings. */
  checkIn: string | null
  checkOut: string | null
  lateMinutes?: number
  earlyMinutes?: number
  compact?: boolean
}

const DAY_START = 6 * 60 // 06:00
const DAY_END = 22 * 60 // 22:00

function localMinutes(iso: string): number {
  const d = dayjs.utc(iso).tz(tz())
  return d.hour() * 60 + d.minute()
}

function parse(hm: string): number {
  const [h, m] = hm.split(':').map(Number)
  return h * 60 + m
}

/**
 * The attendance signature: one line per employee showing the scheduled window (pale band),
 * the actual time on site (solid), and the late / early portions in saffron and orange.
 * A person still on site extends to "now" with a soft edge.
 */
export function ShiftBar({ start, end, checkIn, checkOut, lateMinutes = 0, earlyMinutes = 0, compact = false }: Props) {
  const range = DAY_END - DAY_START
  const pct = (minutes: number) => `${Math.min(100, Math.max(0, ((minutes - DAY_START) / range) * 100))}%`
  const width = (from: number, to: number) => `${Math.max(0, ((Math.min(to, DAY_END) - Math.max(from, DAY_START)) / range) * 100)}%`

  const s = start ? parse(start) : null
  let e = end ? parse(end) : null
  if (s !== null && e !== null && e <= s) e = DAY_END // overnight shift: clip to the visible day
  const inM = checkIn ? localMinutes(checkIn) : null
  const now = dayjs().tz(tz())
  const outM = checkOut ? localMinutes(checkOut) : inM !== null && dayjs.utc(checkIn).tz(tz()).isSame(now, 'day') ? now.hour() * 60 + now.minute() : null
  const live = !checkOut && outM !== null

  const label = [
    start && end ? `Scheduled ${start}–${end}` : 'No fixed hours',
    inM !== null ? `in ${dayjs.utc(checkIn).tz(tz()).format('HH:mm')}` : 'no check-in',
    checkOut ? `out ${dayjs.utc(checkOut).tz(tz()).format('HH:mm')}` : live ? 'still on site' : null,
    lateMinutes ? `late ${fmtMinutes(lateMinutes)}` : null,
    earlyMinutes ? `left ${fmtMinutes(earlyMinutes)} early` : null,
  ]
    .filter(Boolean)
    .join(' · ')

  return (
    <Tooltip label={label}>
      <Box role="img" aria-label={label} pos="relative" h={compact ? 8 : 12} miw={compact ? 120 : 180} style={{ borderRadius: 6, background: 'var(--app-muted-surface)' }}>
        {s !== null && e !== null && (
          <Box pos="absolute" top={0} bottom={0} left={pct(s)} w={width(s, e)} style={{ background: 'var(--app-shift-window)', borderRadius: 6 }} />
        )}
        {inM !== null && outM !== null && outM > inM && (
          <Box
            pos="absolute"
            top={compact ? 1 : 2}
            bottom={compact ? 1 : 2}
            left={pct(inM)}
            w={width(inM, outM)}
            style={{
              background: 'var(--app-shift-worked)',
              borderRadius: 4,
              maskImage: live ? 'linear-gradient(to right, black 85%, transparent)' : undefined,
            }}
          />
        )}
        {s !== null && lateMinutes > 0 && inM !== null && (
          <Box pos="absolute" top={0} bottom={0} left={pct(inM - lateMinutes)} w={width(inM - lateMinutes, inM)} style={{ background: 'var(--app-shift-late)', borderRadius: 4 }} />
        )}
        {e !== null && earlyMinutes > 0 && outM !== null && (
          <Box pos="absolute" top={0} bottom={0} left={pct(outM)} w={width(outM, outM + earlyMinutes)} style={{ background: 'var(--app-shift-early)', borderRadius: 4, opacity: 0.8 }} />
        )}
      </Box>
    </Tooltip>
  )
}

export function ShiftBarLegend() {
  const item = (color: string, text: string) => (
    <Group gap={6} wrap="nowrap">
      <Box w={14} h={8} style={{ background: color, borderRadius: 3 }} />
      <Text size="xs" c="dimmed">
        {text}
      </Text>
    </Group>
  )
  return (
    <Group gap="md">
      {item('var(--app-shift-window)', 'Scheduled')}
      {item('var(--app-shift-worked)', 'On site')}
      {item('var(--app-shift-late)', 'Late')}
      {item('var(--app-shift-early)', 'Left early')}
      <Text size="xs" c="dimmed">06:00–22:00</Text>
    </Group>
  )
}
