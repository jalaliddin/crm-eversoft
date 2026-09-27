import { BarChart } from '@mantine/charts'
import { dayjs } from '@/lib/format'
import { viz } from '@/lib/viz'
import { ChartCard } from './ChartCard'

export interface TrendPoint {
  date: string
  present: number
  late: number
  absent: number
}

/**
 * Stacked daily bars: on time / late / absent. "Present" in the API counts everyone who checked in,
 * so the on-time segment is present − late to keep the stack additive.
 */
export function AttendanceTrendChart({ data, loading, title = 'Attendance trend' }: { data?: TrendPoint[]; loading?: boolean; title?: string }) {
  const rows = (data ?? []).map((p) => ({
    day: dayjs(p.date).format('DD MMM'),
    'On time': Math.max(0, p.present - p.late),
    Late: p.late,
    Absent: p.absent,
  }))
  const empty = rows.every((r) => r['On time'] + r.Late + r.Absent === 0)

  return (
    <ChartCard
      title={title}
      description="Employees per day, by arrival"
      loading={loading}
      empty={!loading && empty}
      table={{ columns: ['Day', 'On time', 'Late', 'Absent'], rows: rows.map((r) => [r.day, r['On time'], r.Late, r.Absent]) }}
    >
      <BarChart
        h={260}
        data={rows}
        dataKey="day"
        type="stacked"
        withLegend
        legendProps={{ verticalAlign: 'top', height: 36 }}
        series={[
          { name: 'On time', color: viz.good },
          { name: 'Late', color: viz.warning },
          { name: 'Absent', color: viz.critical },
        ]}
        barProps={{ radius: 0, stroke: 'var(--mantine-color-body)', strokeWidth: 2 }}
        gridAxis="y"
        tickLine="none"
        xAxisProps={{ interval: 'preserveStartEnd', minTickGap: 16 }}
        tooltipAnimationDuration={120}
        maxBarWidth={22}
      />
    </ChartCard>
  )
}
