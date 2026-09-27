import { useState } from 'react'
import { ActionIcon, Avatar, Badge, Button, Card, FileButton, Grid, Group, Select, Stack, Table, Tabs, Text, TextInput } from '@mantine/core'
import { DateInput } from '@mantine/dates'
import { IconCamera, IconPencil, IconPlus, IconTrash } from '@tabler/icons-react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useNavigate, useParams, useSearchParams } from 'react-router'
import { api } from '@/lib/api'
import { fmtDate, fmtDateTime, fmtMinutes, fmtMoney, humanize, initials, today } from '@/lib/format'
import { notifyError, notifySuccess } from '@/lib/notify'
import { useApiMutation } from '@/hooks/useApiMutation'
import { useDevices, useSchedules, toOptions } from '@/hooks/useLookups'
import { PageHeader } from '@/components/PageHeader'
import { StatusBadge } from '@/components/StatusBadge'
import { DataTable } from '@/components/DataTable'
import { EmptyState } from '@/components/EmptyState'
import { ErrorState } from '@/components/ErrorState'
import { LoadingState } from '@/components/LoadingState'
import { EntityModal } from '@/components/EntityModal'
import { confirmAction } from '@/components/ConfirmDialog'
import { useAuth } from '@/features/auth/useAuth'
import { DetailList } from '@/features/crm/shared/DetailList'
import { DocumentsPanel } from '@/features/crm/shared/DocumentsPanel'
import { NotesPanel } from '@/features/crm/shared/NotesPanel'
import { CustomFieldValues } from '@/features/crm/shared/CustomFieldInputs'
import type { AuditLog, DeviceIdentifier, Employee, PayrollRecord, Penalty } from '@/types/models'
import { EmployeeAttendance } from '../attendance/MyAttendancePage'
import { EmployeeForm } from './EmployeeForm'

function ScheduleTab({ employee, canWrite }: { employee: Employee; canWrite: boolean }) {
  const { data: schedules } = useSchedules()
  const [scheduleId, setScheduleId] = useState<string | null>(null)
  const [from, setFrom] = useState<string | null>(today())
  const assign = useApiMutation({
    mutationFn: () => api.post(`/employees/${employee.id}/schedule`, { work_schedule_id: Number(scheduleId), effective_from: from }),
    invalidate: [['employees']],
    successMessage: 'Schedule assigned. Recalculate attendance if it applies to past days.',
  })

  return (
    <Card>
      {canWrite && (
        <Group align="flex-end" mb="md" wrap="wrap">
          <Select label="New schedule" data={toOptions(schedules)} value={scheduleId} onChange={setScheduleId} w={260} />
          <DateInput label="Starting" value={from} onChange={setFrom} valueFormat="DD.MM.YYYY" w={160} />
          <Button disabled={!scheduleId || !from} loading={assign.isPending} onClick={() => assign.mutate()}>Assign</Button>
        </Group>
      )}
      {!employee.schedule_assignments?.length ? (
        <EmptyState compact title="Using the default schedule" message="Assign a schedule to use different hours for this person." />
      ) : (
        <Table verticalSpacing="sm">
          <Table.Thead><Table.Tr><Table.Th>Schedule</Table.Th><Table.Th>From</Table.Th><Table.Th>Until</Table.Th></Table.Tr></Table.Thead>
          <Table.Tbody>
            {employee.schedule_assignments.map((a) => (
              <Table.Tr key={a.id}>
                <Table.Td>{a.name}</Table.Td>
                <Table.Td className="tabular">{fmtDate(a.effective_from)}</Table.Td>
                <Table.Td className="tabular">{a.effective_to ? fmtDate(a.effective_to) : <Badge variant="light">Current</Badge>}</Table.Td>
              </Table.Tr>
            ))}
          </Table.Tbody>
        </Table>
      )}
    </Card>
  )
}

