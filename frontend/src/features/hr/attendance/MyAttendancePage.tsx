import { useState } from 'react'
import { Card, Group, SimpleGrid, Stack } from '@mantine/core'
import { MonthPickerInput } from '@mantine/dates'
import { useQuery } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { fmtMinutes, tenantNow } from '@/lib/format'
import { PageHeader } from '@/components/PageHeader'
import { StatCard } from '@/components/StatCard'
import { ErrorState } from '@/components/ErrorState'
import { LoadingState } from '@/components/LoadingState'
import type { CalendarDay } from '@/types/models'
import { CalendarLegend, MonthCalendar } from './MonthCalendar'
import { AttendanceRecordDrawer } from './AttendanceRecordDrawer'

interface EmployeeCalendar {
  month: string
  employee: { id: number; full_name: string }
  days: CalendarDay[]
}

/** Month view for one employee: used for "My attendance" and inside employee profiles. */
export function EmployeeAttendance({ employeeId }: { employeeId?: number }) {
  const [month, setMonth] = useState(tenantNow().format('YYYY-MM'))
  const [recordId, setRecordId] = useState<number | null>(null)
  const { data, isLoading, error } = useQuery({
    queryKey: ['attendance', 'calendar', 'employee', employeeId ?? 'me', month],
    queryFn: () => api.get<EmployeeCalendar>('/attendance/calendar', { month, employee_id: employeeId }),
  })

  if (isLoading) return <LoadingState />
  if (error || !data) return <ErrorState error={error} />

  const counted = data.days.filter((d) => d.status)
  const count = (codes: string[]) => counted.filter((d) => d.code && codes.includes(d.code)).length
  // A day can be late and incomplete at once, so lateness is counted from minutes, not from the day code.
  const lateDays = data.days.filter((d) => (d.late_minutes ?? 0) > 0).length

  return (
    <Stack gap="md">
      <Group justify="space-between" wrap="wrap">
        <MonthPickerInput value={`${month}-01`} onChange={(v) => v && setMonth(String(v).slice(0, 7))} valueFormat="MMMM YYYY" w={180} aria-label="Month" />
        <CalendarLegend />
      </Group>
      <SimpleGrid cols={{ base: 2, sm: 5 }} spacing="sm">
        <StatCard label="Days present" value={count(['P', 'L', 'EL', 'I'])} />
        <StatCard label="Days late" value={lateDays} hint={`${fmtMinutes(data.days.reduce((s, d) => s + (d.late_minutes ?? 0), 0))} in total`} tone={lateDays ? 'saffron' : undefined} />
        <StatCard label="Absent" value={count(['A'])} tone={count(['A']) ? 'red' : undefined} />
        <StatCard label="Worked" value={fmtMinutes(data.days.reduce((s, d) => s + (d.worked_minutes ?? 0), 0))} />
        <StatCard label="Overtime" value={fmtMinutes(data.days.reduce((s, d) => s + (d.overtime_minutes ?? 0), 0))} />
      </SimpleGrid>
      <Card>
        <MonthCalendar days={data.days} onSelect={(d) => d.record_id && setRecordId(d.record_id)} />
      </Card>
      <AttendanceRecordDrawer recordId={recordId} onClose={() => setRecordId(null)} />
    </Stack>
  )
}

export default function MyAttendancePage() {
  return (
    <>
      <PageHeader title="My attendance" description="Your check-ins and working time as recorded by the terminals." />
      <EmployeeAttendance />
    </>
  )
}
