import { useEffect, useState } from 'react'
import { SimpleGrid } from '@mantine/core'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useQuery } from '@tanstack/react-query'
import { EntityDrawer } from '@/components/EntityDrawer'
import { FormField } from '@/components/FormField'
import { api, ApiError } from '@/lib/api'
import { applyServerErrors, nullify } from '@/lib/forms'
import { useOptions, useTags, useUsersLookup } from '@/hooks/useLookups'
import { useApiMutation } from '@/hooks/useApiMutation'
import { useAuth } from '@/features/auth/useAuth'
import type { Customer, Lead } from '@/types/models'
import { SOURCES } from '../shared/sources'
import { CustomFieldInputs } from '../shared/CustomFieldInputs'

const schema = z.object({
  name: z.string().trim().min(1, 'Enter the lead name'),
  company_name: z.string().nullable(),
  phone: z.string().nullable(),
  email: z.union([z.literal(''), z.string().email('Enter a valid email')]).nullable(),
  source: z.string().nullable(),
  status: z.string(),
  expected_value: z.number().nullable(),
  assigned_to: z.string().nullable(),
  customer_id: z.string().nullable(),
  description: z.string().nullable(),
  lost_reason: z.string().nullable(),
  tag_ids: z.array(z.string()),
})
type Values = z.infer<typeof schema>

export function LeadForm({ opened, onClose, lead, onSaved }: { opened: boolean; onClose: () => void; lead?: Lead | null; onSaved?: (l: Lead) => void }) {
  const { me, can } = useAuth()
  const statuses = useOptions('lead_statuses').filter((s) => s.value !== 'converted')
  const { data: users = [] } = useUsersLookup()
  const { data: tags = [] } = useTags()
  const customers = useQuery({
    queryKey: ['customers', 'picker'],
    queryFn: () => api.list<Customer>('/customers', { per_page: 100, sort: 'last_name' }),
    enabled: opened && can('customers.view'),
    select: (r) => r.data.map((c) => ({ value: String(c.id), label: c.display_name })),
  })
  const [custom, setCustom] = useState<Record<string, unknown>>({})
  const [customErrors, setCustomErrors] = useState<Record<string, string>>({})
  const form = useForm<Values>({ resolver: zodResolver(schema) })

  useEffect(() => {
    if (!opened) return
    form.reset({
      name: lead?.name ?? '',
      company_name: lead?.company_name ?? '',
      phone: lead?.phone ?? '',
      email: lead?.email ?? '',
      source: lead?.source ?? null,
      status: lead?.status ?? 'new',
      expected_value: lead?.expected_value ?? null,
      assigned_to: lead?.assigned_to ? String(lead.assigned_to) : String(me?.user.id ?? ''),
      customer_id: lead?.customer_id ? String(lead.customer_id) : null,
      description: lead?.description ?? '',
      lost_reason: lead?.lost_reason ?? '',
      tag_ids: lead?.tags?.map((t) => String(t.id)) ?? [],
    })
    setCustom(lead?.custom_fields ?? {})
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [opened, lead])

  const save = useApiMutation<Lead, Values>({
    mutationFn: (values) => {
      const payload = {
        ...nullify(values),
        assigned_to: values.assigned_to ? Number(values.assigned_to) : null,
        customer_id: values.customer_id ? Number(values.customer_id) : null,
        tag_ids: values.tag_ids.map(Number),
        custom_fields: custom,
      }
      return lead ? api.put(`/leads/${lead.id}`, payload) : api.post('/leads', payload)
    },
    invalidate: [['leads'], ['timeline'], ['dashboard']],
    successMessage: lead ? 'Lead saved.' : 'Lead created.',
    onSuccess: (data) => {
      onSaved?.(data)
      onClose()
    },
    silentError: true,
  })

  const submit = form.handleSubmit((values) =>
    save.mutate(values, {
      onError: (e) => {
        if (e instanceof ApiError) setCustomErrors(Object.fromEntries(Object.entries(e.errors).map(([k, v]) => [k, v[0]])))
        applyServerErrors(e, form.setError)
      },
    }),
  )

  return (
    <EntityDrawer opened={opened} onClose={onClose} title={lead ? 'Edit lead' : 'New lead'} formId="lead-form" submitting={save.isPending} submitLabel={lead ? 'Save changes' : 'Create lead'}>
      <form id="lead-form" onSubmit={submit}>
        <FormField control={form.control} name="name" label="Contact name" required autoFocus />
        <SimpleGrid cols={{ base: 1, sm: 2 }} spacing="sm" mt="sm">
          <FormField control={form.control} name="company_name" label="Company" />
          <FormField control={form.control} name="phone" label="Phone" />
          <FormField control={form.control} name="email" label="Email" kind="email" />
          <FormField control={form.control} name="source" label="Source" kind="select" data={SOURCES} clearable />
          <FormField control={form.control} name="status" label="Status" kind="select" data={statuses} />
          <FormField control={form.control} name="expected_value" label="Expected value" kind="number" min={0} suffix={` ${me?.organization?.currency ?? ''}`} />
          <FormField control={form.control} name="assigned_to" label="Assigned to" kind="select" data={users} searchable clearable disabled={!can('leads.assign')} description={can('leads.assign') ? undefined : 'Only managers can reassign leads.'} />
          <FormField control={form.control} name="customer_id" label="Existing customer" kind="select" data={customers.data ?? []} searchable clearable />
        </SimpleGrid>
        <FormField control={form.control} name="tag_ids" label="Tags" kind="multiselect" data={tags.map((t) => ({ value: String(t.id), label: t.name }))} searchable />
        <FormField control={form.control} name="description" label="What do they need?" kind="textarea" />
        {form.watch('status') === 'lost' && <FormField control={form.control} name="lost_reason" label="Why was it lost?" />}
        <CustomFieldInputs entity="lead" value={custom} onChange={setCustom} errors={customErrors} />
      </form>
    </EntityDrawer>
  )
}