function DeviceIdsTab({ employee }: { employee: Employee }) {
  const { can } = useAuth()
  const { data: devices } = useDevices()
  const [editing, setEditing] = useState<DeviceIdentifier | null | undefined>(undefined)
  const [form, setForm] = useState({ identifier: '', device_id: null as string | null, card_number: '' })
  const key = ['employees', employee.id, 'identifiers']
  const { data = [] } = useQuery({ queryKey: key, queryFn: () => api.get<DeviceIdentifier[]>(`/employees/${employee.id}/identifiers`) })
  const save = useApiMutation({
    mutationFn: () => {
      const payload = { identifier: form.identifier, device_id: form.device_id ? Number(form.device_id) : null, card_number: form.card_number || null }
      return editing ? api.put(`/employees/${employee.id}/identifiers/${editing.id}`, payload) : api.post(`/employees/${employee.id}/identifiers`, payload)
    },
    invalidate: [key, ['employees', employee.id]],
    successMessage: 'Terminal ID saved. Use “Reprocess” on the devices page to attach earlier unmatched punches.',
    onSuccess: () => setEditing(undefined),
  })
  const remove = useApiMutation<null, number>({ mutationFn: (id) => api.delete(`/employees/${employee.id}/identifiers/${id}`), invalidate: [key], successMessage: 'Removed.' })
  const open = (row: DeviceIdentifier | null) => {
    setForm({ identifier: row?.identifier ?? '', device_id: row?.device_id ? String(row.device_id) : null, card_number: row?.card_number ?? '' })
    setEditing(row)
  }

  return (
    <Card>
      <Group justify="space-between" mb="sm">
        <Text size="sm" c="dimmed">The ID this person has on each terminal (Hikvision “employeeNo”). It does not need to match the employee number.</Text>
        {can('devices.manage') && <Button size="xs" leftSection={<IconPlus size={14} />} onClick={() => open(null)}>Add ID</Button>}
      </Group>
      {data.length === 0 ? <EmptyState compact title="No terminal IDs" message="Without an ID, this person’s punches stay unmatched." /> : (
        <Table verticalSpacing="sm">
          <Table.Thead><Table.Tr><Table.Th>Terminal ID</Table.Th><Table.Th>Device</Table.Th><Table.Th>Card</Table.Th><Table.Th>Status</Table.Th><Table.Th /></Table.Tr></Table.Thead>
          <Table.Tbody>
            {data.map((row) => (
              <Table.Tr key={row.id}>
                <Table.Td className="tabular" fw={600}>{row.identifier}</Table.Td>
                <Table.Td>{row.device?.name ?? 'All devices'}</Table.Td>
                <Table.Td className="tabular">{row.card_number ?? '—'}</Table.Td>
                <Table.Td><StatusBadge status={row.status} /></Table.Td>
                <Table.Td ta="right">
                  {can('devices.manage') && (
                    <>
                      <ActionIcon variant="subtle" aria-label="Edit" onClick={() => open(row)}><IconPencil size={16} /></ActionIcon>
                      <ActionIcon variant="subtle" color="red" aria-label="Remove" onClick={() => confirmAction({ title: 'Remove terminal ID?', message: 'Future punches with this ID will be unmatched.', confirmLabel: 'Remove', danger: true, onConfirm: () => remove.mutate(row.id) })}><IconTrash size={16} /></ActionIcon>
                    </>
                  )}
                </Table.Td>
              </Table.Tr>
            ))}
          </Table.Tbody>
        </Table>
      )}
      <EntityModal opened={editing !== undefined} onClose={() => setEditing(undefined)} title="Terminal ID" formId="identifier-form" submitting={save.isPending}>
        <form id="identifier-form" onSubmit={(e) => { e.preventDefault(); save.mutate() }}>
          <Stack gap="sm">
            <TextInput label="Terminal ID (employeeNo)" required value={form.identifier} onChange={(e) => setForm({ ...form, identifier: e.currentTarget.value })} />
            <Select label="Device" placeholder="All devices" data={toOptions(devices)} value={form.device_id} onChange={(v) => setForm({ ...form, device_id: v })} clearable />
            <TextInput label="Card number" value={form.card_number} onChange={(e) => setForm({ ...form, card_number: e.currentTarget.value })} />
          </Stack>
        </form>
      </EntityModal>
    </Card>
  )
}

