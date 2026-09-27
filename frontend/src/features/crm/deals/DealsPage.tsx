import { useState } from 'react'
import { Avatar, Button, Group, Modal, Paper, SegmentedControl, Select, Stack, Text, TextInput, Tooltip } from '@mantine/core'
import { IconLayoutKanban, IconList, IconPlus } from '@tabler/icons-react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from 'react-router'
import { api } from '@/lib/api'
import { fmtCompact, fmtDate, fmtMoney, initials } from '@/lib/format'
import { notifyError } from '@/lib/notify'
import { PageHeader } from '@/components/PageHeader'
import { DataTable, type Column } from '@/components/DataTable'
import { KanbanBoard } from '@/components/KanbanBoard'
import { SearchInput } from '@/components/SearchInput'
import { StatusBadge } from '@/components/StatusBadge'
import { LoadingState } from '@/components/LoadingState'
import { ErrorState } from '@/components/ErrorState'
import { useListParams } from '@/hooks/useListParams'
import { usePipelines, useUsersLookup } from '@/hooks/useLookups'
import { Can } from '@/features/auth/guards'
import { useAuth } from '@/features/auth/useAuth'
import type { Deal, Pipeline } from '@/types/models'
import { DealForm } from './DealForm'

interface Board {
  pipeline: Pipeline
  columns: { stage: { id: number; name: string; color: string; type: string; probability: number }; deals: Deal[]; count: number; total_value: number }[]
}

const listColumns: Column<Deal>[] = [
  { key: 'title', header: 'Deal', sortable: true, render: (d) => (<div><Text size="sm" fw={600}>{d.title}</Text><Text size="xs" c="dimmed">{d.customer?.display_name ?? '—'}</Text></div>) },
  { key: 'stage', header: 'Stage', render: (d) => <StatusBadge status={d.stage?.type === 'open' ? 'open' : d.status} label={d.stage?.name} /> },
  { key: 'value', header: 'Value', sortable: true, align: 'right', render: (d) => <span className="tabular">{fmtMoney(d.value, d.currency)}</span> },
  { key: 'probability', header: 'Probability', align: 'right', visibleFrom: 'lg', render: (d) => <span className="tabular">{d.probability}%</span> },
  { key: 'expected_close_date', header: 'Expected close', sortable: true, visibleFrom: 'md', render: (d) => fmtDate(d.expected_close_date) },
  { key: 'assignee', header: 'Owner', visibleFrom: 'md', render: (d) => d.assignee?.name ?? '—' },
]

function DealCard({ deal, dragging }: { deal: Deal; dragging: boolean }) {
  const navigate = useNavigate()
  return (
    <Paper p="sm" radius="md" withBorder shadow={dragging ? 'md' : undefined} style={{ borderColor: 'var(--app-border)' }} onClick={() => navigate(`/crm/deals/${deal.id}`)}>
      <Text size="sm" fw={600} lineClamp={2}>{deal.title}</Text>
      <Text size="xs" c="dimmed" truncate>{deal.customer?.display_name ?? 'No customer'}</Text>
      <Text size="sm" fw={650} className="tabular" mt={8} style={{ whiteSpace: 'nowrap' }}>{fmtMoney(deal.value, deal.currency)}</Text>
      <Group justify="space-between" mt={4} wrap="nowrap">
        <Text size="xs" c="dimmed">{deal.expected_close_date ? `Close ${fmtDate(deal.expected_close_date)}` : 'No close date'}</Text>
        {deal.assignee && <Tooltip label={deal.assignee.name}><Avatar size={22} radius="xl" color="khiva">{initials(deal.assignee.name)}</Avatar></Tooltip>}
      </Group>
    </Paper>
  )
}

