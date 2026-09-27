import { useState } from 'react'
import { Anchor, Button, Card, Grid, Group, Menu, Stack, Tabs, Text } from '@mantine/core'
import { IconArrowsExchange, IconChevronDown, IconPencil, IconTrash, IconTransform } from '@tabler/icons-react'
import { useQuery } from '@tanstack/react-query'
import { Link, useNavigate, useParams, useSearchParams } from 'react-router'
import { api } from '@/lib/api'
import { fmtDate, fmtDateTime, fmtMoney } from '@/lib/format'
import { useApiMutation } from '@/hooks/useApiMutation'
import { useOptions } from '@/hooks/useLookups'
import { PageHeader } from '@/components/PageHeader'
import { StatusBadge } from '@/components/StatusBadge'
import { ErrorState } from '@/components/ErrorState'
import { LoadingState } from '@/components/LoadingState'
import { confirmAction } from '@/components/ConfirmDialog'
import { useAuth } from '@/features/auth/useAuth'
import type { Lead } from '@/types/models'
import { LeadForm } from './LeadForm'
import { ConvertLeadModal } from './ConvertLeadModal'
import { NotesPanel } from '../shared/NotesPanel'
import { DocumentsPanel } from '../shared/DocumentsPanel'
import { TimelinePanel } from '../shared/TimelinePanel'
import { DetailList } from '../shared/DetailList'
import { CustomFieldValues } from '../shared/CustomFieldInputs'
import { ActivitiesPanel } from '../activities/ActivitiesPanel'

export default function LeadDetailPage() {
  const id = Number(useParams().id)
  const navigate = useNavigate()
  const { can } = useAuth()
  const [searchParams, setSearchParams] = useSearchParams()
  const [editing, setEditing] = useState(false)
  const [converting, setConverting] = useState(false)
  const statuses = useOptions('lead_statuses').filter((s) => s.value !== 'converted')
  const { data: lead, isLoading, error, refetch } = useQuery({ queryKey: ['leads', id], queryFn: () => api.get<Lead>(`/leads/${id}`) })
  const remove = useApiMutation({ mutationFn: () => api.delete(`/leads/${id}`), invalidate: [['leads']], successMessage: 'Lead deleted.', onSuccess: () => navigate('/crm/leads') })
  const move = useApiMutation<Lead, string>({ mutationFn: (status) => api.post(`/leads/${id}/move`, { status }), invalidate: [['leads'], ['timeline']], successMessage: 'Status updated.' })

  if (isLoading) return <LoadingState />
  if (error || !lead) return <ErrorState error={error} onRetry={() => void refetch()} />

  const canWrite = can('leads.update')
  const converted = lead.status === 'converted'
  const convertOpen = !converted && (converting || searchParams.get('convert') === '1')

  return (
    <>
      <PageHeader
        breadcrumbs={[{ label: 'Leads', to: '/crm/leads' }, { label: lead.name }]}
        title={<Group gap="sm">{lead.name}<StatusBadge status={lead.status} size="lg" /></Group>}
        description={[lead.company_name, lead.source && `via ${lead.source}`, `created ${fmtDate(lead.created_at)}`].filter(Boolean).join(' · ')}
        actions={
          <>
            {canWrite && !converted && (
              <Menu>
                <Menu.Target>
                  <Button variant="default" leftSection={<IconArrowsExchange size={16} />} rightSection={<IconChevronDown size={14} />}>Status</Button>
                </Menu.Target>
                <Menu.Dropdown>
                  {statuses.map((s) => (
                    <Menu.Item key={s.value} disabled={s.value === lead.status} onClick={() => move.mutate(s.value)}>{s.label}</Menu.Item>
                  ))}
                </Menu.Dropdown>
              </Menu>
            )}
            {canWrite && !converted && can('customers.create') && (
              <Button leftSection={<IconTransform size={16} />} onClick={() => setConverting(true)}>Convert</Button>
            )}
            {canWrite && <Button variant="default" leftSection={<IconPencil size={16} />} onClick={() => setEditing(true)}>Edit</Button>}
            {can('leads.delete') && (
              <Button variant="subtle" color="red" leftSection={<IconTrash size={16} />}
                onClick={() => confirmAction({ title: 'Delete lead?', message: `${lead.name} will be archived.`, confirmLabel: 'Delete lead', danger: true, onConfirm: () => remove.mutate() })}>
                Delete
              </Button>
            )}
          </>
        }
      />

      <Grid gap="lg">
        <Grid.Col span={{ base: 12, lg: 4 }}>
          <Card>
            <Stack gap="md">
              <DetailList
                cols={1}
                items={[
                  { label: 'Expected value', value: <Text fw={650} size="lg" className="tabular">{fmtMoney(lead.expected_value)}</Text> },
                  { label: 'Phone', value: lead.phone ? <Anchor href={`tel:${lead.phone}`}>{lead.phone}</Anchor> : '—' },
                  { label: 'Email', value: lead.email ? <Anchor href={`mailto:${lead.email}`}>{lead.email}</Anchor> : '—' },
                  { label: 'Assigned to', value: lead.assignee?.name },
                  { label: 'Customer', value: lead.customer ? <Anchor component={Link} to={`/crm/customers/${lead.customer.id}`}>{lead.customer.display_name}</Anchor> : '—' },
                  ...(converted ? [{ label: 'Converted', value: fmtDateTime(lead.converted_at) }] : []),
                  ...(lead.status === 'lost' ? [{ label: 'Lost because', value: lead.lost_reason }] : []),
                ]}
              />
              <dl style={{ display: 'grid', gap: 12, margin: 0 }}><CustomFieldValues entity="lead" values={lead.custom_fields} /></dl>
              {lead.description && (
                <div>
                  <Text size="xs" c="dimmed" fw={500}>What they need</Text>
                  <Text size="sm" style={{ whiteSpace: 'pre-wrap' }}>{lead.description}</Text>
                </div>
              )}
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
            <Tabs.Panel value="activities"><Card><ActivitiesPanel filter={{ lead_id: id }} /></Card></Tabs.Panel>
            <Tabs.Panel value="notes"><NotesPanel type="leads" id={id} canWrite={canWrite} /></Tabs.Panel>
            <Tabs.Panel value="documents"><Card><DocumentsPanel type="leads" id={id} canWrite={canWrite} /></Card></Tabs.Panel>
            <Tabs.Panel value="timeline"><Card><TimelinePanel url={`/leads/${id}/timeline`} /></Card></Tabs.Panel>
          </Tabs>
        </Grid.Col>
      </Grid>

      <LeadForm opened={editing} onClose={() => setEditing(false)} lead={lead} onSaved={() => void refetch()} />
      {convertOpen && <ConvertLeadModal lead={lead} opened={convertOpen} onClose={() => { setConverting(false); setSearchParams({}, { replace: true }) }} />}
    </>
  )
}
