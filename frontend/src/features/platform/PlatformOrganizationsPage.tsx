import { useState } from 'react'
import { Button, Group, PasswordInput, Select, SimpleGrid, Stack, Text, TextInput, Title } from '@mantine/core'
import { IconPlus } from '@tabler/icons-react'
import { useQuery } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { fmtDate } from '@/lib/format'
import { PageHeader } from '@/components/PageHeader'
import { DataTable } from '@/components/DataTable'
import { SearchInput } from '@/components/SearchInput'
import { StatusBadge } from '@/components/StatusBadge'
import { StatCard } from '@/components/StatCard'
import { EntityDrawer } from '@/components/EntityDrawer'
import { confirmAction } from '@/components/ConfirmDialog'
import { useApiMutation } from '@/hooks/useApiMutation'
import { useListParams } from '@/hooks/useListParams'
import { useAuth } from '@/features/auth/useAuth'
import type { Organization } from '@/types/models'

const blank = { name: '', slug: '', domain: '', timezone: 'Asia/Tashkent', currency: 'UZS', status: 'active', owner: { name: '', email: '', password: '' } }

export default function PlatformOrganizationsPage() {
  const { can } = useAuth()
  const list = useListParams()
  const [creating, setCreating] = useState(false)
  const [draft, setDraft] = useState(blank)
  const stats = useQuery({ queryKey: ['platform', 'stats'], queryFn: () => api.get<Record<string, number>>('/platform/stats') })
  const { data, isLoading, error } = useQuery({ queryKey: ['platform', 'organizations', list.query], queryFn: () => api.list<Organization>('/platform/organizations', list.query) })
  const create = useApiMutation({
    mutationFn: () => api.post('/platform/organizations', { ...draft, slug: draft.slug || null, domain: draft.domain || null }),
    invalidate: [['platform']],
    successMessage: 'Organization created. Send the owner their sign-in details.',
    onSuccess: () => { setCreating(false); setDraft(blank) },
  })
  const setStatus = useApiMutation<Organization, { id: number; status: string }>({
    mutationFn: ({ id, status }) => api.put(`/platform/organizations/${id}`, { status }),
    invalidate: [['platform']],
    successMessage: 'Status updated.',
  })

  return (
    <>
      <PageHeader title="Organizations" description="Every tenant on crm.eversoft.uz. Tenant data is never visible here." actions={can('platform.organizations.manage') && <Button leftSection={<IconPlus size={16} />} onClick={() => setCreating(true)}>New organization</Button>} />
      <SimpleGrid cols={{ base: 2, md: 4 }} spacing="sm" mb="lg">
        <StatCard label="Organizations" value={stats.data?.organizations ?? '—'} />
        <StatCard label="Active" value={stats.data?.active_organizations ?? '—'} />
        <StatCard label="Users" value={stats.data?.users ?? '—'} />
        <StatCard label="Employees tracked" value={stats.data?.employees ?? '—'} />
      </SimpleGrid>
      <Group mb="md" gap="sm">
        <SearchInput value={list.params.search ?? ''} onChange={(search) => list.set({ search })} placeholder="Name or slug" />
        <Select placeholder="Any status" data={['active', 'trial', 'suspended']} value={list.params.status ?? null} onChange={(v) => list.set({ status: v })} clearable w={150} aria-label="Status" />
      </Group>
      <DataTable
        rows={data?.data}
        meta={data?.meta}
        loading={isLoading}
        error={error}
        {...list.tableProps}
        columns={[
          { key: 'name', header: 'Organization', render: (o) => (<div><Text size="sm" fw={600}>{o.name}</Text><Text size="xs" c="dimmed">{o.slug}.crm.eversoft.uz{o.domain ? ` · ${o.domain}` : ''}</Text></div>) },
          { key: 'users', header: 'Users', align: 'right', render: (o) => <span className="tabular">{o.users_count ?? 0}</span> },
          { key: 'tz', header: 'Timezone', visibleFrom: 'md', render: (o) => `${o.timezone} · ${o.currency}` },
          { key: 'created', header: 'Since', visibleFrom: 'md', render: (o) => fmtDate(o.created_at) },
          { key: 'status', header: 'Status', render: (o) => <StatusBadge status={o.status} /> },
          {
            key: 'actions', header: '', render: (o) => can('platform.organizations.manage') && (
              o.status === 'suspended'
                ? <Button size="compact-xs" variant="light" onClick={() => setStatus.mutate({ id: o.id, status: 'active' })}>Reactivate</Button>
                : <Button size="compact-xs" variant="subtle" color="red" onClick={() => confirmAction({ title: `Suspend ${o.name}?`, message: 'All users are signed out and cannot sign in until reactivated. Data is kept.', confirmLabel: 'Suspend', danger: true, onConfirm: () => setStatus.mutate({ id: o.id, status: 'suspended' }) })}>Suspend</Button>
            ),
          },
        ]}
        empty={{ title: 'No organizations' }}
      />
      <EntityDrawer opened={creating} onClose={() => setCreating(false)} title="New organization" formId="org-form" submitting={create.isPending}>
        <form id="org-form" onSubmit={(e) => { e.preventDefault(); create.mutate() }}>
          <Stack gap="sm">
            <TextInput label="Organization name" required value={draft.name} onChange={(e) => setDraft({ ...draft, name: e.currentTarget.value })} />
            <SimpleGrid cols={2} spacing="sm">
              <TextInput label="Subdomain" placeholder="auto from name" value={draft.slug} onChange={(e) => setDraft({ ...draft, slug: e.currentTarget.value.toLowerCase() })} rightSection={<Text size="xs" c="dimmed" pr={70}>.crm…</Text>} />
              <TextInput label="Custom domain" placeholder="optional" value={draft.domain} onChange={(e) => setDraft({ ...draft, domain: e.currentTarget.value })} />
              <Select label="Timezone" data={['Asia/Tashkent', 'Asia/Samarkand', 'Asia/Almaty', 'Europe/Moscow', 'UTC']} value={draft.timezone} onChange={(v) => v && setDraft({ ...draft, timezone: v })} />
              <Select label="Currency" data={['UZS', 'USD', 'EUR', 'RUB', 'KZT']} value={draft.currency} onChange={(v) => v && setDraft({ ...draft, currency: v })} />
              <Select label="Status" data={['active', 'trial']} value={draft.status} onChange={(v) => v && setDraft({ ...draft, status: v })} />
            </SimpleGrid>
            <Title order={4} mt="sm">Owner account</Title>
            <TextInput label="Full name" required value={draft.owner.name} onChange={(e) => setDraft({ ...draft, owner: { ...draft.owner, name: e.currentTarget.value } })} />
            <TextInput label="Email" type="email" required value={draft.owner.email} onChange={(e) => setDraft({ ...draft, owner: { ...draft.owner, email: e.currentTarget.value } })} />
            <PasswordInput label="Temporary password" required value={draft.owner.password} onChange={(e) => setDraft({ ...draft, owner: { ...draft.owner, password: e.currentTarget.value } })} description="The owner should change it after the first sign-in." />
          </Stack>
        </form>
      </EntityDrawer>
    </>
  )
}
