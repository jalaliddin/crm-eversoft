import { Avatar, Card, Grid, Group, SimpleGrid, Table, Text, Title } from '@mantine/core'
import { BarChart, LineChart } from '@mantine/charts'
import { IconActivityHeartbeat, IconClockExclamation, IconUserCheck, IconUsers, IconUserX } from '@tabler/icons-react'
import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router'
import { api } from '@/lib/api'
import { dayjs, fmtMinutes, fmtTime, initials } from '@/lib/format'
import { viz } from '@/lib/viz'
import { PageHeader } from '@/components/PageHeader'
import { StatCard } from '@/components/StatCard'
import { StatusBadge } from '@/components/StatusBadge'
import { ErrorState } from '@/components/ErrorState'
import { ShiftBar } from '@/components/ShiftBar'
import { ChartCard } from '@/components/charts/ChartCard'
import { AttendanceTrendChart, type TrendPoint } from '@/components/charts/AttendanceTrendChart'
import type { AttendanceSummary, MonitorRow } from '@/types/models'

interface HrDashboard {
  date: string
  summary: AttendanceSummary
  trend: (TrendPoint & { early_leave: number })[]
  by_department: { department: string; present: number; late: number; absent: number }[]
  live: MonitorRow[]
}

export default function HrDashboardPage() {
  const { data, isLoading, error, refetch } = useQuery({ queryKey: ['attendance', 'dashboard'], queryFn: () => api.get<HrDashboard>('/attendance/dashboard'), refetchInterval: 60_000 })

  if (error) return <ErrorState error={error} onRetry={() => void refetch()} />
  const s = data?.summary

  return (
    <>
      <PageHeader title="HR overview" description={data ? dayjs(data.date).format('dddd, D MMMM YYYY') : undefined} />
      <SimpleGrid cols={{ base: 2, sm: 3, lg: 5 }} spacing="sm" mb="lg">
        <StatCard loading={isLoading} label="Total employees" value={s?.total_employees ?? 0} icon={<IconUsers size={18} />} to="/hr/employees" />
        <StatCard loading={isLoading} label="Present today" value={s?.present ?? 0} icon={<IconUserCheck size={18} />} tone="teal" to="/hr/attendance/monitor?status=present" />
        <StatCard loading={isLoading} label="Late today" value={s?.late ?? 0} icon={<IconClockExclamation size={18} />} tone="saffron" to="/hr/attendance/monitor?status=late" />
        <StatCard loading={isLoading} label="Absent today" value={s?.absent ?? 0} icon={<IconUserX size={18} />} tone={s?.absent ? 'red' : undefined} to="/hr/attendance/monitor?status=absent" />
        <StatCard loading={isLoading} label="Working now" value={s?.working ?? 0} icon={<IconActivityHeartbeat size={18} />} to="/hr/attendance/monitor?status=working" />
      </SimpleGrid>

      <Grid gap="md">
        <Grid.Col span={{ base: 12, lg: 8 }}>
          <AttendanceTrendChart data={data?.trend} loading={isLoading} title="Attendance, last 30 days" />
        </Grid.Col>
        <Grid.Col span={{ base: 12, lg: 4 }}>
          <ChartCard
            title="Late arrivals"
            description="Employees late per day"
            loading={isLoading}
            table={{ columns: ['Day', 'Late'], rows: (data?.trend ?? []).map((p) => [dayjs(p.date).format('DD MMM'), p.late]) }}
          >
            <LineChart
              h={260}
              data={(data?.trend ?? []).map((p) => ({ day: dayjs(p.date).format('DD MMM'), Late: p.late }))}
              dataKey="day"
              series={[{ name: 'Late', color: viz.warning }]}
              curveType="linear"
              strokeWidth={2}
              withDots={false}
              gridAxis="y"
              tickLine="none"
              xAxisProps={{ interval: 'preserveStartEnd', minTickGap: 24 }}
            />
          </ChartCard>
        </Grid.Col>
        <Grid.Col span={{ base: 12, lg: 5 }}>
          <ChartCard
            title="Departments today"
            description="Employees by arrival"
            loading={isLoading}
            empty={!isLoading && !data?.by_department.length}
            emptyMessage="Nobody has checked in yet today."
            table={{ columns: ['Department', 'On time', 'Late', 'Absent'], rows: (data?.by_department ?? []).map((d) => [d.department, d.present - d.late, d.late, d.absent]) }}
          >
            <BarChart
              h={260}
              orientation="vertical"
              type="stacked"
              data={(data?.by_department ?? []).map((d) => ({ department: d.department, 'On time': Math.max(0, d.present - d.late), Late: d.late, Absent: d.absent }))}
              dataKey="department"
              series={[{ name: 'On time', color: viz.good }, { name: 'Late', color: viz.warning }, { name: 'Absent', color: viz.critical }]}
              withLegend
              legendProps={{ verticalAlign: 'top', height: 36 }}
              barProps={{ stroke: 'var(--mantine-color-body)', strokeWidth: 2 }}
              yAxisProps={{ width: 110 }}
              gridAxis="none"
              tickLine="none"
              maxBarWidth={18}
            />
          </ChartCard>
        </Grid.Col>
        <Grid.Col span={{ base: 12, lg: 7 }}>
          <Card h="100%">
            <Group justify="space-between" mb="sm">
              <Title order={3} fz="md">Latest arrivals</Title>
              <Text component={Link} to="/hr/attendance/monitor" size="sm" c="khiva">Open monitor</Text>
            </Group>
            <Table.ScrollContainer minWidth={520}>
              <Table verticalSpacing={6}>
                <Table.Thead>
                  <Table.Tr><Table.Th>Employee</Table.Th><Table.Th>Department</Table.Th><Table.Th>Check-in</Table.Th><Table.Th>Day</Table.Th><Table.Th>Status</Table.Th><Table.Th ta="right">Worked</Table.Th></Table.Tr>
                </Table.Thead>
                <Table.Tbody>
                  {(data?.live ?? []).map((r) => (
                    <Table.Tr key={r.employee.id}>
                      <Table.Td><Group gap="xs" wrap="nowrap"><Avatar size={26} radius="xl" src={r.employee.photo_url} color="khiva">{initials(r.employee.full_name)}</Avatar><Text size="sm" truncate maw={160}>{r.employee.full_name}</Text></Group></Table.Td>
                      <Table.Td><Text size="sm" c="dimmed" truncate maw={140}>{r.department ?? '—'}</Text></Table.Td>
                      <Table.Td className="tabular">{fmtTime(r.check_in)}</Table.Td>
                      <Table.Td><ShiftBar start={r.schedule.start} end={r.schedule.end} checkIn={r.check_in} checkOut={r.check_out} lateMinutes={r.late_minutes} earlyMinutes={r.early_leave_minutes} compact /></Table.Td>
                      <Table.Td><StatusBadge status={r.live_status} /></Table.Td>
                      <Table.Td ta="right" className="tabular">{r.worked_minutes ? fmtMinutes(r.worked_minutes) : '—'}</Table.Td>
                    </Table.Tr>
                  ))}
                  {!isLoading && !data?.live.length && (
                    <Table.Tr><Table.Td colSpan={6}><Text c="dimmed" size="sm" ta="center" py="md">Nobody has checked in yet today.</Text></Table.Td></Table.Tr>
                  )}
                </Table.Tbody>
              </Table>
            </Table.ScrollContainer>
          </Card>
        </Grid.Col>
      </Grid>
    </>
  )
}
