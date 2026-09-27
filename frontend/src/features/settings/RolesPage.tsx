import { useState } from 'react'
import { Badge, Button, Card, Checkbox, Grid, Group, NavLink, ScrollArea, Stack, Text, TextInput, Title } from '@mantine/core'
import { IconLock, IconPlus } from '@tabler/icons-react'
import { useQuery } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { humanize } from '@/lib/format'
import { PageHeader } from '@/components/PageHeader'
import { LoadingState } from '@/components/LoadingState'
import { confirmAction } from '@/components/ConfirmDialog'
import { useApiMutation } from '@/hooks/useApiMutation'
import { useAuth } from '@/features/auth/useAuth'
import type { Role } from '@/types/models'

interface PermissionGroup {
  group: string
  permissions: { key: string; label: string }[]
}

function RoleEditor({ role, catalog, editable, onSaved, onDeleted }: {
  role: Role | null
  catalog: PermissionGroup[]
  editable: boolean
  onSaved: (role: Role) => void
  onDeleted: () => void
}) {
  const [name, setName] = useState(role?.name ?? '')
  const [description, setDescription] = useState(role?.description ?? '')
  const [granted, setGranted] = useState<string[]>(role?.permissions ?? [])
  const isNew = role === null
  const locked = !editable || role?.slug === 'owner'

  const save = useApiMutation<Role>({
    mutationFn: () => (isNew ? api.post('/roles', { name, description, permissions: granted }) : api.put(`/roles/${role.id}`, { name, description, permissions: granted })),
    invalidate: [['roles']],
    successMessage: 'Role saved. Changes apply on the users’ next request.',
    onSuccess: onSaved,
  })
  const remove = useApiMutation({ mutationFn: () => api.delete(`/roles/${role?.id}`), invalidate: [['roles']], successMessage: 'Role deleted.', onSuccess: onDeleted })
  const toggle = (key: string, on: boolean) => setGranted((g) => (on ? [...new Set([...g, key])] : g.filter((p) => p !== key)))

  return (
    <Card>
      <Stack gap="md">
        <Group align="flex-end" wrap="wrap">
          <TextInput label="Role name" value={name} onChange={(e) => setName(e.currentTarget.value)} disabled={locked} w={260} required />
          <TextInput label="Description" value={description} onChange={(e) => setDescription(e.currentTarget.value)} disabled={locked} flex={1} miw={220} />
        </Group>
        {role?.slug === 'owner' && <Text size="sm" c="dimmed"><IconLock size={14} /> The owner role always has every permission.</Text>}
        <ScrollArea.Autosize mah="calc(100vh - 360px)">
          <Stack gap="lg">
            {catalog.map((group) => {
              const keys = group.permissions.map((p) => p.key)
              const all = keys.every((k) => granted.includes(k))
              const some = keys.some((k) => granted.includes(k))
              return (
                <div key={group.group}>
                  <Group justify="space-between" mb={6}>
                    <Title order={4} fz="sm">{humanize(group.group)}</Title>
                    <Checkbox size="xs" label="All" checked={all} indeterminate={!all && some} disabled={locked}
                      onChange={(e) => setGranted((g) => (e.currentTarget.checked ? [...new Set([...g, ...keys])] : g.filter((p) => !keys.includes(p))))} />
                  </Group>
                  <Grid gap={6}>
                    {group.permissions.map((p) => (
                      <Grid.Col key={p.key} span={{ base: 12, sm: 6, lg: 4 }}>
                        <Checkbox label={p.label} description={p.key} checked={granted.includes(p.key)} disabled={locked} onChange={(e) => toggle(p.key, e.currentTarget.checked)} />
                      </Grid.Col>
                    ))}
                  </Grid>
                </div>
              )
            })}
          </Stack>
        </ScrollArea.Autosize>
        {!locked && (
          <Group justify="space-between">
            {role && !role.is_system ? (
              <Button variant="subtle" color="red" onClick={() => confirmAction({ title: `Delete ${role.name}?`, message: 'Only roles without users can be deleted.', confirmLabel: 'Delete role', danger: true, onConfirm: () => remove.mutate() })}>Delete role</Button>
            ) : <span />}
            <Button onClick={() => save.mutate()} loading={save.isPending} disabled={!name.trim()}>{isNew ? 'Create role' : 'Save role'}</Button>
          </Group>
        )}
      </Stack>
    </Card>
  )
}

export default function RolesPage() {
  const { can } = useAuth()
  const editable = can('roles.manage')
  const roles = useQuery({ queryKey: ['roles'], queryFn: () => api.get<Role[]>('/roles') })
  const catalog = useQuery({ queryKey: ['permissions'], queryFn: () => api.get<PermissionGroup[]>('/permissions'), staleTime: Infinity })
  const [selectedId, setSelectedId] = useState<number | 'new' | null>(null)

  if (roles.isLoading || catalog.isLoading) return <LoadingState />

  const isNew = selectedId === 'new'
  const selected = isNew ? null : roles.data?.find((r) => r.id === selectedId) ?? roles.data?.[0] ?? null

  return (
    <>
      <PageHeader title="Roles & permissions" description="Each user can hold several roles; permissions add up." actions={editable && <Button leftSection={<IconPlus size={16} />} onClick={() => setSelectedId('new')}>New role</Button>} />
      <Grid gap="lg">
        <Grid.Col span={{ base: 12, md: 4, lg: 3 }}>
          <Card p="xs">
            {roles.data?.map((r) => (
              <NavLink
                key={r.id}
                label={r.name}
                description={`${r.users_count ?? 0} users · ${r.permissions.length} permissions`}
                active={!isNew && selected?.id === r.id}
                onClick={() => setSelectedId(r.id)}
                rightSection={r.is_system ? <Badge size="xs" variant="default">System</Badge> : null}
                style={{ borderRadius: 8 }}
              />
            ))}
          </Card>
        </Grid.Col>
        <Grid.Col span={{ base: 12, md: 8, lg: 9 }}>
          <RoleEditor
            key={isNew ? 'new' : `${selected?.id}-${selected?.permissions.length}-${selected?.name}`}
            role={selected}
            catalog={catalog.data ?? []}
            editable={editable}
            onSaved={(role) => setSelectedId(role.id)}
            onDeleted={() => setSelectedId(null)}
          />
        </Grid.Col>
      </Grid>
    </>
  )
}
