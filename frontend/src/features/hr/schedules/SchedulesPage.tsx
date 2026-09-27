import { useState } from 'react'
import { ActionIcon, Badge, Box, Button, Card, Group, Menu, SimpleGrid, Stack, Text, Tooltip } from '@mantine/core'
import { IconDots, IconPlus } from '@tabler/icons-react'
import { api } from '@/lib/api'
import { fmtMinutes } from '@/lib/format'
import { PageHeader } from '@/components/PageHeader'
import { LoadingState } from '@/components/LoadingState'
import { EmptyState } from '@/components/EmptyState'
import { confirmAction } from '@/components/ConfirmDialog'
import { useSchedules } from '@/hooks/useLookups'
import { useApiMutation } from '@/hooks/useApiMutation'
import { useAuth } from '@/features/auth/useAuth'
import type { WorkSchedule } from '@/types/models'
import { ScheduleEditor } from './ScheduleEditor'
import { WEEKDAYS } from './weekdays'

function WeekStrip({ schedule }: { schedule: WorkSchedule }) {
  return (
    <Group gap={4} wrap="nowrap">
      {WEEKDAYS.map((name, i) => {
        const day = schedule.days.find((d) => d.day_of_week === i + 1)
        const label = day?.is_working_day ? (schedule.type === 'flexible' ? 'Flexible hours' : `${day.start_time}–${day.end_time}`) : 'Day off'
        return (
          <Tooltip key={name} label={`${name}: ${label}`}>
            <Box
              flex={1}
              py={6}
              ta="center"
              style={{ borderRadius: 6, background: day?.is_working_day ? 'var(--mantine-primary-color-light)' : 'var(--app-muted-surface)' }}
            >
              <Text size="xs" fw={600} c={day?.is_working_day ? undefined : 'dimmed'}>{name.slice(0, 2)}</Text>
            </Box>
          </Tooltip>
        )
      })}
    </Group>
  )
}

export default function SchedulesPage() {
  const { can } = useAuth()
  const { data, isLoading } = useSchedules()
  const [editing, setEditing] = useState<WorkSchedule | null | undefined>(undefined)
  const remove = useApiMutation<null, number>({ mutationFn: (id) => api.delete(`/work-schedules/${id}`), invalidate: [['work-schedules']], successMessage: 'Schedule deleted.' })

  return (
    <>
      <PageHeader
        title="Work schedules"
        description="Working days, hours, breaks and grace periods. Attendance is calculated against these."
        actions={can('schedules.manage') && <Button leftSection={<IconPlus size={16} />} onClick={() => setEditing(null)}>New schedule</Button>}
      />
      {isLoading ? <LoadingState /> : !data?.length ? (
        <EmptyState title="No schedules" message="Create at least one schedule and mark it as the default." />
      ) : (
        <SimpleGrid cols={{ base: 1, md: 2, xl: 3 }} spacing="md">
          {data.map((s) => {
            const firstWorking = s.days.find((d) => d.is_working_day)
            return (
              <Card key={s.id}>
                <Group justify="space-between" align="flex-start" wrap="nowrap">
                  <Stack gap={2}>
                    <Group gap="xs">
                      <Text fw={650}>{s.name}</Text>
                      {s.is_default && <Badge>Default</Badge>}
                      {s.status === 'inactive' && <Badge color="gray">Inactive</Badge>}
                    </Group>
                    <Text size="sm" c="dimmed">
                      {s.type === 'flexible'
                        ? `Flexible · ${fmtMinutes(s.required_daily_minutes)} per day`
                        : `${firstWorking?.start_time ?? '—'}–${firstWorking?.end_time ?? '—'} · grace ${s.grace_minutes} min`}
                    </Text>
                  </Stack>
                  {can('schedules.manage') && (
                    <Menu position="bottom-end">
                      <Menu.Target><ActionIcon variant="subtle" color="gray" aria-label={`Actions for ${s.name}`}><IconDots size={18} /></ActionIcon></Menu.Target>
                      <Menu.Dropdown>
                        <Menu.Item onClick={() => setEditing(s)}>Edit</Menu.Item>
                        <Menu.Item color="red" onClick={() => confirmAction({ title: `Delete ${s.name}?`, message: 'Schedules assigned to employees or marked as default cannot be deleted.', confirmLabel: 'Delete', danger: true, onConfirm: () => remove.mutate(s.id) })}>Delete</Menu.Item>
                      </Menu.Dropdown>
                    </Menu>
                  )}
                </Group>
                <Box mt="md"><WeekStrip schedule={s} /></Box>
                <Group gap="md" mt="md">
                  <Text size="xs" c="dimmed">{s.employees_count ?? 0} employees</Text>
                  <Text size="xs" c="dimmed">Overtime {s.overtime_enabled ? `from ${s.overtime_threshold_minutes} min` : 'off'}</Text>
                  <Text size="xs" c="dimmed">{s.late_mode === 'after_grace' ? 'Late counted after grace' : 'Late counted from start'}</Text>
                </Group>
              </Card>
            )
          })}
        </SimpleGrid>
      )}
      <ScheduleEditor opened={editing !== undefined} onClose={() => setEditing(undefined)} schedule={editing} />
    </>
  )
}
