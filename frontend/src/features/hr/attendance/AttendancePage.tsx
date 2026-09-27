import { useState } from 'react'
import { Badge, Button, Group, Menu, Select, Text } from '@mantine/core'
import { DatePickerInput } from '@mantine/dates'
import { IconDownload, IconPlus, IconRefresh } from '@tabler/icons-react'
import { useQuery } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { fmtDate, fmtMinutes, fmtTime, tenantNow } from '@/lib/format'
import { notifyError } from '@/lib/notify'
import { PageHeader } from '@/components/PageHeader'
import { DataTable, type Column } from '@/components/DataTable'
import { StatusBadge } from '@/components/StatusBadge'
import { FilterDrawer } from '@/components/FilterDrawer'
import { useListParams } from '@/hooks/useListParams'
import { useDepartments, useDevices, useEmployeesLookup, useOptions, toOptions } from '@/hooks/useLookups'
import { useApiMutation } from '@/hooks/useApiMutation'
import { Can } from '@/features/auth/guards'
import type { AttendanceRecord } from '@/types/models'
import { AttendanceRecordDrawer } from './AttendanceRecordDrawer'
import { ManualAttendanceModal } from './ManualAttendanceModal'

const columns: Column<AttendanceRecord>[] = [
  { key: 'date', header: 'Date', render: (r) => <span className="tabular">{fmtDate(r.date)}</span> },
  { key: 'employee', header: 'Employee', render: (r) => (<div><Text size="sm" fw={600}>{r.employee?.full_name}</Text><Text size="xs" c="dimmed">{r.employee?.department ?? ''}</Text></div>) },
  { key: 'in', header: 'Check-in', render: (r) => <span className="tabular">{fmtTime(r.check_in)}</span> },
  { key: 'out', header: 'Check-out', render: (r) => <span className="tabular">{fmtTime(r.check_out)}</span> },
  { key: 'worked', header: 'Worked', align: 'right', render: (r) => <span className="tabular">{fmtMinutes(r.worked_minutes)}</span>, visibleFrom: 'sm' },
  { key: 'late', header: 'Late', align: 'right', render: (r) => <Text size="sm" className="tabular" c={r.late_minutes ? 'saffron.7' : 'dimmed'}>{r.late_minutes ? fmtMinutes(r.late_minutes) : '—'}</Text>, visibleFrom: 'md' },
  { key: 'early', header: 'Early leave', align: 'right', render: (r) => <Text size="sm" className="tabular" c={r.early_leave_minutes ? 'orange.7' : 'dimmed'}>{r.early_leave_minutes ? fmtMinutes(r.early_leave_minutes) : '—'}</Text>, visibleFrom: 'md' },
  { key: 'status', header: 'Status', render: (r) => <Group gap={4} wrap="nowrap"><StatusBadge status={r.status} />{r.is_manual && <Badge color="grape" size="xs">edited</Badge>}</Group> },
  { key: 'source', header: 'Source', render: (r) => r.source, visibleFrom: 'lg' },
]

export default function AttendancePage() {
  const now = tenantNow()
  const list = useListParams({ from: now.startOf('month').format('YYYY-MM-DD'), to: now.format('YYYY-MM-DD') })
  const { params } = list
  const [recordId, setRecordId] = useState<number | null>(null)
  const [manual, setManual] = useState(false)
  const statuses = useOptions('attendance_statuses')
  const sources = useOptions('attendance_sources')
  const { data: departments } = useDepartments()
  const { data: employees = [] } = useEmployeesLookup()
  const { data: devices } = useDevices()

  const { data, isLoading, isFetching, error, refetch } = useQuery({
    queryKey: ['attendance', 'list', list.query],
    queryFn: () => api.list<AttendanceRecord>('/attendance', list.query),
    placeholderData: (p) => p,
  })

  const recalc = useApiMutation<{ recalculated: number }>({
    mutationFn: () => api.post('/attendance/recalculate', { from: params.from, to: params.to }),
    invalidate: [['attendance']],
  })

  const exportDaily = (format: string) => void api.download('/reports/attendance/monthly', { month: params.from?.slice(0, 7), department_id: params.department_id, export: format }, `attendance.${format}`).catch(notifyError)

  return (
    <>
      <PageHeader
        title="Attendance"
        description="Daily records from terminals and manual entries."
        actions={
          <>
            <Can permission="attendance.correct">
              <Button variant="default" leftSection={<IconRefresh size={16} />} loading={recalc.isPending} onClick={() => recalc.mutate()}>Recalculate period</Button>
            </Can>
            <Can permission="attendance.export">
              <Menu position="bottom-end">
                <Menu.Target><Button variant="default" leftSection={<IconDownload size={16} />}>Export month</Button></Menu.Target>
                <Menu.Dropdown>
                  <Menu.Item onClick={() => exportDaily('xlsx')}>Excel (.xlsx)</Menu.Item>
                  <Menu.Item onClick={() => exportDaily('csv')}>CSV</Menu.Item>
                  <Menu.Item onClick={() => exportDaily('pdf')}>PDF</Menu.Item>
                </Menu.Dropdown>
              </Menu>
            </Can>
            <Can permission="attendance.manage">
              <Button leftSection={<IconPlus size={16} />} onClick={() => setManual(true)}>Add manually</Button>
            </Can>
          </>
        }
      />
      <Group mb="md" gap="sm" wrap="wrap">
        <DatePickerInput
          type="range"
          value={[params.from ?? null, params.to ?? null]}
          onChange={([a, b]) => list.set({ from: a ? String(a).slice(0, 10) : null, to: b ? String(b).slice(0, 10) : null })}
          valueFormat="DD.MM.YYYY"
          w={240}
          aria-label="Period"
        />
        <Select placeholder="All employees" data={employees} value={params.employee_id ?? null} onChange={(v) => list.set({ employee_id: v })} searchable clearable w={240} aria-label="Employee" />
        <Select placeholder="Any status" data={statuses} value={params.status ?? null} onChange={(v) => list.set({ status: v })} clearable w={160} aria-label="Status" />
        <FilterDrawer activeCount={list.activeCount(['department_id', 'source', 'device_id', 'manual'])} onReset={() => list.reset(['department_id', 'source', 'device_id', 'manual'])}>
          <Select label="Department" data={toOptions(departments)} value={params.department_id ?? null} onChange={(v) => list.set({ department_id: v })} clearable />
          <Select label="Source" data={sources} value={params.source ?? null} onChange={(v) => list.set({ source: v })} clearable />
          <Select label="Device" data={toOptions(devices)} value={params.device_id ?? null} onChange={(v) => list.set({ device_id: v })} clearable />
          <Select label="Entry" data={[{ value: '1', label: 'Manually corrected' }, { value: '0', label: 'From device only' }]} value={params.manual ?? null} onChange={(v) => list.set({ manual: v })} clearable />
        </FilterDrawer>
      </Group>
      <DataTable
        columns={columns}
        rows={data?.data}
        meta={data?.meta}
        loading={isLoading || isFetching}
        error={error}
        onRetry={() => void refetch()}
        {...list.tableProps}
        onRowClick={(r) => setRecordId(r.id)}
        empty={{ title: 'No attendance records', message: 'Records appear after the terminals sync or when HR adds them manually. Weekends and holidays are not stored.' }}
      />
      <AttendanceRecordDrawer recordId={recordId} onClose={() => setRecordId(null)} />
      <ManualAttendanceModal opened={manual} onClose={() => setManual(false)} />
    </>
  )
}
