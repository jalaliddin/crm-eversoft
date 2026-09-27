import { useState } from 'react'
import { Avatar, Button, Group, Menu, Select, Text } from '@mantine/core'
import { IconDownload, IconPlus } from '@tabler/icons-react'
import { useQuery } from '@tanstack/react-query'
import { useNavigate } from 'react-router'
import { api } from '@/lib/api'
import { fmtDate, initials } from '@/lib/format'
import { notifyError } from '@/lib/notify'
import { PageHeader } from '@/components/PageHeader'
import { DataTable, type Column } from '@/components/DataTable'
import { SearchInput } from '@/components/SearchInput'
import { StatusBadge } from '@/components/StatusBadge'
import { FilterDrawer } from '@/components/FilterDrawer'
import { useListParams } from '@/hooks/useListParams'
import { useDepartments, useOptions, usePositions, toOptions } from '@/hooks/useLookups'
import { Can } from '@/features/auth/guards'
import type { Employee } from '@/types/models'
import { EmployeeForm } from './EmployeeForm'

const columns: Column<Employee>[] = [
  {
    key: 'last_name',
    header: 'Employee',
    sortable: true,
    render: (e) => (
      <Group gap="sm" wrap="nowrap">
        <Avatar src={e.photo_url} radius="xl" size={34} color="khiva">{initials(`${e.first_name} ${e.last_name}`)}</Avatar>
        <div style={{ minWidth: 0 }}>
          <Text size="sm" fw={600} truncate>{e.full_name}</Text>
          <Text size="xs" c="dimmed" truncate>{e.position?.name ?? '—'}</Text>
        </div>
      </Group>
    ),
  },
  { key: 'employee_number', header: 'No.', sortable: true, render: (e) => <span className="tabular">{e.employee_number}</span> },
  { key: 'department', header: 'Department', visibleFrom: 'sm', render: (e) => e.department?.name ?? '—' },
  { key: 'schedule', header: 'Schedule', visibleFrom: 'lg', render: (e) => e.work_schedule?.name ?? 'Default' },
  { key: 'phone', header: 'Phone', visibleFrom: 'xl', render: (e) => <span className="tabular">{e.phone ?? '—'}</span> },
  { key: 'hire_date', header: 'Hired', sortable: true, visibleFrom: 'md', render: (e) => fmtDate(e.hire_date) },
  { key: 'status', header: 'Status', sortable: true, render: (e) => <StatusBadge status={e.status} /> },
]

export default function EmployeesPage() {
  const navigate = useNavigate()
  const list = useListParams({ status: 'active' })
  const { params } = list
  const [creating, setCreating] = useState(false)
  const { data: departments } = useDepartments()
  const { data: positions } = usePositions()
  const statuses = useOptions('employee_statuses')
  const employmentTypes = useOptions('employment_types')
  const salaryTypes = useOptions('salary_types')

  const query = { ...list.query, status: params.status === 'all' ? undefined : params.status }
  const { data, isLoading, isFetching, error, refetch } = useQuery({ queryKey: ['employees', query], queryFn: () => api.list<Employee>('/employees', query), placeholderData: (p) => p })
  const exportAs = (format: string) => void api.download('/employees', { ...query, export: format }, `employees.${format}`).catch(notifyError)

  return (
    <>
      <PageHeader
        title="Employees"
        description={data ? `${data.meta.total} people` : undefined}
        actions={
          <>
            <Menu position="bottom-end">
              <Menu.Target><Button variant="default" leftSection={<IconDownload size={16} />}>Export</Button></Menu.Target>
              <Menu.Dropdown>
                <Menu.Item onClick={() => exportAs('xlsx')}>Excel (.xlsx)</Menu.Item>
                <Menu.Item onClick={() => exportAs('csv')}>CSV</Menu.Item>
                <Menu.Item onClick={() => exportAs('pdf')}>PDF</Menu.Item>
              </Menu.Dropdown>
            </Menu>
            <Can permission="employees.create"><Button leftSection={<IconPlus size={16} />} onClick={() => setCreating(true)}>New employee</Button></Can>
          </>
        }
      />
      <Group mb="md" gap="sm" wrap="wrap">
        <SearchInput value={params.search ?? ''} onChange={(search) => list.set({ search })} placeholder="Name, number, phone" />
        <Select data={toOptions(departments)} placeholder="All departments" value={params.department_id ?? null} onChange={(v) => list.set({ department_id: v })} clearable w={200} aria-label="Department" />
        <Select data={[{ value: 'all', label: 'All statuses' }, ...statuses]} value={params.status} onChange={(v) => list.set({ status: v })} w={160} aria-label="Status" />
        <FilterDrawer activeCount={list.activeCount(['position_id', 'employment_type', 'salary_type'])} onReset={() => list.reset(['position_id', 'employment_type', 'salary_type'])}>
          <Select label="Position" data={toOptions(positions)} value={params.position_id ?? null} onChange={(v) => list.set({ position_id: v })} clearable />
          <Select label="Employment type" data={employmentTypes} value={params.employment_type ?? null} onChange={(v) => list.set({ employment_type: v })} clearable />
          <Select label="Paid" data={salaryTypes} value={params.salary_type ?? null} onChange={(v) => list.set({ salary_type: v })} clearable />
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
        onRowClick={(e) => navigate(`/hr/employees/${e.id}`)}
        empty={{ title: 'No employees found', message: 'Add employees, then map their terminal IDs so attendance can be matched.' }}
      />
      <EmployeeForm opened={creating} onClose={() => setCreating(false)} onSaved={(e) => navigate(`/hr/employees/${e.id}`)} />
    </>
  )
}
