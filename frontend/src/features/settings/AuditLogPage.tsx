import { useState } from 'react'
import { Code, Drawer, Group, Select, Stack, Text, TextInput } from '@mantine/core'
import { useQuery } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { fmtDateTime, humanize } from '@/lib/format'
import { PageHeader } from '@/components/PageHeader'
import { DataTable } from '@/components/DataTable'
import { useListParams } from '@/hooks/useListParams'
import { useUsersLookup } from '@/hooks/useLookups'
import type { AuditLog } from '@/types/models'

const EVENT_GROUPS = [
  { value: 'auth', label: 'Sign-ins' }, { value: 'created', label: 'Created' }, { value: 'updated', label: 'Updated' }, { value: 'deleted', label: 'Deleted' },
  { value: 'attendance', label: 'Attendance corrections' }, { value: 'payroll', label: 'Payroll' }, { value: 'penalty', label: 'Penalties' },
  { value: 'role', label: 'Role & permission changes' }, { value: 'user', label: 'Users' }, { value: 'device', label: 'Devices' }, { value: 'settings', label: 'Settings' },
  { value: 'report', label: 'Exports' },
]

export default function AuditLogPage() {
  const list = useListParams({ per_page: '50' })
  const [open, setOpen] = useState<AuditLog | null>(null)
  const { data: users = [] } = useUsersLookup()
  const { data, isLoading, error } = useQuery({ queryKey: ['audit', list.query], queryFn: () => api.list<AuditLog>('/audit-logs', list.query), placeholderData: (p) => p })

  return (
    <>
      <PageHeader title="Audit log" description="Who changed what, and when. Passwords, tokens and device secrets are never recorded." />
      <Group mb="md" gap="sm" wrap="wrap">
        <Select placeholder="Any event" data={EVENT_GROUPS} value={list.params.event ?? null} onChange={(v) => list.set({ event: v })} clearable w={220} aria-label="Event" />
        <Select placeholder="Anyone" data={users} value={list.params.user_id ?? null} onChange={(v) => list.set({ user_id: v })} clearable searchable w={200} aria-label="User" />
        <TextInput placeholder="Record type (e.g. customer)" value={list.params.auditable_type ?? ''} onChange={(e) => list.set({ auditable_type: e.currentTarget.value })} w={220} aria-label="Record type" />
      </Group>
      <DataTable
        rows={data?.data}
        meta={data?.meta}
        loading={isLoading}
        error={error}
        {...list.tableProps}
        onRowClick={setOpen}
        columns={[
          { key: 'created_at', header: 'When', render: (l) => <span className="tabular">{fmtDateTime(l.created_at)}</span> },
          { key: 'user', header: 'Who', render: (l) => l.user?.name ?? 'System' },
          { key: 'event', header: 'What', render: (l) => humanize(l.event.replace('.', ' ')) },
          { key: 'record', header: 'Record', render: (l) => (l.auditable_type ? `${humanize(l.auditable_type)} #${l.auditable_id}` : '—') },
          { key: 'ip', header: 'IP', visibleFrom: 'lg', render: (l) => l.ip_address ?? '—' },
        ]}
        empty={{ title: 'No audit entries' }}
      />
      <Drawer opened={!!open} onClose={() => setOpen(null)} title="Audit entry" size="lg">
        {open && (
          <Stack gap="sm">
            <Text size="sm"><strong>{humanize(open.event)}</strong> by {open.user?.name ?? 'System'} · {fmtDateTime(open.created_at)}</Text>
            <Text size="xs" c="dimmed">{open.ip_address} · {open.user_agent}</Text>
            <Text size="sm" fw={600}>Before</Text>
            <Code block>{JSON.stringify(open.old_values, null, 2)}</Code>
            <Text size="sm" fw={600}>After</Text>
            <Code block>{JSON.stringify(open.new_values, null, 2)}</Code>
          </Stack>
        )}
      </Drawer>
    </>
  )
}
