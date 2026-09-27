import { useEffect, useState } from 'react'
import { Avatar, Badge, Button, Group, Select, Text } from '@mantine/core'
import { IconPlus } from '@tabler/icons-react'
import { useForm } from 'react-hook-form'
import { useQuery } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { applyServerErrors } from '@/lib/forms'
import { fromNow, initials } from '@/lib/format'
import { PageHeader } from '@/components/PageHeader'
import { DataTable } from '@/components/DataTable'
import { SearchInput } from '@/components/SearchInput'
import { StatusBadge } from '@/components/StatusBadge'
import { EntityDrawer } from '@/components/EntityDrawer'
import { FormField } from '@/components/FormField'
import { confirmAction } from '@/components/ConfirmDialog'
import { useApiMutation } from '@/hooks/useApiMutation'
import { useListParams } from '@/hooks/useListParams'
import { useAuth } from '@/features/auth/useAuth'
import type { Role, User } from '@/types/models'

interface Values {
  name: string
  email: string
  phone: string
  password: string
  locale: string | null
  status: string
  role_ids: string[]
}

export default function UsersPage() {
  const { can, me } = useAuth()
  const list = useListParams()
  const [editing, setEditing] = useState<User | null | undefined>(undefined)
  const { data, isLoading, error } = useQuery({ queryKey: ['users', list.query], queryFn: () => api.list<User>('/users', list.query) })
  const roles = useQuery({ queryKey: ['roles'], queryFn: () => api.get<Role[]>('/roles') })
  const form = useForm<Values>()

  useEffect(() => {
    if (editing === undefined) return
    form.reset({
      name: editing?.name ?? '', email: editing?.email ?? '', phone: editing?.phone ?? '', password: '', locale: editing?.locale ?? null,
      status: editing?.status ?? 'active', role_ids: editing?.roles?.map((r) => String(r.id)) ?? [],
    })
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [editing])

  const save = useApiMutation<User, Values>({
    mutationFn: (v) => {
      const payload = { ...v, role_ids: v.role_ids.map(Number), password: v.password || undefined }
      return editing ? api.put(`/users/${editing.id}`, payload) : api.post('/users', payload)
    },
    invalidate: [['users'], ['meta', 'users']],
    successMessage: editing ? 'User saved.' : 'User invited. Share their email and password with them.',
    onSuccess: () => setEditing(undefined),
    silentError: true,
  })
  const remove = useApiMutation<null, number>({ mutationFn: (id) => api.delete(`/users/${id}`), invalidate: [['users']], successMessage: 'User deleted.', onSuccess: () => setEditing(undefined) })
  const roleOptions = (roles.data ?? []).map((r) => ({ value: String(r.id), label: r.name }))

  return (
    <>
      <PageHeader title="Users" description="People who can sign in to your organization." actions={can('users.manage') && <Button leftSection={<IconPlus size={16} />} onClick={() => setEditing(null)}>New user</Button>} />
      <Group mb="md" gap="sm">
        <SearchInput value={list.params.search ?? ''} onChange={(search) => list.set({ search })} placeholder="Name or email" />
        <Select placeholder="Any role" data={roleOptions} value={list.params.role_id ?? null} onChange={(v) => list.set({ role_id: v })} clearable w={200} aria-label="Role" />
      </Group>
      <DataTable
        rows={data?.data}
        meta={data?.meta}
        loading={isLoading}
        error={error}
        {...list.tableProps}
        onRowClick={can('users.manage') ? (u) => setEditing(u) : undefined}
        columns={[
          { key: 'name', header: 'User', sortable: true, render: (u) => (
            <Group gap="sm" wrap="nowrap">
              <Avatar src={u.avatar_url} radius="xl" size={32} color="khiva">{initials(u.name)}</Avatar>
              <div><Text size="sm" fw={600}>{u.name}{u.id === me?.user.id ? ' (you)' : ''}</Text><Text size="xs" c="dimmed">{u.email}</Text></div>
            </Group>
          ) },
          { key: 'roles', header: 'Roles', render: (u) => <Group gap={4}>{u.roles?.map((r) => <Badge key={r.id} variant="light">{r.name}</Badge>)}</Group> },
          { key: 'last_login_at', header: 'Last sign-in', sortable: true, visibleFrom: 'md', render: (u) => fromNow(u.last_login_at) },
          { key: 'status', header: 'Status', render: (u) => <StatusBadge status={u.status} /> },
        ]}
        empty={{ title: 'No users' }}
        minWidth={620}
      />
      <EntityDrawer opened={editing !== undefined} onClose={() => setEditing(undefined)} title={editing ? `Edit ${editing.name}` : 'New user'} formId="user-form" submitting={save.isPending}>
        <form id="user-form" onSubmit={form.handleSubmit((v) => save.mutate(v, { onError: (e) => applyServerErrors(e, form.setError) }))}>
          <FormField control={form.control} name="name" label="Full name" required autoFocus />
          <FormField control={form.control} name="email" label="Email" kind="email" required />
          <FormField control={form.control} name="phone" label="Phone" />
          <FormField control={form.control} name="password" label={editing ? 'New password' : 'Password'} kind="password" required={!editing} description={editing ? 'Leave empty to keep the current password.' : 'At least 8 characters.'} />
          <FormField control={form.control} name="role_ids" label="Roles" kind="multiselect" data={roleOptions} />
          <Group grow>
            <FormField control={form.control} name="status" label="Status" kind="select" data={[{ value: 'active', label: 'Active' }, { value: 'inactive', label: 'Disabled' }]} />
            <FormField control={form.control} name="locale" label="Language" kind="select" clearable data={[{ value: 'en', label: 'English' }, { value: 'ru', label: 'Русский' }, { value: 'uz', label: "O'zbekcha" }]} />
          </Group>
          {editing && editing.id !== me?.user.id && (
            <Button variant="subtle" color="red" mt="lg" onClick={() => confirmAction({ title: `Delete ${editing.name}?`, message: 'They lose access immediately. Their records stay.', confirmLabel: 'Delete user', danger: true, onConfirm: () => remove.mutate(editing.id) })}>
              Delete user
            </Button>
          )}
        </form>
      </EntityDrawer>
    </>
  )
}
