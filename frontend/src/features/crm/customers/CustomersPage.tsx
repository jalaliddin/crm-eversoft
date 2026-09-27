import { useState } from 'react'
import { Badge, Button, Group, Menu, Select, Text } from '@mantine/core'
import { IconBuilding, IconDownload, IconPlus, IconUser } from '@tabler/icons-react'
import { useQuery } from '@tanstack/react-query'
import { useNavigate } from 'react-router'
import { api } from '@/lib/api'
import { fmtDate } from '@/lib/format'
import { notifyError } from '@/lib/notify'
import { PageHeader } from '@/components/PageHeader'
import { DataTable, type Column } from '@/components/DataTable'
import { SearchInput } from '@/components/SearchInput'
import { FilterDrawer } from '@/components/FilterDrawer'
import { StatusBadge } from '@/components/StatusBadge'
import { useListParams } from '@/hooks/useListParams'
import { useOptions, useTags, useUsersLookup } from '@/hooks/useLookups'
import { Can } from '@/features/auth/guards'
import type { Customer } from '@/types/models'
import { CustomerForm } from './CustomerForm'
import { SOURCES } from '../shared/sources'

const columns: Column<Customer>[] = [
  {
    key: 'last_name',
    header: 'Customer',
    sortable: true,
    render: (c) => (
      <Group gap="sm" wrap="nowrap">
        {c.type === 'company' ? <IconBuilding size={18} stroke={1.5} opacity={0.6} /> : <IconUser size={18} stroke={1.5} opacity={0.6} />}
        <div style={{ minWidth: 0 }}>
          <Text size="sm" fw={600} truncate>{c.display_name}</Text>
          <Text size="xs" c="dimmed" truncate>{c.email ?? c.phone ?? '—'}</Text>
        </div>
      </Group>
    ),
  },
  { key: 'phone', header: 'Phone', render: (c) => <span className="tabular">{c.phone ?? '—'}</span>, visibleFrom: 'md' },
  { key: 'city', header: 'City', sortable: true, visibleFrom: 'lg', render: (c) => c.city ?? '—' },
  {
    key: 'tags',
    header: 'Tags',
    visibleFrom: 'lg',
    render: (c) => (
      <Group gap={4}>
        {c.tags?.slice(0, 3).map((t) => <Badge key={t.id} color={t.color} size="sm">{t.name}</Badge>)}
      </Group>
    ),
  },
  { key: 'deals', header: 'Deals', align: 'right', visibleFrom: 'md', render: (c) => <span className="tabular">{c.deals_count ?? 0}</span> },
  { key: 'owner', header: 'Owner', visibleFrom: 'xl', render: (c) => c.owner?.name ?? '—' },
  { key: 'status', header: 'Status', sortable: true, render: (c) => <StatusBadge status={c.status} /> },
  { key: 'created_at', header: 'Added', sortable: true, visibleFrom: 'lg', render: (c) => fmtDate(c.created_at) },
]

const FILTERS = ['status', 'type', 'source', 'owner_id', 'tag_id', 'city']

export default function CustomersPage() {
  const navigate = useNavigate()
  const list = useListParams()
  const { params } = list
  const [creating, setCreating] = useState(false)
  const statuses = useOptions('customer_statuses')
  const types = useOptions('customer_types')
  const { data: users = [] } = useUsersLookup()
  const { data: tags = [] } = useTags()

  const { data, isLoading, isFetching, error, refetch } = useQuery({
    queryKey: ['customers', list.query],
    queryFn: () => api.list<Customer>('/customers', list.query),
    placeholderData: (prev) => prev,
  })

  const exportAs = (format: string) => void api.download('/customers', { ...list.query, export: format }, `customers.${format}`).catch(notifyError)

  return (
    <>
      <PageHeader
        title="Customers"
        description={data ? `${data.meta.total} customers` : undefined}
        actions={
          <>
            <Can permission="customers.export">
              <Menu position="bottom-end">
                <Menu.Target>
                  <Button variant="default" leftSection={<IconDownload size={16} />}>Export</Button>
                </Menu.Target>
                <Menu.Dropdown>
                  <Menu.Item onClick={() => exportAs('xlsx')}>Excel (.xlsx)</Menu.Item>
                  <Menu.Item onClick={() => exportAs('csv')}>CSV</Menu.Item>
                  <Menu.Item onClick={() => exportAs('pdf')}>PDF</Menu.Item>
                </Menu.Dropdown>
              </Menu>
            </Can>
            <Can permission="customers.create">
              <Button leftSection={<IconPlus size={16} />} onClick={() => setCreating(true)}>New customer</Button>
            </Can>
          </>
        }
      />

      <Group mb="md" gap="sm" wrap="wrap">
        <SearchInput value={params.search ?? ''} onChange={(search) => list.set({ search })} placeholder="Name, company, email or phone" w={{ base: '100%', sm: 320 }} />
        <Select placeholder="Any status" data={statuses} value={params.status ?? null} onChange={(v) => list.set({ status: v })} clearable w={150} aria-label="Status" />
        <FilterDrawer activeCount={list.activeCount(FILTERS.filter((f) => f !== 'status'))} onReset={() => list.reset(FILTERS)}>
          <Select label="Type" data={types} value={params.type ?? null} onChange={(v) => list.set({ type: v })} clearable />
          <Select label="Source" data={SOURCES} value={params.source ?? null} onChange={(v) => list.set({ source: v })} clearable />
          <Select label="Owner" data={users} value={params.owner_id ?? null} onChange={(v) => list.set({ owner_id: v })} clearable searchable />
          <Select label="Tag" data={tags.map((t) => ({ value: String(t.id), label: t.name }))} value={params.tag_id ?? null} onChange={(v) => list.set({ tag_id: v })} clearable />
        </FilterDrawer>
      </Group>

      <DataTable
        columns={columns}
        rows={data?.data}
        meta={data?.meta}
        loading={isLoading || isFetching}
        error={error}
        onRetry={() => void refetch()}
        onRowClick={(c) => navigate(`/crm/customers/${c.id}`)}
        {...list.tableProps}
        empty={{
          title: params.search ? 'No customers match' : 'No customers yet',
          message: params.search ? 'Try another name, phone or email.' : 'Add your first customer, or convert a qualified lead.',
          action: <Can permission="customers.create"><Button variant="light" onClick={() => setCreating(true)}>Add customer</Button></Can>,
        }}
      />

      <CustomerForm opened={creating} onClose={() => setCreating(false)} onSaved={(c) => navigate(`/crm/customers/${c.id}`)} />
    </>
  )
}
