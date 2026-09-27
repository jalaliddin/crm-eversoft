import { useEffect } from 'react'
import { Alert, Checkbox, NumberInput, SimpleGrid, Table, Text } from '@mantine/core'
import { TimeInput } from '@mantine/dates'
import { Controller, useFieldArray, useForm, useWatch } from 'react-hook-form'
import { EntityDrawer } from '@/components/EntityDrawer'
import { FormField } from '@/components/FormField'
import { api } from '@/lib/api'
import { applyServerErrors } from '@/lib/forms'
import { useApiMutation } from '@/hooks/useApiMutation'
import type { ScheduleDay, WorkSchedule } from '@/types/models'
import { WEEKDAYS } from './weekdays'


interface Values {
  name: string
  type: 'fixed' | 'shift' | 'flexible'
  timezone: string | null
  grace_minutes: number
  early_leave_grace_minutes: number
  late_mode: 'after_grace' | 'from_start'
  required_daily_minutes: number | null
  overtime_enabled: boolean
  overtime_threshold_minutes: number
  is_default: boolean
  status: 'active' | 'inactive'
  days: ScheduleDay[]
}

const defaultDays = (): ScheduleDay[] =>
  WEEKDAYS.map((_, i) => ({
    day_of_week: i + 1,
    is_working_day: i < 5,
    start_time: i < 5 ? '09:00' : null,
    end_time: i < 5 ? '18:00' : null,
    break_start: i < 5 ? '13:00' : null,
    break_end: i < 5 ? '14:00' : null,
    break_minutes: null,
  }))

