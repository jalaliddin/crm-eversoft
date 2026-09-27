import { useEffect, useState } from 'react'
import { SimpleGrid } from '@mantine/core'
import { useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useQuery } from '@tanstack/react-query'
import { EntityDrawer } from '@/components/EntityDrawer'
import { FormField } from '@/components/FormField'
import { api, ApiError } from '@/lib/api'
import { applyServerErrors, nullify } from '@/lib/forms'
import { usePipelines, useTags, useUsersLookup } from '@/hooks/useLookups'
import { useApiMutation } from '@/hooks/useApiMutation'
import { useAuth } from '@/features/auth/useAuth'
import type { Customer, Deal } from '@/types/models'
import { CustomFieldInputs } from '../shared/CustomFieldInputs'

const schema = z.object({
  title: z.string().trim().min(1, 'Name the deal'),
  value: z.number().min(0),
  currency: z.string().length(3),
  expected_close_date: z.string().nullable(),
  customer_id: z.string().nullable(),
  pipeline_id: z.string().nullable(),
  pipeline_stage_id: z.string().nullable(),
  assigned_to: z.string().nullable(),
  description: z.string().nullable(),
  tag_ids: z.array(z.string()),
})
type Values = z.infer<typeof schema>

interface Props {
  opened: boolean
  onClose: () => void
  deal?: Deal | null
  defaults?: { customer_id?: number; pipeline_id?: number }
  onSaved?: (deal: Deal) => void
}

export function DealForm({ opened, onClose, deal, defaults, onSaved }: Props) {
  const { me, can } = useAuth()
  const { data: pipelines = [] } = usePipelines()
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
  const pipelineId = useWatch({ control: form.control, name: 'pipeline_id' })
  const stages = pipelines.find((p) => String(p.id) === pipelineId)?.stages ?? []

  useEffect(() => {
    if (!opened) return
    const defaultPipeline = pipelines.find((p) => p.is_default) ?? pipelines[0]
    form.reset({
      title: deal?.title ?? '',
      value: deal?.value ?? 0,
      currency: deal?.currency ?? me?.organization?.currency ?? 'UZS',
      expected_close_date: deal?.expected_close_date ?? null,
      customer_id: deal?.customer_id ? String(deal.customer_id) : defaults?.customer_id ? String(defaults.customer_id) : null,
      pipeline_id: String(deal?.pipeline_id ?? defaults?.pipeline_id ?? defaultPipeline?.id ?? ''),
      pipeline_stage_id: deal?.pipeline_stage_id ? String(deal.pipeline_stage_id) : null,
      assigned_to: deal?.assigned_to ? String(deal.assigned_to) : String(me?.user.id ?? ''),
      description: deal?.description ?? '',
      tag_ids: deal?.tags?.map((t) => String(t.id)) ?? [],
    })
    setCustom(deal?.custom_fields ?? {})
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [opened, deal, pipelines.length])

  const save = useApiMutation<Deal, Values>({
    mutationFn: (values) => {
      const toId = (v: string | null) => (v ? Number(v) : null)
      const payload = {
        ...nullify(values),
        customer_id: toId(values.customer_id),
        pipeline_id: toId(values.pipeline_id),
        pipeline_stage_id: toId(values.pipeline_stage_id),
        assigned_to: toId(values.assigned_to),
        tag_ids: values.tag_ids.map(Number),
        custom_fields: custom,
      }
      return deal ? api.put(`/deals/${deal.id}`, payload) : api.post('/deals', payload)
    },
    invalidate: [['deals'], ['timeline'], ['dashboard'], ['customers']],
    successMessage: deal ? 'Deal saved.' : 'Deal created.',
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
    <EntityDrawer opened={opened} onClose={onClose} title={deal ? 'Edit deal' : 'New deal'} formId="deal-form" submitting={save.isPending} submitLabel={deal ? 'Save changes' : 'Create deal'}>
      <form id="deal-form" onSubmit={submit}>
        <FormField control={form.control} name="title" label="Deal" required autoFocus placeholder="e.g. 200 staff uniforms" />
        <SimpleGrid cols={{ base: 1, sm: 2 }} spacing="sm" mt="sm">
          <FormField control={form.control} name="value" label="Value" kind="number" min={0} />
          <FormField control={form.control} name="currency" label="Currency" kind="select" data={['UZS', 'USD', 'EUR', 'RUB']} />
          <FormField control={form.control} name="customer_id" label="Customer" kind="select" data={customers.data ?? []} searchable clearable />
          <FormField control={form.control} name="expected_close_date" label="Expected close" kind="date" clearable />
          <FormField control={form.control} name="pipeline_id" label="Pipeline" kind="select" data={pipelines.map((p) => ({ value: String(p.id), label: p.name }))} />
          <FormField control={form.control} name="pipeline_stage_id" label="Stage" kind="select" data={stages.map((s) => ({ value: String(s.id), label: s.name }))} clearable placeholder="First stage" />
          <FormField control={form.control} name="assigned_to" label="Owner" kind="select" data={users} searchable clearable />
        </SimpleGrid>
        <FormField control={form.control} name="tag_ids" label="Tags" kind="multiselect" data={tags.map((t) => ({ value: String(t.id), label: t.name }))} searchable />
        <FormField control={form.control} name="description" label="Details" kind="textarea" />
        <CustomFieldInputs entity="deal" value={custom} onChange={setCustom} errors={customErrors} />
      </form>
    </EntityDrawer>
  )
}
