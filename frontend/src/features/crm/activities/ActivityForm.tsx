import { useEffect } from 'react'
import { SimpleGrid } from '@mantine/core'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { EntityDrawer } from '@/components/EntityDrawer'
import { FormField } from '@/components/FormField'
import { api } from '@/lib/api'
import { applyServerErrors, nullify } from '@/lib/forms'
import { dayjs, toTenantIso, tenantLocal } from '@/lib/format'
import { useOptions, useUsersLookup } from '@/hooks/useLookups'
import { useApiMutation } from '@/hooks/useApiMutation'
import type { Activity } from '@/types/models'

const schema = z.object({
  title: z.string().trim().min(1, 'Give the activity a title'),
  type: z.string().min(1),
  description: z.string().nullable(),
  due_at: z.string().nullable(),
  duration_minutes: z.number().nullable(),
  priority: z.string(),
  status: z.string(),
  assigned_to: z.string().nullable(),
})
type Values = z.infer<typeof schema>

interface Props {
  opened: boolean
  onClose: () => void
  activity?: Activity | null
  /** Pre-link to a CRM record when opened from its page. */
  links?: { customer_id?: number; lead_id?: number; deal_id?: number }
  defaultType?: Activity['type']
}

export function ActivityForm({ opened, onClose, activity, links, defaultType = 'task' }: Props) {
  const types = useOptions('activity_types')
  const priorities = useOptions('activity_priorities')
  const statuses = useOptions('activity_statuses')
  const { data: users = [] } = useUsersLookup()
  const form = useForm<Values>({ resolver: zodResolver(schema) })

  useEffect(() => {
    if (!opened) return
    form.reset({
      title: activity?.title ?? '',
      type: activity?.type ?? defaultType,
      description: activity?.description ?? null,
      due_at: activity?.due_at ? tenantLocal(activity.due_at, 'YYYY-MM-DD HH:mm:ss') : dayjs().add(1, 'day').hour(10).minute(0).format('YYYY-MM-DD HH:mm:ss'),
      duration_minutes: activity?.duration_minutes ?? null,
      priority: activity?.priority ?? 'normal',
      status: activity?.status ?? 'pending',
      assigned_to: activity?.assigned_to ? String(activity.assigned_to) : null,
    })
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [opened, activity])

  const save = useApiMutation<Activity, Values>({
    mutationFn: (values) => {
      const payload = {
        ...nullify(values),
        due_at: values.due_at ? toTenantIso(values.due_at.slice(0, 16)) : null,
        assigned_to: values.assigned_to ? Number(values.assigned_to) : null,
        ...(activity ? {} : links),
      }
      return activity ? api.put(`/activities/${activity.id}`, payload) : api.post('/activities', payload)
    },
    invalidate: [['activities'], ['timeline'], ['dashboard']],
    successMessage: activity ? 'Activity saved.' : 'Activity created.',
    onSuccess: onClose,
    silentError: true,
  })

  const submit = form.handleSubmit((values) => save.mutate(values, { onError: (e) => applyServerErrors(e, form.setError) }))

  return (
    <EntityDrawer opened={opened} onClose={onClose} title={activity ? 'Edit activity' : 'New activity'} formId="activity-form" submitting={save.isPending} submitLabel={activity ? 'Save changes' : 'Create activity'}>
      <form id="activity-form" onSubmit={submit}>
        <SimpleGrid cols={{ base: 1, sm: 2 }} spacing="sm">
          <FormField control={form.control} name="type" label="Type" kind="select" data={types} />
          <FormField control={form.control} name="priority" label="Priority" kind="select" data={priorities} />
        </SimpleGrid>
        <FormField control={form.control} name="title" label="Title" required autoFocus />
        <FormField control={form.control} name="description" label="Details" kind="textarea" />
        <SimpleGrid cols={{ base: 1, sm: 2 }} spacing="sm">
          <FormField control={form.control} name="due_at" label="Due" kind="datetime" clearable />
          <FormField control={form.control} name="duration_minutes" label="Duration" kind="number" suffix=" min" min={1} max={1440} />
          <FormField control={form.control} name="assigned_to" label="Assigned to" kind="select" data={users} searchable clearable />
          <FormField control={form.control} name="status" label="Status" kind="select" data={statuses} />
        </SimpleGrid>
      </form>
    </EntityDrawer>
  )
}