export function ScheduleEditor({ opened, onClose, schedule }: { opened: boolean; onClose: () => void; schedule?: WorkSchedule | null }) {
  const form = useForm<Values>()
  const { fields } = useFieldArray({ control: form.control, name: 'days' })
  const type = useWatch({ control: form.control, name: 'type' })
  const days = useWatch({ control: form.control, name: 'days' })

  useEffect(() => {
    if (!opened) return
    form.reset({
      name: schedule?.name ?? '',
      type: schedule?.type ?? 'fixed',
      timezone: schedule?.timezone ?? null,
      grace_minutes: schedule?.grace_minutes ?? 10,
      early_leave_grace_minutes: schedule?.early_leave_grace_minutes ?? 0,
      late_mode: schedule?.late_mode ?? 'after_grace',
      required_daily_minutes: schedule?.required_daily_minutes ?? null,
      overtime_enabled: schedule?.overtime_enabled ?? false,
      overtime_threshold_minutes: schedule?.overtime_threshold_minutes ?? 0,
      is_default: schedule?.is_default ?? false,
      status: schedule?.status ?? 'active',
      days: schedule?.days?.length === 7 ? schedule.days : defaultDays(),
    })
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [opened, schedule])

  const save = useApiMutation<WorkSchedule, Values>({
    mutationFn: (values) => (schedule ? api.put(`/work-schedules/${schedule.id}`, values) : api.post('/work-schedules', values)),
    invalidate: [['work-schedules']],
    successMessage: (s) => `${s.name} saved.`,
    onSuccess: onClose,
    silentError: true,
  })

  const flexible = type === 'flexible'
  const overnight = days?.some((d) => d.is_working_day && d.start_time && d.end_time && d.end_time <= d.start_time)

  return (
    <EntityDrawer opened={opened} onClose={onClose} title={schedule ? `Edit ${schedule.name}` : 'New work schedule'} size="xl" formId="schedule-form" submitting={save.isPending}>
      <form id="schedule-form" onSubmit={form.handleSubmit((v) => save.mutate(v, { onError: (e) => applyServerErrors(e, form.setError) }))}>
        <SimpleGrid cols={{ base: 1, sm: 2 }} spacing="sm">
          <FormField control={form.control} name="name" label="Name" required autoFocus />
          <FormField control={form.control} name="type" label="Type" kind="select" data={[
            { value: 'fixed', label: 'Fixed hours' }, { value: 'shift', label: 'Shift (may cross midnight)' }, { value: 'flexible', label: 'Flexible (daily hours only)' },
          ]} />
          {!flexible && <FormField control={form.control} name="grace_minutes" label="Grace period" kind="number" suffix=" min" min={0} max={240} description="Arrivals within this window are not late." />}
          {!flexible && (
            <FormField control={form.control} name="late_mode" label="Once the grace is exceeded, count lateness" kind="select" data={[
              { value: 'after_grace', label: 'After the grace period (08:45 → 5 min)' },
              { value: 'from_start', label: 'From the scheduled start (08:45 → 15 min)' },
            ]} />
          )}
          {!flexible && <FormField control={form.control} name="early_leave_grace_minutes" label="Early-leave tolerance" kind="number" suffix=" min" min={0} max={240} />}
          <FormField control={form.control} name="required_daily_minutes" label="Required per day" kind="number" suffix=" min" min={1} max={1440} description={flexible ? 'Required for flexible schedules.' : 'Leave empty to use the scheduled hours minus break.'} required={flexible} />
          <FormField control={form.control} name="timezone" label="Timezone" placeholder="Organization timezone" description="Only for teams in another timezone." />
          <FormField control={form.control} name="overtime_threshold_minutes" label="Count overtime from" kind="number" suffix=" min" min={0} max={600} />
        </SimpleGrid>
        <SimpleGrid cols={{ base: 1, sm: 3 }} spacing="sm" mt="md">
          <FormField control={form.control} name="overtime_enabled" label="Track overtime" kind="switch" />
          <FormField control={form.control} name="is_default" label="Default for employees without a schedule" kind="switch" />
          <Controller control={form.control} name="status" render={({ field }) => <Checkbox label="Active" checked={field.value === 'active'} onChange={(e) => field.onChange(e.currentTarget.checked ? 'active' : 'inactive')} />} />
        </SimpleGrid>

        {overnight && <Alert mt="md" color="khiva" variant="light">Days whose end time is earlier than the start run past midnight; punches until 6 hours after the end count for the start day.</Alert>}

        <Table.ScrollContainer minWidth={720} mt="md">
          <Table verticalSpacing={6}>
            <Table.Thead>
              <Table.Tr>
                <Table.Th>Day</Table.Th>
                <Table.Th>Working</Table.Th>
                {!flexible && <Table.Th>Start</Table.Th>}
                {!flexible && <Table.Th>End</Table.Th>}
                {!flexible && <Table.Th>Break from</Table.Th>}
                {!flexible && <Table.Th>Break to</Table.Th>}
                <Table.Th>Break (min)</Table.Th>
              </Table.Tr>
            </Table.Thead>
            <Table.Tbody>
              {fields.map((field, index) => {
                const working = days?.[index]?.is_working_day
                return (
                  <Table.Tr key={field.id}>
                    <Table.Td><Text size="sm" fw={500}>{WEEKDAYS[index]}</Text></Table.Td>
                    <Table.Td>
                      <Controller control={form.control} name={`days.${index}.is_working_day`} render={({ field: f }) => <Checkbox aria-label={`${WEEKDAYS[index]} is a working day`} checked={!!f.value} onChange={(e) => f.onChange(e.currentTarget.checked)} />} />
                    </Table.Td>
                    {!flexible && (['start_time', 'end_time', 'break_start', 'break_end'] as const).map((key) => (
                      <Table.Td key={key}>
                        <Controller
                          control={form.control}
                          name={`days.${index}.${key}`}
                          render={({ field: f, fieldState }) => (
                            <TimeInput size="xs" disabled={!working} value={f.value ?? ''} onChange={(e) => f.onChange(e.currentTarget.value || null)} error={fieldState.error?.message} aria-label={`${WEEKDAYS[index]} ${key.replace('_', ' ')}`} />
                          )}
                        />
                      </Table.Td>
                    ))}
                    <Table.Td>
                      <Controller control={form.control} name={`days.${index}.break_minutes`} render={({ field: f }) => (
                        <NumberInput size="xs" w={80} disabled={!working} value={f.value ?? ''} onChange={(v) => f.onChange(v === '' ? null : Number(v))} min={0} max={600} hideControls aria-label={`${WEEKDAYS[index]} break minutes`} />
                      )} />
                    </Table.Td>
                  </Table.Tr>
                )
              })}
            </Table.Tbody>
          </Table>
        </Table.ScrollContainer>
        <Text size="xs" c="dimmed" mt="xs">Use break minutes when the break has no fixed time; it is deducted from longer days only.</Text>
      </form>
    </EntityDrawer>
  )
}
