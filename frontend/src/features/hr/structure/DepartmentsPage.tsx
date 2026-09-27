import { useEffect, useState } from 'react'
import { Button, Group, Text } from '@mantine/core'
import { IconPlus } from '@tabler/icons-react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useQuery } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { applyServerErrors, nullify } from '@/lib/forms'
import { PageHeader } from '@/components/PageHeader'
import { DataTable } from '@/components/DataTable'
import { SearchInput } from '@/components/SearchInput'
import { StatusBadge } from '@/components/StatusBadge'
import { EntityModal } from '@/components/EntityModal'
import { FormField } from '@/components/FormField'
import { confirmAction } from '@/components/ConfirmDialog'
import { useApiMutation } from '@/hooks/useApiMutation'
import { useEmployeesLookup, toOptions } from '@/hooks/useLookups'
import { useListParams } from '@/hooks/useListParams'
import { useAuth } from '@/features/auth/useAuth'
import type { Department } from '@/types/models'

const schema = z.object({
  name: z.string().trim().min(1, 'Required'),
  code: z.string().nullable(),
  description: z.string().nullable(),
  parent_id: z.string().nullable(),
  manager_id: z.string().nullable(),
  status: z.string(),
})
type Values = z.infer<typeof schema>

export default function DepartmentsPage() {
  const { can } = useAuth()
  const list = useListParams()
  const [editing, setEditing] = useState<Department | null | undefined>(undefined)
  const { data, isLoading, error, refetch } = useQuery({ queryKey: ['departments', list.query], queryFn: () => api.list<Department>('/departments', list.query) })
  const all = useQuery({ queryKey: ['departments', 'all'], queryFn: () => api.get<Department[]>('/departments', { all: 1 }) })
  const { data: employees = [] } = useEmployeesLookup()
  const form = useForm<Values>({ resolver: zodResolver(schema) })

  useEffect(() => {
    if (editing === undefined) return
    form.reset({
      name: editing?.name ?? '', code: editing?.code ?? '', description: editing?.description ?? '',
      parent_id: editing?.parent_id ? String(editing.parent_id) : null, manager_id: editing?.manager_id ? String(editing.manager_id) : null,
      status: editing?.status ?? 'active',
    })
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [editing])

  const save = useApiMutation<Department, Values>({
    mutationFn: (v) => {
      const payload = { ...nullify(v), parent_id: v.parent_id ? Number(v.parent_id) : null, manager_id: v.manager_id ? Number(v.manager_id) : null }
      return editing ? api.put(`/departments/${editing.id}`, payload) : api.post('/departments', payload)
    },
    invalidate: [['departments']],
    successMessage: 'Department saved.',
    onSuccess: () => setEditing(undefined),
    silentError: true,
  })
  const remove = useApiMutation<null, number>({ mutationFn: (id) => api.delete(`/departments/${id}`), invalidate: [['departments']], successMessage: 'Department deleted.' })

  return (
    <>
      <PageHeader
        title="Departments"
        description="Your organization structure. Departments can be nested."
        actions={can('departments.manage') && <Button leftSection={<IconPlus size={16} />} onClick={() => setEditing(null)}>New department</Button>}
      />
      <Group mb="md"><SearchInput value={list.params.search ?? ''} onChange={(search) => list.set({ search })} placeholder="Search departments" /></Group>
      <DataTable
        rows={data?.data}
        meta={data?.meta}
        loading={isLoading}
        error={error}
        onRetry={() => void refetch()}
        {...list.tableProps}
        onRowClick={can('departments.manage') ? (d) => setEditing(d) : undefined}
        columns={[
          { key: 'name', header: 'Department', render: (d) => (<div><Text size="sm" fw={600}>{d.name}</Text>{d.parent && <Text size="xs" c="dimmed">in {d.parent.name}</Text>}</div>) },
          { key: 'code', header: 'Code', render: (d) => d.code ?? '—' },
          { key: 'manager', header: 'Head', visibleFrom: 'sm', render: (d) => d.manager?.full_name ?? '—' },
          { key: 'employees_count', header: 'Employees', align: 'right', render: (d) => <span className="tabular">{d.employees_count ?? 0}</span> },
          { key: 'status', header: 'Status', render: (d) => <StatusBadge status={d.status} /> },
        ]}
        empty={{ title: 'No departments yet', message: 'Add departments such as Sales, Accounting or Warehouse.' }}
        minWidth={560}
      />
      <EntityModal opened={editing !== undefined} onClose={() => setEditing(undefined)} title={editing ? 'Edit department' : 'New department'} formId="department-form" submitting={save.isPending}>
        <form id="department-form" onSubmit={form.handleSubmit((v) => save.mutate(v, { onError: (e) => applyServerErrors(e, form.setError) }))}>
          <FormField control={form.control} name="name" label="Name" required autoFocus />
          <Group grow mt="sm" align="flex-start">
            <FormField control={form.control} name="code" label="Code" />
            <FormField control={form.control} name="status" label="Status" kind="select" data={[{ value: 'active', label: 'Active' }, { value: 'inactive', label: 'Inactive' }]} />
          </Group>
          <FormField control={form.control} name="parent_id" label="Parent department" kind="select" clearable searchable data={toOptions(all.data?.filter((d) => d.id !== editing?.id))} />
          <FormField control={form.control} name="manager_id" label="Head of department" kind="select" clearable searchable data={employees} />
          <FormField control={form.control} name="description" label="Description" kind="textarea" />
          {editing && can('departments.manage') && (
            <Button variant="subtle" color="red" mt="md" onClick={() => confirmAction({ title: 'Delete department?', message: 'Only departments without employees can be deleted.', confirmLabel: 'Delete', danger: true, onConfirm: () => { remove.mutate(editing.id); setEditing(undefined) } })}>
              Delete department
            </Button>
          )}
        </form>
      </EntityModal>
    </>
  )
}
