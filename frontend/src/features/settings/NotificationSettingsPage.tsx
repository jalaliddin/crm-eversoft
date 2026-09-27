import { useState } from 'react'
import { Button, Card, Checkbox, Table, Text } from '@mantine/core'
import { useQuery } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { PageHeader } from '@/components/PageHeader'
import { LoadingState } from '@/components/LoadingState'
import { useApiMutation } from '@/hooks/useApiMutation'
import type { Organization } from '@/types/models'

const EVENTS: { key: string; label: string; who: string }[] = [
  { key: 'employee_late', label: 'Employee arrived late', who: 'People who can view attendance' },
  { key: 'early_leave', label: 'Employee left early', who: 'People who can view attendance' },
  { key: 'employee_absent', label: 'Daily absence summary', who: 'People who can view attendance' },
  { key: 'payroll_calculated', label: 'Payroll calculated', who: 'People who can approve payroll' },
  { key: 'lead_assigned', label: 'Lead assigned to you', who: 'The assignee' },
  { key: 'deal_stage_changed', label: 'Your deal changed stage', who: 'The deal owner' },
  { key: 'task_due', label: 'Task due soon', who: 'The assignee' },
]
const CHANNELS = [{ key: 'database', label: 'In the app' }, { key: 'mail', label: 'Email' }]
const FUTURE = ['Telegram', 'SMS', 'WhatsApp', 'Push']

export default function NotificationSettingsPage() {
  const { data } = useQuery({ queryKey: ['settings', 'organization'], queryFn: () => api.get<Organization>('/settings/organization') })
  if (!data) return <LoadingState />
  return <NotificationMatrix initial={data.settings.notifications} />
}

function NotificationMatrix({ initial }: { initial: Record<string, string[]> }) {
  const [matrix, setMatrix] = useState<Record<string, string[]>>(initial)
  const save = useApiMutation({ mutationFn: () => api.put('/settings/organization', { settings: { notifications: matrix } }), invalidate: [['settings']], successMessage: 'Notification settings saved.' })

  const toggle = (event: string, channel: string, on: boolean) =>
    setMatrix((m) => ({ ...m, [event]: on ? [...new Set([...(m[event] ?? []), channel])] : (m[event] ?? []).filter((c) => c !== channel) }))

  return (
    <>
      <PageHeader title="Notifications" description="Choose how each event reaches your team." actions={<Button onClick={() => save.mutate()} loading={save.isPending}>Save</Button>} />
      <Card maw={900}>
        <Table.ScrollContainer minWidth={620}>
          <Table verticalSpacing="sm">
            <Table.Thead>
              <Table.Tr>
                <Table.Th>Event</Table.Th>
                {CHANNELS.map((c) => <Table.Th key={c.key} ta="center">{c.label}</Table.Th>)}
                {FUTURE.map((f) => <Table.Th key={f} ta="center" c="dimmed">{f}</Table.Th>)}
              </Table.Tr>
            </Table.Thead>
            <Table.Tbody>
              {EVENTS.map((e) => (
                <Table.Tr key={e.key}>
                  <Table.Td><Text size="sm" fw={500}>{e.label}</Text><Text size="xs" c="dimmed">{e.who}</Text></Table.Td>
                  {CHANNELS.map((c) => (
                    <Table.Td key={c.key}><Checkbox mx="auto" w="fit-content" aria-label={`${e.label} by ${c.label}`} checked={(matrix[e.key] ?? []).includes(c.key)} onChange={(ev) => toggle(e.key, c.key, ev.currentTarget.checked)} /></Table.Td>
                  ))}
                  {FUTURE.map((f) => <Table.Td key={f}><Checkbox mx="auto" w="fit-content" disabled aria-label={`${f} coming later`} /></Table.Td>)}
                </Table.Tr>
              ))}
            </Table.Tbody>
          </Table>
        </Table.ScrollContainer>
        <Text size="xs" c="dimmed" mt="sm">Telegram, SMS, WhatsApp and push channels are planned.</Text>
      </Card>
    </>
  )
}
