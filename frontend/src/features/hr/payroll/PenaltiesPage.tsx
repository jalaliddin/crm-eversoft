import { useState } from 'react'
import { Button, Group, NumberInput, Select, SimpleGrid, Stack, Tabs, Text, TextInput } from '@mantine/core'
import { DateInput } from '@mantine/dates'
import { IconCheck, IconPlus } from '@tabler/icons-react'
import { useQuery } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { fmtDate, fmtMoney, humanize, today } from '@/lib/format'
import { PageHeader } from '@/components/PageHeader'
import { DataTable } from '@/components/DataTable'
import { StatusBadge } from '@/components/StatusBadge'
import { StatCard } from '@/components/StatCard'
import { EntityModal } from '@/components/EntityModal'
import { useListParams } from '@/hooks/useListParams'
import { useApiMutation } from '@/hooks/useApiMutation'
import { useEmployeesLookup, useOptions } from '@/hooks/useLookups'
import { useAuth } from '@/features/auth/useAuth'
import type { PageMeta, Paginated } from '@/types/api'
import type { Penalty } from '@/types/models'
import { PenaltyRules } from './PenaltyRules'

type Meta = PageMeta & { totals: Record<string, { count: number; amount: number }> }

export default function PenaltiesPage() {
  const { can } = useAuth()
  const list = useListParams({ status: 'pending' })
  const { params } = list
  const [tab, setTab] = useState<string | null>('penalties')
  const [selected, setSelected] = useState<(string | number)[]>([])
  const [creating, setCreating] = useState(false)
  const [manual, setManual] = useState({ employee_id: null as string | null, date: today() as string | null, amount: 0 as number | string, reason: '' })
  const types = useOptions('penalty_types')
  const statuses = useOptions('penalty_statuses')
  const { data: employees = [] } = useEmployeesLookup()

  const query = { ...list.query, status: params.status === 'all' ? undefined : params.status }
  const { data, isLoading, error } = useQuery({ queryKey: ['penalties', query], queryFn: () => api.list<Penalty, Meta>('/penalties', query) as Promise<Paginated<Penalty, Meta>>, placeholderData: (p) => p })
  const invalidate = [['penalties']]
  const act = useApiMutation<Penalty, { id: number; action: 'approve' | 'reject' | 'cancel' }>({ mutationFn: ({ id, action }) => api.post(`/penalties/${id}/${action}`), invalidate })
  const bulk = useApiMutation<{ approved: number }>({ mutationFn: () => api.post('/penalties/bulk-approve', { ids: selected }), invalidate, onSuccess: () => setSelected([]) })
  const create = useApiMutation({
    mutationFn: () => api.post('/penalties', { ...manual, employee_id: Number(manual.employee_id), amount: Number(manual.amount) }),
    invalidate,
    successMessage: 'Penalty added for approval.',
    onSuccess: () => { setCreating(false); setManual({ employee_id: null, date: today(), amount: 0, reason: '' }) },
  })
  const totals = data?.meta.totals ?? {}

  return (
    <>
      <PageHeader
        title="Penalties"
        description="Generated from attendance by your rules, reviewed by HR, deducted in payroll once approved."
        actions={can('penalties.manage') && tab === 'penalties' && <Button leftSection={<IconPlus size={16} />} onClick={() => setCreating(true)}>Manual penalty</Button>}
      />
      <Tabs value={tab} onChange={setTab}>
        <Tabs.List mb="lg">
          <Tabs.Tab value="penalties">Penalties</Tabs.Tab>
          <Tabs.Tab value="rules">Rules</Tabs.Tab>
        </Tabs.List>
        <Tabs.Panel value="penalties">
          <SimpleGrid cols={{ base: 2, md: 4 }} spacing="sm" mb="md">
            {(['pending', 'approved', 'rejected', 'cancelled'] as const).map((s) => (
              <StatCard key={s} label={humanize(s)} value={totals[s]?.count ?? 0} hint={fmtMoney(totals[s]?.amount ?? 0)} tone={s === 'pending' && totals[s]?.count ? 'saffron' : undefined} />
            ))}
          </SimpleGrid>
          <Group mb="md" gap="sm" justify="space-between" wrap="wrap">
            <Group gap="sm" wrap="wrap">
              <Select data={[{ value: 'all', label: 'All statuses' }, ...statuses]} value={params.status} onChange={(v) => list.set({ status: v })} w={160} aria-label="Status" />
              <Select placeholder="Any type" data={types} value={params.type ?? null} onChange={(v) => list.set({ type: v })} clearable w={150} aria-label="Type" />
              <Select placeholder="All employees" data={employees} value={params.employee_id ?? null} onChange={(v) => list.set({ employee_id: v })} clearable searchable w={240} aria-label="Employee" />
            </Group>
            {can('penalties.approve') && selected.length > 0 && (
              <Button leftSection={<IconCheck size={16} />} loading={bulk.isPending} onClick={() => bulk.mutate()}>Approve {selected.length} selected</Button>
            )}
          </Group>
          <DataTable
            rows={data?.data}
            meta={data?.meta}
            loading={isLoading}
            error={error}
            {...list.tableProps}
            selection={can('penalties.approve') ? { selected, onChange: setSelected, isSelectable: (p) => p.status === 'pending' } : undefined}
            columns={[
              { key: 'date', header: 'Date', render: (p) => <span className="tabular">{fmtDate(p.date)}</span> },
              { key: 'employee', header: 'Employee', render: (p) => <Text size="sm" fw={600}>{p.employee?.full_name}</Text> },
              { key: 'type', header: 'Type', render: (p) => <Text size="sm">{humanize(p.type)}{p.is_automatic ? '' : ' (manual)'}</Text> },
              { key: 'reason', header: 'Reason', visibleFrom: 'md', render: (p) => <Text size="sm" c="dimmed" lineClamp={1}>{p.reason}</Text> },
              { key: 'amount', header: 'Amount', align: 'right', render: (p) => <Text size="sm" fw={600} className="tabular">{fmtMoney(p.amount)}</Text> },
              { key: 'status', header: 'Status', render: (p) => <StatusBadge status={p.status} /> },
              {
                key: 'actions',
                header: '',
                render: (p) => (
                  <Group gap={4} justify="flex-end" wrap="nowrap">
                    {p.status === 'pending' && can('penalties.approve') && (
                      <>
                        <Button size="compact-xs" variant="light" color="teal" onClick={() => act.mutate({ id: p.id, action: 'approve' })}>Approve</Button>
                        <Button size="compact-xs" variant="subtle" color="red" onClick={() => act.mutate({ id: p.id, action: 'reject' })}>Reject</Button>
                      </>
                    )}
                    {p.status === 'approved' && can('penalties.manage') && (
                      <Button size="compact-xs" variant="subtle" color="gray" onClick={() => act.mutate({ id: p.id, action: 'cancel' })}>Cancel</Button>
                    )}
                  </Group>
                ),
              },
            ]}
            empty={{ title: params.status === 'pending' ? 'Nothing waiting for approval' : 'No penalties', message: 'Penalties appear here when attendance matches a rule.' }}
          />
        </Tabs.Panel>
        <Tabs.Panel value="rules"><PenaltyRules /></Tabs.Panel>
      </Tabs>

      <EntityModal opened={creating} onClose={() => setCreating(false)} title="Manual penalty" formId="manual-penalty" submitting={create.isPending}>
        <form id="manual-penalty" onSubmit={(e) => { e.preventDefault(); create.mutate() }}>
          <Stack gap="sm">
            <Select label="Employee" data={employees} value={manual.employee_id} onChange={(v) => setManual({ ...manual, employee_id: v })} searchable required />
            <SimpleGrid cols={2} spacing="sm">
              <DateInput label="Date" value={manual.date} onChange={(v) => setManual({ ...manual, date: v })} valueFormat="DD.MM.YYYY" required />
              <NumberInput label="Amount" value={manual.amount} onChange={(v) => setManual({ ...manual, amount: v })} min={0} thousandSeparator=" " required />
            </SimpleGrid>
            <TextInput label="Reason" value={manual.reason} onChange={(e) => setManual({ ...manual, reason: e.currentTarget.value })} required />
          </Stack>
        </form>
      </EntityModal>
    </>
  )
}
