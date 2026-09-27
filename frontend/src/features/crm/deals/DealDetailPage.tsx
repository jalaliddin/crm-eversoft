import { useState } from 'react'
import { Anchor, Badge, Button, Card, Grid, Group, Modal, Stack, Tabs, Text, TextInput, UnstyledButton } from '@mantine/core'
import { IconCheck, IconPencil, IconTrash, IconX } from '@tabler/icons-react'
import { useQuery } from '@tanstack/react-query'
import { Link, useNavigate, useParams } from 'react-router'
import { api } from '@/lib/api'
import { fmtDate, fmtMoney } from '@/lib/format'
import { useApiMutation } from '@/hooks/useApiMutation'
import { usePipelines } from '@/hooks/useLookups'
import { PageHeader } from '@/components/PageHeader'
import { StatusBadge } from '@/components/StatusBadge'
import { ErrorState } from '@/components/ErrorState'
import { LoadingState } from '@/components/LoadingState'
import { confirmAction } from '@/components/ConfirmDialog'
import { useAuth } from '@/features/auth/useAuth'
import type { Deal } from '@/types/models'
import { DealForm } from './DealForm'
import { NotesPanel } from '../shared/NotesPanel'
import { DocumentsPanel } from '../shared/DocumentsPanel'
import { TimelinePanel } from '../shared/TimelinePanel'
import { DetailList } from '../shared/DetailList'
import { CustomFieldValues } from '../shared/CustomFieldInputs'
import { ActivitiesPanel } from '../activities/ActivitiesPanel'

