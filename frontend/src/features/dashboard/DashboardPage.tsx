import { useState } from 'react'
import { Grid, SimpleGrid, Stack, Text, Title } from '@mantine/core'
import { AreaChart, BarChart } from '@mantine/charts'
import {
  IconAddressBook, IconAlertTriangle, IconBriefcase, IconCalendarEvent, IconChecklist, IconClockExclamation, IconTargetArrow,
  IconTrophy, IconUserCheck, IconUserOff, IconUsers, IconUserX, IconActivityHeartbeat,
} from '@tabler/icons-react'
import { useQuery } from '@tanstack/react-query'
import { PageHeader } from '@/components/PageHeader'
import { StatCard } from '@/components/StatCard'
import { ErrorState } from '@/components/ErrorState'
import { ChartCard } from '@/components/charts/ChartCard'
import { AttendanceTrendChart, type TrendPoint } from '@/components/charts/AttendanceTrendChart'
import { useAuth } from '@/features/auth/useAuth'
import { api } from '@/lib/api'
import { dayjs, fmtCompact, fmtMoney, fmtNumber } from '@/lib/format'
import { barRadius, hBarRadius, viz } from '@/lib/viz'
import type { AttendanceSummary } from '@/types/models'
import { DateRangeFilter } from './DateRangeFilter'
import { presetRange, type RangePreset } from './dateRange'

interface DashboardData {
  range: { from: string; to: string; bucket: 'day' | 'month' }
  crm?: {
    cards: Record<string, number>
    customer_growth: { date: string; value: number }[]
    lead_funnel: { status: string; label: string; count: number }[]
    deal_pipeline: { stage_id: number; stage: string; type: string; count: number; value: number }[]
    deal_value: { date: string; value: number }[]
    currency: string
  }
  hr?: { today: AttendanceSummary; attendance_trend: TrendPoint[] }
}

