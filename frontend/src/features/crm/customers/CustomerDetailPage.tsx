import { useState } from 'react'
import { Anchor, Badge, Button, Card, Grid, Group, Stack, Tabs, Text } from '@mantine/core'
import { IconPencil, IconTrash } from '@tabler/icons-react'
import { useQuery } from '@tanstack/react-query'
import { Link, useNavigate, useParams, useSearchParams } from 'react-router'
import { api } from '@/lib/api'
import { fmtDate, fmtMoney } from '@/lib/format'
import { useApiMutation } from '@/hooks/useApiMutation'
import { PageHeader } from '@/components/PageHeader'
import { StatusBadge } from '@/components/StatusBadge'
import { StatCard } from '@/components/StatCard'
import { DataTable } from '@/components/DataTable'
import { ErrorState } from '@/components/ErrorState'
import { LoadingState } from '@/components/LoadingState'
import { confirmAction } from '@/components/ConfirmDialog'
import { useAuth } from '@/features/auth/useAuth'
import type { Customer, Deal, Lead } from '@/types/models'
import { CustomerForm } from './CustomerForm'
import { ContactsPanel } from './ContactsPanel'
import { NotesPanel } from '../shared/NotesPanel'
import { DocumentsPanel } from '../shared/DocumentsPanel'
import { TimelinePanel } from '../shared/TimelinePanel'
import { DetailList } from '../shared/DetailList'
import { CustomFieldValues } from '../shared/CustomFieldInputs'
import { ActivitiesPanel } from '../activities/ActivitiesPanel'

