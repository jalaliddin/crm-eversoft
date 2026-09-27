import { Badge, Card, Group, SimpleGrid, Stack, Table, Text } from '@mantine/core'
import { IconAlertTriangle, IconCircleCheck, IconCircleX } from '@tabler/icons-react'
import { useQuery } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { fmtDateTime, fromNow } from '@/lib/format'
import { PageHeader } from '@/components/PageHeader'
import { LoadingState } from '@/components/LoadingState'
import { ErrorState } from '@/components/ErrorState'
import { StatusBadge } from '@/components/StatusBadge'

type Check = Record<string, unknown> & { status: 'ok' | 'warning' | 'error' }
interface Health {
  status: string
  application: Check
  database: Check
  cache: Check
  queue: Check
  scheduler: Check
  attendance_devices?: Check & { devices: { id: number; name: string; status: string; last_sync_at: string | null; last_sync_status: string | null; last_error: string | null }[] }
}

const ICON = { ok: <IconCircleCheck color="var(--mantine-color-teal-6)" />, warning: <IconAlertTriangle color="var(--mantine-color-saffron-6)" />, error: <IconCircleX color="var(--mantine-color-red-6)" /> }
const LABEL = { ok: 'Working', warning: 'Needs attention', error: 'Down' }

function CheckCard({ title, check, lines }: { title: string; check: Check; lines: [string, unknown][] }) {
  return (
    <Card>
      <Group justify="space-between" mb="xs">
        <Text fw={650}>{title}</Text>
        <Group gap={6}>{ICON[check.status]}<Text size="sm">{LABEL[check.status]}</Text></Group>
      </Group>
      <Stack gap={2}>
        {lines.filter(([, v]) => v !== undefined && v !== null).map(([label, value]) => (
          <Group key={label} justify="space-between"><Text size="sm" c="dimmed">{label}</Text><Text size="sm" className="tabular">{String(value)}</Text></Group>
        ))}
        {Boolean(check.error) && <Text size="sm" c="red">{String(check.error)}</Text>}
      </Stack>
    </Card>
  )
}

export default function SystemHealthPage({ platform = false }: { platform?: boolean }) {
  const { data, isLoading, error, refetch } = useQuery({ queryKey: ['health', platform], queryFn: () => api.get<Health>(platform ? '/platform/health' : '/system/health'), refetchInterval: 30_000 })
  if (isLoading) return <LoadingState />
  if (error || !data) return <ErrorState error={error} onRetry={() => void refetch()} />

  return (
    <>
      <PageHeader title="System health" description={<Group gap={6}>Overall <Badge color={data.status === 'ok' ? 'teal' : 'red'}>{data.status === 'ok' ? 'operational' : 'degraded'}</Badge></Group>} />
      <SimpleGrid cols={{ base: 1, sm: 2, lg: 3 }} spacing="md" mb="lg">
        <CheckCard title="Application" check={data.application} lines={[['Environment', data.application.environment], ['Laravel', data.application.laravel], ['PHP', data.application.php], ['Debug mode', data.application.debug ? 'on' : 'off']]} />
        <CheckCard title="Database" check={data.database} lines={[['Driver', data.database.driver], ['Latency', data.database.latency_ms !== undefined ? `${data.database.latency_ms} ms` : undefined]]} />
        <CheckCard title="Cache / Redis" check={data.cache} lines={[['Store', data.cache.store]]} />
        <CheckCard title="Queue" check={data.queue} lines={[['Connection', data.queue.connection], ['Waiting jobs', data.queue.pending], ['Failed jobs', data.queue.failed]]} />
        <CheckCard title="Scheduler" check={data.scheduler} lines={[['Last run', data.scheduler.last_run_at ? fromNow(String(data.scheduler.last_run_at)) : 'Never — is the scheduler container running?']]} />
        {data.attendance_devices && (
          <CheckCard title="Attendance devices" check={data.attendance_devices} lines={[['Devices', data.attendance_devices.total], ['Last sync', data.attendance_devices.last_sync_at ? fmtDateTime(String(data.attendance_devices.last_sync_at)) : 'Never']]} />
        )}
      </SimpleGrid>
      {data.attendance_devices && data.attendance_devices.devices.length > 0 && (
        <Card>
          <Text fw={650} mb="sm">Devices</Text>
          <Table.ScrollContainer minWidth={560}>
            <Table verticalSpacing="sm">
              <Table.Tbody>
                {data.attendance_devices.devices.map((d) => (
                  <Table.Tr key={d.id}>
                    <Table.Td><Text size="sm" fw={500}>{d.name}</Text></Table.Td>
                    <Table.Td><StatusBadge status={d.status} /></Table.Td>
                    <Table.Td>{d.last_sync_status ? <StatusBadge status={d.last_sync_status} /> : '—'}</Table.Td>
                    <Table.Td className="tabular">{fmtDateTime(d.last_sync_at)}</Table.Td>
                    <Table.Td><Text size="xs" c="red" lineClamp={2}>{d.last_error}</Text></Table.Td>
                  </Table.Tr>
                ))}
              </Table.Tbody>
            </Table>
          </Table.ScrollContainer>
        </Card>
      )}
    </>
  )
}
