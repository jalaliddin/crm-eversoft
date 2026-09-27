import { useEffect, useState } from 'react'
import { SegmentedControl, SimpleGrid, Stack, Text } from '@mantine/core'
import { useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { EntityDrawer } from '@/components/EntityDrawer'
import { FormField } from '@/components/FormField'
import { api, ApiError } from '@/lib/api'
import { applyServerErrors, nullify } from '@/lib/forms'
import { useOptions, useTags, useUsersLookup } from '@/hooks/useLookups'
import { useApiMutation } from '@/hooks/useApiMutation'
import type { Customer } from '@/types/models'
import { CustomFieldInputs } from '../shared/CustomFieldInputs'
import { SOURCES } from '../shared/sources'

const schema = z
  .object({
    type: z.enum(['individual', 'company']),
    first_name: z.string().nullable(),
    last_name: z.string().nullable(),
    company_name: z.string().nullable(),
    email: z.union([z.literal(''), z.string().email('Enter a valid email')]).nullable(),
    phone: z.string().nullable(),
    secondary_phone: z.string().nullable(),
    website: z.string().nullable(),
    address: z.string().nullable(),
    city: z.string().nullable(),
    country: z.string().nullable(),
    source: z.string().nullable(),
    status: z.string(),
    owner_id: z.string().nullable(),
    notes: z.string().nullable(),
    tag_ids: z.array(z.string()),
  })
  .superRefine((v, ctx) => {
    if (v.type === 'company' && !v.company_name?.trim()) ctx.addIssue({ code: 'custom', path: ['company_name'], message: 'Company name is required' })
    if (v.type === 'individual' && !v.first_name?.trim()) ctx.addIssue({ code: 'custom', path: ['first_name'], message: 'First name is required' })
  })
type Values = z.infer<typeof schema>


interface Props {
  opened: boolean
  onClose: () => void
  customer?: Customer | null
  onSaved?: (customer: Customer) => void
}

export function CustomerForm({ opened, onClose, customer, onSaved }: Props) {
  const statuses = useOptions('customer_statuses')
  const { data: users = [] } = useUsersLookup()
  const { data: tags = [] } = useTags()
  const [custom, setCustom] = useState<Record<string, unknown>>({})
  const [customErrors, setCustomErrors] = useState<Record<string, string>>({})
  const form = useForm<Values>({ resolver: zodResolver(schema) })
  const type = useWatch({ control: form.control, name: 'type' })

  useEffect(() => {
    if (!opened) return
    form.reset({
      type: customer?.type ?? 'individual',
      first_name: customer?.first_name ?? '',
      last_name: customer?.last_name ?? '',
      company_name: customer?.company_name ?? '',
      email: customer?.email ?? '',
      phone: customer?.phone ?? '',
      secondary_phone: customer?.secondary_phone ?? '',
      website: customer?.website ?? '',
      address: customer?.address ?? '',
      city: customer?.city ?? '',
      country: customer?.country ?? 'Uzbekistan',
      source: customer?.source ?? null,
      status: customer?.status ?? 'active',
      owner_id: customer?.owner_id ? String(customer.owner_id) : null,
      notes: customer?.notes ?? '',
      tag_ids: customer?.tags?.map((t) => String(t.id)) ?? [],
    })
    setCustom(customer?.custom_fields ?? {})
    setCustomErrors({})
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [opened, customer])

  const save = useApiMutation<Customer, Values>({
    mutationFn: (values) => {
      const payload = { ...nullify(values), owner_id: values.owner_id ? Number(values.owner_id) : null, tag_ids: values.tag_ids.map(Number), custom_fields: custom }
      return customer ? api.put(`/customers/${customer.id}`, payload) : api.post('/customers', payload)
    },
    invalidate: [['customers'], ['timeline'], ['dashboard']],
    successMessage: customer ? 'Customer saved.' : 'Customer created.',
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
    <EntityDrawer opened={opened} onClose={onClose} title={customer ? `Edit ${customer.display_name}` : 'New customer'} formId="customer-form" submitting={save.isPending} submitLabel={customer ? 'Save changes' : 'Create customer'}>
      <form id="customer-form" onSubmit={submit}>
        <Stack gap="sm">
          <SegmentedControl
            value={type}
            onChange={(v) => form.setValue('type', v as Values['type'])}
            data={[{ value: 'individual', label: 'Person' }, { value: 'company', label: 'Company' }]}
            aria-label="Customer type"
          />
          {type === 'company' && <FormField control={form.control} name="company_name" label="Company name" required autoFocus />}
          <SimpleGrid cols={{ base: 1, sm: 2 }} spacing="sm">
            <FormField control={form.control} name="first_name" label={type === 'company' ? 'Contact first name' : 'First name'} required={type === 'individual'} />
            <FormField control={form.control} name="last_name" label={type === 'company' ? 'Contact last name' : 'Last name'} />
            <FormField control={form.control} name="phone" label="Phone" placeholder="+998 90 123 45 67" />
            <FormField control={form.control} name="secondary_phone" label="Second phone" />
            <FormField control={form.control} name="email" label="Email" kind="email" />
            <FormField control={form.control} name="website" label="Website" placeholder="https://" />
            <FormField control={form.control} name="city" label="City" />
            <FormField control={form.control} name="country" label="Country" />
          </SimpleGrid>
          <FormField control={form.control} name="address" label="Address" />
          <SimpleGrid cols={{ base: 1, sm: 3 }} spacing="sm">
            <FormField control={form.control} name="source" label="Source" kind="select" data={SOURCES} clearable />
            <FormField control={form.control} name="status" label="Status" kind="select" data={statuses} />
            <FormField control={form.control} name="owner_id" label="Owner" kind="select" data={users} searchable clearable />
          </SimpleGrid>
          <FormField control={form.control} name="tag_ids" label="Tags" kind="multiselect" data={tags.map((t) => ({ value: String(t.id), label: t.name }))} searchable />
          <FormField control={form.control} name="notes" label="Internal notes" kind="textarea" />
          <CustomFieldInputs entity="customer" value={custom} onChange={setCustom} errors={customErrors} />
          {Object.keys(customErrors).length > 0 && <Text c="red" size="xs">Some custom fields need attention.</Text>}
        </Stack>
      </form>
    </EntityDrawer>
  )
}