export default function DealDetailPage() {
  const id = Number(useParams().id)
  const navigate = useNavigate()
  const { can } = useAuth()
  const { data: pipelines = [] } = usePipelines()
  const [editing, setEditing] = useState(false)
  const [lostOpen, setLostOpen] = useState(false)
  const [lostReason, setLostReason] = useState('')
  const { data: deal, isLoading, error, refetch } = useQuery({ queryKey: ['deals', id], queryFn: () => api.get<Deal>(`/deals/${id}`) })
  const move = useApiMutation<Deal, { stageId: number; reason?: string }>({
    mutationFn: ({ stageId, reason }) => api.post(`/deals/${id}/move`, { pipeline_stage_id: stageId, lost_reason: reason }),
    invalidate: [['deals'], ['timeline'], ['dashboard']],
    successMessage: 'Stage updated.',
  })
  const remove = useApiMutation({ mutationFn: () => api.delete(`/deals/${id}`), invalidate: [['deals']], successMessage: 'Deal deleted.', onSuccess: () => navigate('/crm/deals') })

  if (isLoading) return <LoadingState />
  if (error || !deal) return <ErrorState error={error} onRetry={() => void refetch()} />

  const canWrite = can('deals.update')
  const stages = pipelines.find((p) => p.id === deal.pipeline_id)?.stages ?? []
  const openStages = stages.filter((s) => s.type === 'open')
  const won = stages.find((s) => s.type === 'won')
  const lost = stages.find((s) => s.type === 'lost')
  const currentIndex = openStages.findIndex((s) => s.id === deal.pipeline_stage_id)

  return (
    <>
      <PageHeader
        breadcrumbs={[{ label: 'Deals', to: '/crm/deals' }, { label: deal.title }]}
        title={<Group gap="sm">{deal.title}<StatusBadge status={deal.status} size="lg" /></Group>}
        description={<Text span fw={650} className="tabular">{fmtMoney(deal.value, deal.currency)}</Text>}
        actions={
          <>
            {canWrite && deal.status === 'open' && won && <Button color="teal" leftSection={<IconCheck size={16} />} onClick={() => move.mutate({ stageId: won.id })}>Mark won</Button>}
            {canWrite && deal.status === 'open' && lost && <Button variant="light" color="red" leftSection={<IconX size={16} />} onClick={() => { setLostReason(''); setLostOpen(true) }}>Mark lost</Button>}
            {canWrite && deal.status !== 'open' && openStages[0] && <Button variant="default" onClick={() => move.mutate({ stageId: openStages[openStages.length - 1].id })}>Reopen</Button>}
            {canWrite && <Button variant="default" leftSection={<IconPencil size={16} />} onClick={() => setEditing(true)}>Edit</Button>}
            {can('deals.delete') && (
              <Button variant="subtle" color="red" leftSection={<IconTrash size={16} />}
                onClick={() => confirmAction({ title: 'Delete deal?', message: `${deal.title} will be archived.`, confirmLabel: 'Delete deal', danger: true, onConfirm: () => remove.mutate() })}>
                Delete
              </Button>
            )}
          </>
        }
      />

      {deal.status === 'open' && openStages.length > 0 && (
        <Group gap={4} mb="lg" wrap="nowrap" style={{ overflowX: 'auto' }} role="list" aria-label="Pipeline stages">
          {openStages.map((stage, index) => {
            const done = index < currentIndex
            const current = index === currentIndex
            return (
              <UnstyledButton
                key={stage.id}
                role="listitem"
                disabled={!canWrite || current}
                onClick={() => move.mutate({ stageId: stage.id })}
                aria-current={current ? 'step' : undefined}
                style={{
                  flex: 1,
                  minWidth: 120,
                  padding: '8px 12px',
                  borderRadius: 8,
                  background: current ? 'var(--mantine-primary-color-filled)' : done ? 'var(--mantine-primary-color-light)' : 'var(--app-muted-surface)',
                  color: current ? 'white' : undefined,
                }}
              >
                <Text size="sm" fw={current ? 650 : 500} truncate>{stage.name}</Text>
                <Text size="xs" opacity={0.75}>{stage.probability}%</Text>
              </UnstyledButton>
            )
          })}
        </Group>
      )}

      <Grid gap="lg">
        <Grid.Col span={{ base: 12, lg: 4 }}>
          <Card>
            <Stack gap="md">
              <DetailList
                cols={1}
                items={[
                  { label: 'Customer', value: deal.customer ? <Anchor component={Link} to={`/crm/customers/${deal.customer.id}`}>{deal.customer.display_name}</Anchor> : '—' },
                  { label: 'Stage', value: <Badge color={deal.stage?.color}>{deal.stage?.name}</Badge> },
                  { label: 'Probability', value: `${deal.probability}%` },
                  { label: 'Expected close', value: fmtDate(deal.expected_close_date) },
                  { label: 'Owner', value: deal.assignee?.name },
                  ...(deal.lead_id ? [{ label: 'From lead', value: <Anchor component={Link} to={`/crm/leads/${deal.lead_id}`}>Open lead</Anchor> }] : []),
                  ...(deal.status === 'won' ? [{ label: 'Won on', value: fmtDate(deal.won_at) }] : []),
                  ...(deal.status === 'lost' ? [{ label: 'Lost on', value: fmtDate(deal.lost_at) }, { label: 'Reason', value: deal.lost_reason }] : []),
                ]}
              />
              <dl style={{ display: 'grid', gap: 12, margin: 0 }}><CustomFieldValues entity="deal" values={deal.custom_fields} /></dl>
              {deal.description && <Text size="sm" style={{ whiteSpace: 'pre-wrap' }}>{deal.description}</Text>}
            </Stack>
          </Card>
        </Grid.Col>
        <Grid.Col span={{ base: 12, lg: 8 }}>
          <Tabs defaultValue="activities" keepMounted={false}>
            <Tabs.List mb="md">
              <Tabs.Tab value="activities">Activities</Tabs.Tab>
              <Tabs.Tab value="notes">Notes</Tabs.Tab>
              <Tabs.Tab value="documents">Documents</Tabs.Tab>
              <Tabs.Tab value="timeline">Timeline</Tabs.Tab>
            </Tabs.List>
            <Tabs.Panel value="activities"><Card><ActivitiesPanel filter={{ deal_id: id }} /></Card></Tabs.Panel>
            <Tabs.Panel value="notes"><NotesPanel type="deals" id={id} canWrite={canWrite} /></Tabs.Panel>
            <Tabs.Panel value="documents"><Card><DocumentsPanel type="deals" id={id} canWrite={canWrite} /></Card></Tabs.Panel>
            <Tabs.Panel value="timeline"><Card><TimelinePanel url={`/deals/${id}/timeline`} /></Card></Tabs.Panel>
          </Tabs>
        </Grid.Col>
      </Grid>

      <Modal opened={lostOpen} onClose={() => setLostOpen(false)} title="Mark deal as lost">
        <Stack>
          <TextInput label="Reason" value={lostReason} onChange={(e) => setLostReason(e.currentTarget.value)} data-autofocus />
          <Group justify="flex-end">
            <Button variant="default" onClick={() => setLostOpen(false)}>Cancel</Button>
            <Button color="red" onClick={() => { if (lost) move.mutate({ stageId: lost.id, reason: lostReason }); setLostOpen(false) }}>Mark as lost</Button>
          </Group>
        </Stack>
      </Modal>
      <DealForm opened={editing} onClose={() => setEditing(false)} deal={deal} onSaved={() => void refetch()} />
    </>
  )
}