export default function EmployeeProfilePage() {
  const id = Number(useParams().id)
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { can } = useAuth()
  const [params, setParams] = useSearchParams()
  const tab = params.get('tab') ?? 'overview'
  const [editing, setEditing] = useState(false)
  const { data: employee, isLoading, error, refetch } = useQuery({ queryKey: ['employees', id], queryFn: () => api.get<Employee>(`/employees/${id}`) })
  const penalties = useQuery({ queryKey: ['penalties', 'employee', id], queryFn: () => api.list<Penalty>(`/employees/${id}/penalties`, { per_page: 50 }), enabled: tab === 'penalties' && can('penalties.view') })
  const payroll = useQuery({ queryKey: ['payroll', 'employee', id], queryFn: () => api.get<(PayrollRecord & { period?: { year: number; month: number } })[]>(`/employees/${id}/payroll`), enabled: tab === 'payroll' && can('payroll.view') })
  const history = useQuery({ queryKey: ['audit', 'employee', id], queryFn: () => api.list<AuditLog>('/audit-logs', { auditable_type: 'employee', auditable_id: id, per_page: 50 }), enabled: tab === 'history' && can('audit.view') })
  const remove = useApiMutation({ mutationFn: () => api.delete(`/employees/${id}`), invalidate: [['employees']], successMessage: 'Employee deleted.', onSuccess: () => navigate('/hr/employees') })

  if (isLoading) return <LoadingState />
  if (error || !employee) return <ErrorState error={error} onRetry={() => void refetch()} />

  const canWrite = can('employees.update')
  const uploadPhoto = async (file: File | null) => {
    if (!file) return
    try {
      await api.upload(`/employees/${id}/photo`, 'photo', file)
      await queryClient.invalidateQueries({ queryKey: ['employees', id] })
      notifySuccess('Photo updated.')
    } catch (e) {
      notifyError(e)
    }
  }

  return (
    <>
      <PageHeader
        breadcrumbs={can('employees.view') ? [{ label: 'Employees', to: '/hr/employees' }, { label: employee.full_name }] : undefined}
        title={
          <Group gap="md" wrap="nowrap">
            <div style={{ position: 'relative' }}>
              <Avatar src={employee.photo_url} size={56} radius="xl" color="khiva">{initials(`${employee.first_name} ${employee.last_name}`)}</Avatar>
              {canWrite && (
                <FileButton onChange={uploadPhoto} accept="image/png,image/jpeg,image/webp">
                  {(props) => <ActionIcon {...props} size="sm" radius="xl" variant="default" pos="absolute" bottom={-2} right={-2} aria-label="Change photo"><IconCamera size={12} /></ActionIcon>}
                </FileButton>
              )}
            </div>
            <div>
              <Group gap="sm">{employee.full_name}<StatusBadge status={employee.status} /></Group>
              <Text size="sm" c="dimmed" fw={400}>{[employee.position?.name, employee.department?.name, `No. ${employee.employee_number}`].filter(Boolean).join(' · ')}</Text>
            </div>
          </Group>
        }
        actions={
          <>
            {canWrite && <Button variant="default" leftSection={<IconPencil size={16} />} onClick={() => setEditing(true)}>Edit</Button>}
            {can('employees.delete') && <Button variant="subtle" color="red" leftSection={<IconTrash size={16} />} onClick={() => confirmAction({ title: 'Delete employee?', message: 'Prefer setting the status to Terminated to keep attendance and payroll history visible.', confirmLabel: 'Delete', danger: true, onConfirm: () => remove.mutate() })}>Delete</Button>}
          </>
        }
      />

      <Tabs value={tab} onChange={(v) => setParams({ tab: v ?? 'overview' }, { replace: true })} keepMounted={false}>
        <Tabs.List mb="lg" style={{ flexWrap: 'nowrap', overflowX: 'auto' }}>
          <Tabs.Tab value="overview">Overview</Tabs.Tab>
          <Tabs.Tab value="schedule">Work schedule</Tabs.Tab>
          <Tabs.Tab value="attendance">Attendance</Tabs.Tab>
          {can('payroll.view') && <Tabs.Tab value="payroll">Payroll</Tabs.Tab>}
          {can('penalties.view') && <Tabs.Tab value="penalties">Penalties</Tabs.Tab>}
          {can('devices.view') && <Tabs.Tab value="devices">Terminal IDs</Tabs.Tab>}
          <Tabs.Tab value="documents">Documents</Tabs.Tab>
          <Tabs.Tab value="notes">Notes</Tabs.Tab>
          {can('audit.view') && <Tabs.Tab value="history">Activity history</Tabs.Tab>}
        </Tabs.List>

        <Tabs.Panel value="overview">
          <Grid gap="lg">
            <Grid.Col span={{ base: 12, lg: 6 }}>
              <Card h="100%">
                <Text fw={650} mb="sm">Personal information</Text>
                <DetailList items={[
                  { label: 'Phone', value: employee.phone }, { label: 'Email', value: employee.email },
                  { label: 'Birth date', value: fmtDate(employee.birth_date) }, { label: 'Gender', value: humanize(employee.gender) },
                  { label: 'Address', value: employee.address }, { label: 'Login account', value: employee.user ? `${employee.user.name} (${employee.user.email})` : 'None' },
                ]} />
                <dl style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(200px, 1fr))', gap: 12, marginTop: 16, marginBottom: 0 }}>
                  <CustomFieldValues entity="employee" values={employee.custom_fields} />
                </dl>
              </Card>
            </Grid.Col>
            <Grid.Col span={{ base: 12, lg: 6 }}>
              <Card h="100%">
                <Text fw={650} mb="sm">Employment</Text>
                <DetailList items={[
                  { label: 'Department', value: employee.department?.name }, { label: 'Position', value: employee.position?.name },
                  { label: 'Manager', value: employee.manager?.full_name }, { label: 'Employment type', value: humanize(employee.employment_type) },
                  { label: 'Hired', value: fmtDate(employee.hire_date) }, { label: 'Terminated', value: fmtDate(employee.termination_date) },
                  { label: 'Work schedule', value: employee.work_schedule?.name ?? 'Default schedule' },
                  { label: 'Pay', value: employee.salary_amount !== null ? `${fmtMoney(employee.salary_amount)} ${employee.salary_type === 'monthly' ? 'per month' : employee.salary_type === 'daily' ? 'per day' : 'per hour'}` : 'Hidden' },
                ]} />
                {employee.notes && <Text size="sm" mt="md" style={{ whiteSpace: 'pre-wrap' }}>{employee.notes}</Text>}
              </Card>
            </Grid.Col>
          </Grid>
        </Tabs.Panel>

        <Tabs.Panel value="schedule"><ScheduleTab employee={employee} canWrite={canWrite} /></Tabs.Panel>
        <Tabs.Panel value="attendance"><EmployeeAttendance employeeId={id} /></Tabs.Panel>

        <Tabs.Panel value="payroll">
          <DataTable
            rows={payroll.data}
            loading={payroll.isLoading}
            columns={[
              { key: 'period', header: 'Period', render: (r) => r.period ? `${String(r.period.month).padStart(2, '0')}.${r.period.year}` : '—' },
              { key: 'worked', header: 'Worked', render: (r) => `${r.worked_days} d · ${fmtMinutes(r.worked_minutes)}` },
              { key: 'gross', header: 'Gross', align: 'right', render: (r) => <span className="tabular">{fmtMoney(r.gross_amount, r.currency)}</span> },
              { key: 'penalties', header: 'Penalties', align: 'right', render: (r) => <span className="tabular">{fmtMoney(r.penalty_amount, r.currency)}</span> },
              { key: 'net', header: 'Net', align: 'right', render: (r) => <Text size="sm" fw={650} className="tabular">{fmtMoney(r.net_amount, r.currency)}</Text> },
              { key: 'status', header: 'Status', render: (r) => <StatusBadge status={r.status} /> },
            ]}
            empty={{ title: 'No payroll yet' }}
            minWidth={620}
          />
        </Tabs.Panel>

        <Tabs.Panel value="penalties">
          <DataTable
            rows={penalties.data?.data}
            loading={penalties.isLoading}
            columns={[
              { key: 'date', header: 'Date', render: (p) => fmtDate(p.date) },
              { key: 'type', header: 'Type', render: (p) => humanize(p.type) },
              { key: 'reason', header: 'Reason', render: (p) => p.reason ?? '—' },
              { key: 'amount', header: 'Amount', align: 'right', render: (p) => <span className="tabular">{fmtMoney(p.amount)}</span> },
              { key: 'status', header: 'Status', render: (p) => <StatusBadge status={p.status} /> },
            ]}
            empty={{ title: 'No penalties' }}
            minWidth={560}
          />
        </Tabs.Panel>

        <Tabs.Panel value="devices"><DeviceIdsTab employee={employee} /></Tabs.Panel>
        <Tabs.Panel value="documents"><Card><DocumentsPanel type="employees" id={id} canWrite={canWrite} /></Card></Tabs.Panel>
        <Tabs.Panel value="notes"><NotesPanel type="employees" id={id} canWrite={canWrite} /></Tabs.Panel>
        <Tabs.Panel value="history">
          <DataTable
            rows={history.data?.data}
            loading={history.isLoading}
            columns={[
              { key: 'created_at', header: 'When', render: (l) => fmtDateTime(l.created_at) },
              { key: 'event', header: 'Change', render: (l) => humanize(l.event) },
              { key: 'user', header: 'By', render: (l) => l.user?.name ?? 'System' },
              { key: 'fields', header: 'Fields', render: (l) => <Text size="xs" c="dimmed">{Object.keys(l.new_values).join(', ') || '—'}</Text> },
            ]}
            empty={{ title: 'No changes recorded' }}
            minWidth={560}
          />
        </Tabs.Panel>
      </Tabs>

      <EmployeeForm opened={editing} onClose={() => setEditing(false)} employee={employee} onSaved={() => void refetch()} />
    </>
  )
}