export default function DealsPage() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { can } = useAuth()
  const list = useListParams({ view: 'board' })
  const { params } = list
  const { data: pipelines = [] } = usePipelines()
  const { data: users = [] } = useUsersLookup()
  const [creating, setCreating] = useState(false)
  const [lostPrompt, setLostPrompt] = useState<{ deal: Deal; stageId: number } | null>(null)
  const [lostReason, setLostReason] = useState('')
  const pipelineId = params.pipeline_id ?? String(pipelines.find((p) => p.is_default)?.id ?? pipelines[0]?.id ?? '')
  const filters = { search: params.search, assigned_to: params.assigned_to, pipeline_id: pipelineId || undefined }

  const board = useQuery({ queryKey: ['deals', 'board', filters], queryFn: () => api.get<Board>('/deals-board', filters), enabled: params.view === 'board' && !!pipelineId })
  const table = useQuery({ queryKey: ['deals', 'list', list.query], queryFn: () => api.list<Deal>('/deals', { ...list.query, view: undefined, pipeline_id: undefined }), enabled: params.view === 'list', placeholderData: (p) => p })

  const move = async (deal: Deal, stageId: number, reason?: string) => {
    const key = ['deals', 'board', filters]
    const previous = queryClient.getQueryData<Board>(key)
    queryClient.setQueryData<Board>(key, (b) => b && {
      ...b,
      columns: b.columns.map((c) => ({
        ...c,
        deals: c.stage.id === stageId ? [{ ...deal, pipeline_stage_id: stageId }, ...c.deals] : c.deals.filter((d) => d.id !== deal.id),
      })),
    })
    try {
      await api.post(`/deals/${deal.id}/move`, { pipeline_stage_id: stageId, position: 0, lost_reason: reason })
      void queryClient.invalidateQueries({ queryKey: ['deals'] })
      void queryClient.invalidateQueries({ queryKey: ['dashboard'] })
    } catch (e) {
      queryClient.setQueryData(key, previous)
      notifyError(e)
    }
  }

  return (
    <>
      <PageHeader
        title="Deals"
        description={board.data ? `${board.data.columns.filter((c) => c.stage.type === 'open').reduce((s, c) => s + c.count, 0)} open · ${fmtMoney(board.data.columns.filter((c) => c.stage.type === 'open').reduce((s, c) => s + c.total_value, 0))} in pipeline` : undefined}
        actions={<Can permission="deals.create"><Button leftSection={<IconPlus size={16} />} onClick={() => setCreating(true)}>New deal</Button></Can>}
      />
      <Group mb="md" gap="sm" wrap="wrap">
        <SegmentedControl
          value={params.view}
          onChange={(view) => list.set({ view })}
          data={[
            { value: 'board', label: <Group gap={6} wrap="nowrap"><IconLayoutKanban size={16} />Pipeline</Group> },
            { value: 'list', label: <Group gap={6} wrap="nowrap"><IconList size={16} />List</Group> },
          ]}
          aria-label="View"
        />
        {params.view === 'board' && pipelines.length > 1 && (
          <Select data={pipelines.map((p) => ({ value: String(p.id), label: p.name }))} value={pipelineId} onChange={(v) => list.set({ pipeline_id: v })} w={200} aria-label="Pipeline" />
        )}
        <SearchInput value={params.search ?? ''} onChange={(search) => list.set({ search })} placeholder="Search deals" />
        <Select placeholder="Any owner" data={[{ value: 'me', label: 'My deals' }, ...users]} value={params.assigned_to ?? null} onChange={(v) => list.set({ assigned_to: v })} clearable w={180} aria-label="Owner" searchable />
        {params.view === 'list' && (
          <Select placeholder="Any status" data={[{ value: 'open', label: 'Open' }, { value: 'won', label: 'Won' }, { value: 'lost', label: 'Lost' }]} value={params.status ?? null} onChange={(v) => list.set({ status: v })} clearable w={140} aria-label="Status" />
        )}
      </Group>

      {params.view === 'board' ? (
        board.isLoading ? <LoadingState /> : board.error ? <ErrorState error={board.error} onRetry={() => void board.refetch()} /> : (
          <KanbanBoard
            columns={(board.data?.columns ?? []).map((c) => ({
              id: String(c.stage.id),
              title: c.stage.name,
              color: c.stage.color,
              items: c.deals,
              footer: <Text size="xs" c="dimmed" className="tabular">{fmtCompact(c.total_value)} · {c.stage.probability}%</Text>,
            }))}
            itemId={(d) => d.id}
            canDrag={can('deals.update')}
            renderCard={(deal, dragging) => <DealCard deal={deal} dragging={dragging} />}
            onMove={(deal, stageId) => {
              const stage = board.data?.columns.find((c) => String(c.stage.id) === stageId)?.stage
              if (stage?.type === 'lost') {
                setLostReason('')
                setLostPrompt({ deal, stageId: Number(stageId) })
              } else void move(deal, Number(stageId))
            }}
          />
        )
      ) : (
        <DataTable
          columns={listColumns}
          rows={table.data?.data}
          meta={table.data?.meta}
          loading={table.isLoading || table.isFetching}
          error={table.error}
          {...list.tableProps}
          onRowClick={(d) => navigate(`/crm/deals/${d.id}`)}
          empty={{ title: 'No deals', message: 'Create a deal or convert a qualified lead.' }}
        />
      )}

      <Modal opened={!!lostPrompt} onClose={() => setLostPrompt(null)} title="Mark deal as lost">
        <Stack>
          <TextInput label="Reason" placeholder="e.g. chose a cheaper supplier" value={lostReason} onChange={(e) => setLostReason(e.currentTarget.value)} data-autofocus />
          <Group justify="flex-end">
            <Button variant="default" onClick={() => setLostPrompt(null)}>Cancel</Button>
            <Button color="red" onClick={() => { if (lostPrompt) void move(lostPrompt.deal, lostPrompt.stageId, lostReason); setLostPrompt(null) }}>Mark as lost</Button>
          </Group>
        </Stack>
      </Modal>

      <DealForm
        opened={creating || params.new === '1'}
        onClose={() => { setCreating(false); if (params.new) list.set({ new: null, customer_id: null }) }}
        defaults={{ customer_id: params.customer_id ? Number(params.customer_id) : undefined, pipeline_id: pipelineId ? Number(pipelineId) : undefined }}
        onSaved={(d) => navigate(`/crm/deals/${d.id}`)}
      />
    </>
  )
}
