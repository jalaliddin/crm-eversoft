import { useState } from 'react'
import { Button, Group, SegmentedControl, Select, Text } from '@mantine/core'
import { IconLayoutKanban, IconList, IconPlus } from '@tabler/icons-react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from 'react-router'
import { api } from '@/lib/api'
import { fmtDate, fmtMoney } from '@/lib/format'
import { notifyError } from '@/lib/notify'
import { PageHeader } from '@/components/PageHeader'
import { DataTable, type Column } from '@/components/DataTable'
import { KanbanBoard } from '@/components/KanbanBoard'
import { SearchInput } from '@/components/SearchInput'
import { StatusBadge } from '@/components/StatusBadge'
import { statusColor } from '@/lib/statusColors'
import { LoadingState } from '@/components/LoadingState'
import { ErrorState } from '@/components/ErrorState'
import { useListParams } from '@/hooks/useListParams'
import { useOptions, useUsersLookup } from '@/hooks/useLookups'
import { Can } from '@/features/auth/guards'
import { useAuth } from '@/features/auth/useAuth'
import type { Lead, LeadStatus } from '@/types/models'
import { LeadForm } from './LeadForm'
import { LeadCard } from './LeadCard'
import { SOURCES } from '../shared/sources'

interface BoardColumn {
  status: LeadStatus
  label: string
  leads: Lead[]
  total_value: number
}

const columns: Column<Lead>[] = [
  { key: 'name', header: 'Lead', sortable: true, render: (l) => (<div><Text size="sm" fw={600}>{l.name}</Text><Text size="xs" c="dimmed">{l.company_name ?? l.phone ?? ''}</Text></div>) },
  { key: 'status', header: 'Status', sortable: true, render: (l) => <StatusBadge status={l.status} /> },
  { key: 'expected_value', header: 'Expected value', sortable: true, align: 'right', render: (l) => <span className="tabular">{fmtMoney(l.expected_value)}</span> },
  { key: 'source', header: 'Source', visibleFrom: 'lg', render: (l) => l.source ?? '—' },
  { key: 'assignee', header: 'Assigned to', visibleFrom: 'md', render: (l) => l.assignee?.name ?? '—' },
  { key: 'created_at', header: 'Created', sortable: true, visibleFrom: 'md', render: (l) => fmtDate(l.created_at) },
]

export default function LeadsPage() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { can } = useAuth()
  const list = useListParams({ view: 'board' })
  const { params } = list
  const [creating, setCreating] = useState(false)
  const statuses = useOptions('lead_statuses')
  const { data: users = [] } = useUsersLookup()
  const filters = { search: params.search, assigned_to: params.assigned_to, source: params.source }

  const board = useQuery({ queryKey: ['leads', 'board', filters], queryFn: () => api.get<BoardColumn[]>('/leads-board', filters), enabled: params.view === 'board' })
  const table = useQuery({ queryKey: ['leads', 'list', list.query], queryFn: () => api.list<Lead>('/leads', { ...list.query, view: undefined }), enabled: params.view === 'list', placeholderData: (p) => p })

  const move = async (lead: Lead, status: string) => {
    const key = ['leads', 'board', filters]
    const previous = queryClient.getQueryData<BoardColumn[]>(key)
    // Optimistic move; roll back if the server refuses.
    queryClient.setQueryData<BoardColumn[]>(key, (cols) =>
      cols?.map((c) => ({
        ...c,
        leads: c.status === status ? [{ ...lead, status: status as LeadStatus }, ...c.leads] : c.leads.filter((l) => l.id !== lead.id),
      })),
    )
    try {
      await api.post(`/leads/${lead.id}/move`, { status, position: 0 })
      void queryClient.invalidateQueries({ queryKey: ['leads'] })
    } catch (e) {
      queryClient.setQueryData(key, previous)
      notifyError(e)
    }
  }

  return (
    <>
      <PageHeader
        title="Leads"
        description="Potential customers moving towards a first deal."
        actions={<Can permission="leads.create"><Button leftSection={<IconPlus size={16} />} onClick={() => setCreating(true)}>New lead</Button></Can>}
      />
      <Group mb="md" gap="sm" wrap="wrap">
        <SegmentedControl
          value={params.view}
          onChange={(view) => list.set({ view })}
          data={[
            { value: 'board', label: <Group gap={6} wrap="nowrap"><IconLayoutKanban size={16} />Board</Group> },
            { value: 'list', label: <Group gap={6} wrap="nowrap"><IconList size={16} />List</Group> },
          ]}
          aria-label="View"
        />
        <SearchInput value={params.search ?? ''} onChange={(search) => list.set({ search })} placeholder="Search leads" />
        <Select placeholder="Anyone" data={[{ value: 'me', label: 'Assigned to me' }, ...users]} value={params.assigned_to ?? null} onChange={(v) => list.set({ assigned_to: v })} clearable w={190} aria-label="Assigned to" searchable />
        <Select placeholder="Any source" data={SOURCES} value={params.source ?? null} onChange={(v) => list.set({ source: v })} clearable w={160} aria-label="Source" />
        {params.view === 'list' && <Select placeholder="Any status" data={statuses} value={params.status ?? null} onChange={(v) => list.set({ status: v })} clearable w={160} aria-label="Status" />}
      </Group>

      {params.view === 'board' ? (
        board.isLoading ? <LoadingState /> : board.error ? <ErrorState error={board.error} onRetry={() => void board.refetch()} /> : (
          <KanbanBoard
            columns={(board.data ?? []).map((c) => ({
              id: c.status,
              title: c.label,
              color: statusColor(c.status),
              items: c.leads,
              footer: c.total_value > 0 ? <Text size="xs" c="dimmed" className="tabular">{fmtMoney(c.total_value)}</Text> : undefined,
            }))}
            itemId={(l) => l.id}
            canDrag={can('leads.update')}
            renderCard={(lead, dragging) => <LeadCard lead={lead} dragging={dragging} />}
            onMove={(lead, status) => {
              if (status === 'converted') navigate(`/crm/leads/${lead.id}?convert=1`)
              else void move(lead, status)
            }}
          />
        )
      ) : (
        <DataTable
          columns={columns}
          rows={table.data?.data}
          meta={table.data?.meta}
          loading={table.isLoading || table.isFetching}
          error={table.error}
          {...list.tableProps}
          onRowClick={(l) => navigate(`/crm/leads/${l.id}`)}
          empty={{ title: 'No leads', message: 'Capture enquiries from your website, calls and social channels here.' }}
        />
      )}

      <LeadForm opened={creating} onClose={() => setCreating(false)} onSaved={(l) => navigate(`/crm/leads/${l.id}`)} />
    </>
  )
}