export default function DashboardPage() {
  const { me } = useAuth()
  const [preset, setPreset] = useState<RangePreset>('month')
  const [[from, to], setRange] = useState(presetRange('month'))

  const { data, isLoading, error, refetch } = useQuery({
    queryKey: ['dashboard', from, to],
    queryFn: () => api.get<DashboardData>('/dashboard', { from, to }),
  })

  if (error) return <ErrorState error={error} onRetry={() => void refetch()} />

  const crm = data?.crm
  const hr = data?.hr
  const bucketLabel = (d: string) => (data?.range.bucket === 'month' ? dayjs(d).format('MMM YYYY') : dayjs(d).format('DD MMM'))
  const currency = crm?.currency ?? me?.organization?.currency

  return (
    <>
      <PageHeader
        title={`Good ${dayjs().hour() < 12 ? 'morning' : dayjs().hour() < 18 ? 'afternoon' : 'evening'}, ${me?.user.name.split(' ')[0] ?? ''}`}
        description={me?.organization?.name}
        actions={<DateRangeFilter preset={preset} from={from} to={to} onChange={(p, f, t) => { setPreset(p); setRange([f, t]) }} />}
      />

      <Stack gap="xl">
        {(isLoading || crm) && (
          <section aria-labelledby="crm-heading">
            <Title order={2} id="crm-heading" fz="lg" mb="sm">Sales</Title>
            <SimpleGrid cols={{ base: 2, sm: 3, lg: 4, xl: 7 }} spacing="sm">
              <StatCard loading={isLoading} label="Customers" value={fmtNumber(crm?.cards.customers)} hint={`+${crm?.cards.new_customers ?? 0} in period`} icon={<IconAddressBook size={18} />} to="/crm/customers" />
              <StatCard loading={isLoading} label="New leads" value={fmtNumber(crm?.cards.new_leads)} icon={<IconTargetArrow size={18} />} to="/crm/leads" />
              <StatCard loading={isLoading} label="Open deals" value={fmtNumber(crm?.cards.open_deals)} hint={fmtMoney(crm?.cards.open_deals_value, currency)} icon={<IconBriefcase size={18} />} to="/crm/deals" />
              <StatCard loading={isLoading} label="Won deals" value={fmtNumber(crm?.cards.won_deals)} hint={fmtMoney(crm?.cards.won_deals_value, currency)} icon={<IconTrophy size={18} />} tone="teal" />
              <StatCard loading={isLoading} label="Lost deals" value={fmtNumber(crm?.cards.lost_deals)} icon={<IconUserOff size={18} />} />
              <StatCard loading={isLoading} label="Open tasks" value={fmtNumber(crm?.cards.open_tasks)} hint={crm?.cards.overdue_tasks ? `${crm.cards.overdue_tasks} overdue` : 'None overdue'} icon={<IconChecklist size={18} />} to="/crm/activities?status=pending" tone={crm?.cards.overdue_tasks ? 'red' : undefined} />
              <StatCard loading={isLoading} label="Due today" value={fmtNumber(crm?.cards.today_activities)} icon={<IconCalendarEvent size={18} />} to="/crm/activities" />
            </SimpleGrid>
          </section>
        )}

        {hr && (
          <section aria-labelledby="hr-heading">
            <Title order={2} id="hr-heading" fz="lg" mb="sm">Team today</Title>
            <SimpleGrid cols={{ base: 2, sm: 3, lg: 5 }} spacing="sm">
              <StatCard label="Employees" value={fmtNumber(hr.today.total_employees)} icon={<IconUsers size={18} />} to="/hr/employees" />
              <StatCard label="Present" value={fmtNumber(hr.today.present)} icon={<IconUserCheck size={18} />} to="/hr/attendance/monitor?status=present" tone="teal" />
              <StatCard label="Late" value={fmtNumber(hr.today.late)} icon={<IconClockExclamation size={18} />} to="/hr/attendance/monitor?status=late" tone="saffron" />
              <StatCard label="Absent" value={fmtNumber(hr.today.absent)} icon={<IconUserX size={18} />} to="/hr/attendance/monitor?status=absent" tone={hr.today.absent ? 'red' : undefined} />
              <StatCard label="Working now" value={fmtNumber(hr.today.working)} icon={<IconActivityHeartbeat size={18} />} to="/hr/attendance/monitor?status=working" />
            </SimpleGrid>
          </section>
        )}

        {(isLoading || crm) && (
          <Grid gap="md">
            <Grid.Col span={{ base: 12, lg: 7 }}>
              <ChartCard
                title="Customer growth"
                description="Total customers over the period"
                loading={isLoading}
                table={{ columns: ['Date', 'Customers'], rows: (crm?.customer_growth ?? []).map((p) => [bucketLabel(p.date), p.value]) }}
              >
                <AreaChart
                  h={260}
                  data={(crm?.customer_growth ?? []).map((p) => ({ date: bucketLabel(p.date), Customers: p.value }))}
                  dataKey="date"
                  series={[{ name: 'Customers', color: viz.series }]}
                  curveType="monotone"
                  strokeWidth={2}
                  fillOpacity={0.15}
                  withDots={false}
                  gridAxis="y"
                  tickLine="none"
                  xAxisProps={{ interval: 'preserveStartEnd', minTickGap: 24 }}
                />
              </ChartCard>
            </Grid.Col>
            <Grid.Col span={{ base: 12, lg: 5 }}>
              <ChartCard
                title="Lead funnel"
                description="Leads by current status"
                loading={isLoading}
                table={{ columns: ['Status', 'Leads'], rows: (crm?.lead_funnel ?? []).map((s) => [s.label, s.count]) }}
              >
                <BarChart
                  h={260}
                  orientation="vertical"
                  data={(crm?.lead_funnel ?? []).map((s) => ({ status: s.label, Leads: s.count }))}
                  dataKey="status"
                  series={[{ name: 'Leads', color: viz.series }]}
                  barProps={{ radius: hBarRadius }}
                  withBarValueLabel
                  valueLabelProps={{ position: 'right', fill: 'var(--mantine-color-text)', fontSize: 12 }}
                  gridAxis="none"
                  tickLine="none"
                  yAxisProps={{ width: 96 }}
                  maxBarWidth={18}
                />
              </ChartCard>
            </Grid.Col>
            <Grid.Col span={{ base: 12, lg: 6 }}>
              <ChartCard
                title="Deal pipeline"
                description={`Deal value by stage, ${currency}`}
                loading={isLoading}
                table={{ columns: ['Stage', 'Deals', 'Value'], rows: (crm?.deal_pipeline ?? []).map((s) => [s.stage, s.count, fmtMoney(s.value, currency)]) }}
              >
                <BarChart
                  h={260}
                  data={(crm?.deal_pipeline ?? []).map((s) => ({ stage: s.stage, Value: s.value, deals: s.count }))}
                  dataKey="stage"
                  series={[{ name: 'Value', color: viz.series }]}
                  barProps={{ radius: barRadius }}
                  valueFormatter={(v) => fmtCompact(v)}
                  gridAxis="y"
                  tickLine="none"
                  maxBarWidth={36}
                />
              </ChartCard>
            </Grid.Col>
            <Grid.Col span={{ base: 12, lg: 6 }}>
              <ChartCard
                title="Sales value"
                description={`Won deal value per ${data?.range.bucket ?? 'day'}, ${currency}`}
                loading={isLoading}
                table={{ columns: ['Date', 'Won value'], rows: (crm?.deal_value ?? []).map((p) => [bucketLabel(p.date), fmtMoney(p.value, currency)]) }}
              >
                <BarChart
                  h={260}
                  data={(crm?.deal_value ?? []).map((p) => ({ date: bucketLabel(p.date), Won: p.value }))}
                  dataKey="date"
                  series={[{ name: 'Won', color: viz.series }]}
                  barProps={{ radius: barRadius }}
                  valueFormatter={(v) => fmtCompact(v)}
                  gridAxis="y"
                  tickLine="none"
                  xAxisProps={{ interval: 'preserveStartEnd', minTickGap: 16 }}
                  maxBarWidth={22}
                />
              </ChartCard>
            </Grid.Col>
          </Grid>
        )}

        {hr && <AttendanceTrendChart data={hr.attendance_trend} />}

        {!isLoading && !crm && !hr && (
          <Stack align="center" py="xl" gap={4}>
            <IconAlertTriangle size={28} opacity={0.5} />
            <Text c="dimmed">Your role has no dashboard widgets. Use the menu to open your sections.</Text>
          </Stack>
        )}
      </Stack>
    </>
  )
}
