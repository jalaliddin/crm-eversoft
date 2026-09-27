import { useState } from 'react'
import { Avatar, Badge, Box, Group, Paper, Select, SimpleGrid, Stack, Text, UnstyledButton } from '@mantine/core'
import { DateInput } from '@mantine/dates'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { api } from '@/lib/api'
import type { Paginated, PageMeta } from '@/types/api'
import { fmtMinutes, fmtTime, fromNow, initials, today } from '@/lib/format'
import { PageHeader } from '@/components/PageHeader'
import { DataTable, type Column } from '@/components/DataTable'
import { StatusBadge } from '@/components/StatusBadge'
import { statusColor } from '@/lib/statusColors'
import { SearchInput } from '@/components/SearchInput'
import { ShiftBar, ShiftBarLegend } from '@/components/ShiftBar'
import { FilterDrawer } from '@/components/FilterDrawer'
import { useListParams } from '@/hooks/useListParams'
import { useDepartments, useDevices, useOptions, toOptions } from '@/hooks/useLookups'
import type { AttendanceSummary, MonitorRow } from '@/types/models'
import { AttendanceRecordDrawer } from './AttendanceRecordDrawer'

type MonitorMeta = PageMeta & { date: string; summary: AttendanceSummary; refresh_seconds: number; generated_at: string }

const TILES: { key: keyof AttendanceSummary; status: string; filter: string }[] = [
  { key: 'present', status: 'present', filter: 'present' },
  { key: 'late', status: 'late', filter: 'late' },
  { key: 'absent', status: 'absent', filter: 'absent' },
  { key: 'working', status: 'working', filter: 'working' },
  { key: 'left', status: 'left', filter: 'left' },
  { key: 'incomplete', status: 'incomplete', filter: 'incomplete' },
]

const columns: Column<MonitorRow>[] = [
  {
    key: 'employee',
    header: 'Employee',
    render: (r) => (
      <Group gap="sm" wrap="nowrap">
        <Avatar src={r.employee.photo_url} size={32} radius="xl" color="khiva">{initials(r.employee.full_name)}</Avatar>
        <div style={{ minWidth: 0 }}>
          <Text size="sm" fw={600} truncate>{r.employee.full_name}</Text>
          <Text size="xs" c="dimmed" truncate>{r.department ?? '—'}</Text>
        </div>
      </Group>
    ),
  },
  { key: 'schedule', header: 'Schedule', visibleFrom: 'lg', render: (r) => <Text size="sm" className="tabular" style={{ whiteSpace: 'nowrap' }}>{r.schedule.holiday ? r.schedule.holiday : !r.schedule.is_working_day ? 'Day off' : r.schedule.start ? `${r.schedule.start}–${r.schedule.end}` : 'Flexible'}</Text> },
  { key: 'day', header: 'Day', visibleFrom: 'md', render: (r) => <ShiftBar start={r.schedule.start} end={r.schedule.end} checkIn={r.check_in} checkOut={r.check_out} lateMinutes={r.late_minutes} earlyMinutes={r.early_leave_minutes} compact /> },
  { key: 'in', header: 'Check-in', render: (r) => <span className="tabular">{fmtTime(r.check_in)}</span> },
  { key: 'out', header: 'Check-out', render: (r) => <span className="tabular">{fmtTime(r.check_out)}</span> },
  { key: 'worked', header: 'Worked', align: 'right', visibleFrom: 'sm', render: (r) => <span className="tabular">{r.worked_minutes ? fmtMinutes(r.worked_minutes) : '—'}</span> },
  { key: 'late', header: 'Late', align: 'right', visibleFrom: 'md', render: (r) => <Text size="sm" className="tabular" c={r.late_minutes ? 'saffron.7' : 'dimmed'}>{r.late_minutes ? fmtMinutes(r.late_minutes) : '—'}</Text> },
  { key: 'early', header: 'Early leave', align: 'right', visibleFrom: 'lg', render: (r) => <Text size="sm" className="tabular" c={r.early_leave_minutes ? 'orange.7' : 'dimmed'}>{r.early_leave_minutes ? fmtMinutes(r.early_leave_minutes) : '—'}</Text> },
  { key: 'status', header: 'Status', render: (r) => <Group gap={4} wrap="nowrap"><StatusBadge status={r.live_status} />{r.is_manual && <Badge size="xs" color="grape">edited</Badge>}</Group> },
  { key: 'source', header: 'Source', visibleFrom: 'xl', render: (r) => <Text size="xs" c="dimmed">{r.device ?? r.source ?? '—'}</Text> },
]