export default function CustomerDetailPage() {
  const id = Number(useParams().id)
  const navigate = useNavigate()
  const { can } = useAuth()
  const [searchParams, setSearchParams] = useSearchParams()
  const tab = searchParams.get('tab') ?? 'overview'
  const [editing, setEditing] = useState(false)

  const { data: customer, isLoading, error, refetch } = useQuery({ queryKey: ['customers', id], queryFn: () => api.get<Customer>(`/customers/${id}`) })
  const leads = useQuery({ queryKey: ['leads', { customer_id: id }], queryFn: () => api.list<Lead>('/leads', { customer_id: id, per_page: 50 }), enabled: tab === 'leads' && can('leads.view') })
  const deals = useQuery({ queryKey: ['deals', { customer_id: id }], queryFn: () => api.list<Deal>('/deals', { customer_id: id, per_page: 50 }), enabled: tab === 'deals' && can('deals.view') })
  const remove = useApiMutation({ mutationFn: () => api.delete(`/customers/${id}`), invalidate: [['customers']], successMessage: 'Customer deleted.', onSuccess: () => navigate('/crm/customers') })

  if (isLoading) return <LoadingState />
  if (error || !customer) return <ErrorState error={error} onRetry={() => void refetch()} />

  const canWrite = can('customers.update')

  return (
    <>
      <PageHeader
        breadcrumbs={[{ label: 'Customers', to: '/crm/customers' }, { label: customer.display_name }]}
        title={
          <Group gap="sm">
            {customer.display_name}
            <StatusBadge status={customer.status} size="lg" />
          </Group>
        }
        description={[customer.type === 'company' ? 'Company' : 'Person', customer.city, customer.source && `via ${customer.source}`].filter(Boolean).join(' · ')}
        actions={
          <>
            {canWrite && <Button variant="default" leftSection={<IconPencil size={16} />} onClick={() => setEditing(true)}>Edit</Button>}
            {can('customers.delete') && (
              <Button
                variant="subtle"
                color="red"
                leftSection={<IconTrash size={16} />}
                onClick={() => confirmAction({ title: `Delete ${customer.display_name}?`, message: 'The customer is archived; linked deals and leads keep their history.', confirmLabel: 'Delete customer', danger: true, onConfirm: () => remove.mutate() })}
              >
                Delete
              </Button>
            )}
          </>
        }
      />

      <Tabs value={tab} onChange={(v) => setSearchParams({ tab: v ?? 'overview' }, { replace: true })} keepMounted={false}>
        <Tabs.List mb="lg" style={{ flexWrap: 'nowrap', overflowX: 'auto' }}>
          <Tabs.Tab value="overview">Overview</Tabs.Tab>
          <Tabs.Tab value="contacts">Contacts</Tabs.Tab>
          {can('leads.view') && <Tabs.Tab value="leads" rightSection={<Badge size="xs" variant="default">{customer.leads_count ?? 0}</Badge>}>Leads</Tabs.Tab>}
          {can('deals.view') && <Tabs.Tab value="deals" rightSection={<Badge size="xs" variant="default">{customer.deals_count ?? 0}</Badge>}>Deals</Tabs.Tab>}
          {can('activities.view') && <Tabs.Tab value="activities">Activities</Tabs.Tab>}
          <Tabs.Tab value="notes">Notes</Tabs.Tab>
          <Tabs.Tab value="documents">Documents</Tabs.Tab>
          <Tabs.Tab value="timeline">Timeline</Tabs.Tab>
        </Tabs.List>

        <Tabs.Panel value="overview">
          <Grid gap="lg">
            <Grid.Col span={{ base: 12, lg: 8 }}>
              <Card>
                <DetailList
                  items={[
                    { label: 'Phone', value: customer.phone ? <Anchor href={`tel:${customer.phone}`}>{customer.phone}</Anchor> : '—' },
                    { label: 'Second phone', value: customer.secondary_phone },
                    { label: 'Email', value: customer.email ? <Anchor href={`mailto:${customer.email}`}>{customer.email}</Anchor> : '—' },
                    { label: 'Website', value: customer.website ? <Anchor href={customer.website} target="_blank" rel="noopener noreferrer">{customer.website}</Anchor> : '—' },
                    { label: 'Address', value: [customer.address, customer.city, customer.country].filter(Boolean).join(', ') || '—' },
                    { label: 'Owner', value: customer.owner?.name },
                    { label: 'Added', value: `${fmtDate(customer.created_at)}${customer.created_by ? ` by ${customer.created_by.name}` : ''}` },
                    { label: 'Tags', value: customer.tags?.length ? <Group gap={4}>{customer.tags.map((t) => <Badge key={t.id} color={t.color}>{t.name}</Badge>)}</Group> : '—' },
                  ]}
                />
                <dl style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(220px, 1fr))', gap: 12, marginTop: 16, marginBottom: 0 }}>
                  <CustomFieldValues entity="customer" values={customer.custom_fields} />
                </dl>
                {customer.notes && (
                  <Stack gap={2} mt="md">
                    <Text size="xs" c="dimmed" fw={500}>Internal notes</Text>
                    <Text size="sm" style={{ whiteSpace: 'pre-wrap' }}>{customer.notes}</Text>
                  </Stack>
                )}
              </Card>
            </Grid.Col>
            <Grid.Col span={{ base: 12, lg: 4 }}>
              <Stack gap="sm">
                <StatCard compact label="Open deal value" value={fmtMoney(customer.open_deals_value ?? 0)} />
                <StatCard label="Deals" value={customer.deals_count ?? 0} />
                <StatCard label="Leads" value={customer.leads_count ?? 0} />
              </Stack>
            </Grid.Col>
          </Grid>
        </Tabs.Panel>

        <Tabs.Panel value="contacts"><Card><ContactsPanel customerId={id} canWrite={canWrite} /></Card></Tabs.Panel>

        <Tabs.Panel value="leads">
          <DataTable
            rows={leads.data?.data}
            loading={leads.isLoading}
            onRowClick={(l) => navigate(`/crm/leads/${l.id}`)}
            columns={[
              { key: 'name', header: 'Lead', render: (l) => <Text size="sm" fw={500}>{l.name}</Text> },
              { key: 'status', header: 'Status', render: (l) => <StatusBadge status={l.status} /> },
              { key: 'value', header: 'Expected value', align: 'right', render: (l) => <span className="tabular">{fmtMoney(l.expected_value)}</span> },
              { key: 'created_at', header: 'Created', render: (l) => fmtDate(l.created_at) },
            ]}
            empty={{ title: 'No leads for this customer' }}
            minWidth={520}
          />
        </Tabs.Panel>

        <Tabs.Panel value="deals">
          <Group justify="flex-end" mb="sm">
            {can('deals.create') && <Button size="xs" component={Link} to={`/crm/deals?new=1&customer_id=${id}`}>New deal</Button>}
          </Group>
          <DataTable
            rows={deals.data?.data}
            loading={deals.isLoading}
            onRowClick={(d) => navigate(`/crm/deals/${d.id}`)}
            columns={[
              { key: 'title', header: 'Deal', render: (d) => <Text size="sm" fw={500}>{d.title}</Text> },
              { key: 'stage', header: 'Stage', render: (d) => <Badge color={d.stage?.color}>{d.stage?.name}</Badge> },
              { key: 'value', header: 'Value', align: 'right', render: (d) => <span className="tabular">{fmtMoney(d.value, d.currency)}</span> },
              { key: 'close', header: 'Expected close', render: (d) => fmtDate(d.expected_close_date) },
            ]}
            empty={{ title: 'No deals yet' }}
            minWidth={520}
          />
        </Tabs.Panel>

        <Tabs.Panel value="activities"><Card><ActivitiesPanel filter={{ customer_id: id }} /></Card></Tabs.Panel>
        <Tabs.Panel value="notes"><NotesPanel type="customers" id={id} canWrite={canWrite} /></Tabs.Panel>
        <Tabs.Panel value="documents"><Card><DocumentsPanel type="customers" id={id} canWrite={canWrite} /></Card></Tabs.Panel>
        <Tabs.Panel value="timeline"><Card><TimelinePanel url={`/customers/${id}/timeline`} /></Card></Tabs.Panel>
      </Tabs>

      <CustomerForm opened={editing} onClose={() => setEditing(false)} customer={customer} onSaved={() => void refetch()} />
    </>
  )
}
