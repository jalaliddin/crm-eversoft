import { useEffect, useState } from 'react'
import { Button, Group, Select, Text } from '@mantine/core'
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
import { useDepartments, toOptions } from '@/hooks/useLookups'
import { useListParams } from '@/hooks/useListParams'
import { useAuth } from '@/features/auth/useAuth'
import type { Position } from '@/types/models'

const schema = z.object({ name: z.string().trim().min(1, 'Required'), code: z.string().nullable(), description: z.string().nullable(), department_id: z.string().nullable(), status: z.string() })
type Values = z.infer<typeof schema>

export default function PositionsPage() {
  const { can } = useAuth()
  const list = useListParams()
  const [editing, setEditing] = useState<Position | null | undefined>(undefined)
  const { data, isLoading, error } = useQuery({ queryKey: ['positions', list.query], queryFn: () => api.list<Position>('/positions', list.query) })
  const { data: departments } = useDepartments()
  const form = useForm<Values>({ resolver: zodResolver(schema) })

  useEffect(() => {
    if (editing === undefined) return
    form.reset({ name: editing?.name ?? '', code: editing?.code ?? '', description: editing?.description ?? '', department_id: editing?.department_id ? String(editing.department_id) : null, status: editing?.status ?? 'active' })
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [editing])

  const save = useApiMutation<Position, Values>({
    mutationFn: (v) => {
      const payload = { ...nullify(v), department_id: v.department_id ? Number(v.department_id) : null }
      return editing ? api.put(`/positions/${editing.id}`, payload) : api.post('/positions', payload)
    },
    invalidate: [['positions']],
    successMessage: 'Position saved.',
    onSuccess: () => setEditing(undefined),
    silentError: true,
  })
  const remove = useApiMutation<null, number>({ mutationFn: (id) => api.delete(`/positions/${id}`), invalidate: [['positions']], successMessage: 'Position deleted.' })

  return (
    <>
      <PageHeader title="Positions" description="Job titles used on employee profiles." actions={can('positions.manage') && <Button leftSection={<IconPlus size={16} />} onClick={() => setEditing(null)}>New position</Button>} />
      <Group mb="md" gap="sm">
        <SearchInput value={list.params.search ?? ''} onChange={(search) => list.set({ search })} placeholder="Search positions" />
        <Select placeholder="Any department" data={toOptions(departments)} value={list.params.department_id ?? null} onChange={(v) => list.set({ department_id: v })} clearable w={220} aria-label="Department" />
      </Group>
      <DataTable
        rows={data?.data}
        meta={data?.meta}
        loading={isLoading}
        error={error}
        {...list.tableProps}
        onRowClick={can('positions.manage') ? (p) => setEditing(p) : undefined}
        columns={[
          { key: 'name', header: 'Position', render: (p) => <Text size="sm" fw={600}>{p.name}</Text> },
          { key: 'code', header: 'Code', render: (p) => p.code ?? '—' },
          { key: 'department', header: 'Department', render: (p) => p.department?.name ?? '—' },
          { key: 'employees_count', header: 'Employees', align: 'right', render: (p) => <span className="tabular">{p.employees_count ?? 0}</span> },
          { key: 'status', header: 'Status', render: (p) => <StatusBadge status={p.status} /> },
        ]}
        empty={{ title: 'No positions yet' }}
        minWidth={560}
      />
      <EntityModal opened={editing !== undefined} onClose={() => setEditing(undefined)} title={editing ? 'Edit position' : 'New position'} formId="position-form" submitting={save.isPending}>
        <form id="position-form" onSubmit={form.handleSubmit((v) => save.mutate(v, { onError: (e) => applyServerErrors(e, form.setError) }))}>
          <FormField control={form.control} name="name" label="Title" required autoFocus />
          <Group grow mt="sm" align="flex-start">
            <FormField control={form.control} name="code" label="Code" />
            <FormField control={form.control} name="status" label="Status" kind="select" data={[{ value: 'active', label: 'Active' }, { value: 'inactive', label: 'Inactive' }]} />
          </Group>
          <FormField control={form.control} name="department_id" label="Department" kind="select" clearable data={toOptions(departments)} />
          <FormField control={form.control} name="description" label="Description" kind="textarea" />
          {editing && (
            <Button variant="subtle" color="red" mt="md" onClick={() => confirmAction({ title: 'Delete position?', message: 'Only positions nobody holds can be deleted.', confirmLabel: 'Delete', danger: true, onConfirm: () => { remove.mutate(editing.id); setEditing(undefined) } })}>
              Delete position
            </Button>
          )}
        </form>
      </EntityModal>
    </>
  )
}