/**
 * Live attendance. Polls on the tenant's refresh interval; the endpoint is stateless,
 * so switching to WebSockets later only replaces the refetch trigger.
 */
export default function MonitorPage() {
  const { t } = useTranslation()
  const list = useListParams({ date: today(), per_page: '50' })
  const { params } = list
  const [recordId, setRecordId] = useState<number | null>(null)
  const { data: departments } = useDepartments()
  const { data: devices } = useDevices()
  const sources = useOptions('attendance_sources')
  const isToday = params.date === today()

  const { data, isLoading, isFetching, error, refetch } = useQuery({
    queryKey: ['attendance', 'monitor', list.query],
    queryFn: () => api.list<MonitorRow, MonitorMeta>('/attendance/monitor', list.query) as Promise<Paginated<MonitorRow, MonitorMeta>>,
    refetchInterval: (q) => (isToday ? ((q.state.data?.meta.refresh_seconds ?? 30) * 1000) : false),
    refetchIntervalInBackground: false,
    placeholderData: (p) => p,
  })
  const summary = data?.meta.summary

  return (
    <>
      <PageHeader
        title="Attendance monitor"
        description={isToday ? `Live · updated ${fromNow(data?.meta.generated_at)}` : 'Past day'}
        actions={<DateInput value={params.date} onChange={(v) => list.set({ date: v ? String(v).slice(0, 10) : today() })} valueFormat="DD.MM.YYYY" maxDate={today()} w={150} aria-label="Date" />}
      />

      <SimpleGrid cols={{ base: 2, sm: 3, lg: 6 }} spacing="sm" mb="md">
        {TILES.map((tile) => {
          const active = params.status === tile.filter
          return (
            <UnstyledButton key={tile.key} onClick={() => list.set({ status: active ? null : tile.filter })} aria-pressed={active}>
              <Paper withBorder p="md" radius="lg" style={{ borderColor: active ? 'var(--mantine-primary-color-filled)' : 'var(--app-border)', borderWidth: active ? 2 : 1 }}>
                <Group justify="space-between" wrap="nowrap">
                  <Text size="sm" c="dimmed">{t(`status.${tile.status}`)}</Text>
                  <Box w={10} h={10} style={{ borderRadius: 5, background: `var(--mantine-color-${statusColor(tile.status)}-6)` }} aria-hidden />
                </Group>
                <Text fz={26} fw={650} className="tabular">{summary ? summary[tile.key] : '—'}</Text>
              </Paper>
            </UnstyledButton>
          )
        })}
      </SimpleGrid>

      <Group mb="md" gap="sm" wrap="wrap" justify="space-between">
        <Group gap="sm" wrap="wrap">
          <SearchInput value={params.search ?? ''} onChange={(search) => list.set({ search })} placeholder="Employee name or number" />
          <Select placeholder="All departments" data={toOptions(departments)} value={params.department_id ?? null} onChange={(v) => list.set({ department_id: v })} clearable w={200} aria-label="Department" />
          <FilterDrawer activeCount={list.activeCount(['device_id', 'source'])} onReset={() => list.reset(['device_id', 'source'])}>
            <Select label="Device" data={toOptions(devices)} value={params.device_id ?? null} onChange={(v) => list.set({ device_id: v })} clearable />
            <Select label="Source" data={sources} value={params.source ?? null} onChange={(v) => list.set({ source: v })} clearable />
          </FilterDrawer>
        </Group>
        <Stack gap={0} visibleFrom="md"><ShiftBarLegend /></Stack>
      </Group>

      <DataTable
        columns={columns}
        rows={data?.data}
        meta={data?.meta}
        loading={isLoading || (isFetching && !isToday)}
        error={error}
        onRetry={() => void refetch()}
        {...list.tableProps}
        rowKey={(r) => r.employee.id}
        onRowClick={(r) => r.record_id && setRecordId(r.record_id)}
        empty={{ title: params.status ? 'Nobody in this group' : 'No employees to track', message: params.status ? 'Clear the status filter to see everyone.' : 'Add employees and assign schedules to start monitoring.' }}
        minWidth={900}
      />
      <AttendanceRecordDrawer recordId={recordId} onClose={() => setRecordId(null)} />
    </>
  )
}
