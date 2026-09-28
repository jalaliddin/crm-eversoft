import { useEffect, useState } from 'react'
import {
  ActionIcon, Alert, Badge, Button, Card, Code, CopyButton, Drawer, Group, Menu, SegmentedControl, Select, SimpleGrid, Stack, Tabs, Text, Tooltip,
} from '@mantine/core'
import { IconCheck, IconCopy, IconDots, IconPlugConnected, IconPlus, IconRefresh } from '@tabler/icons-react'
import { useForm, useWatch } from 'react-hook-form'
import { useQuery } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { applyServerErrors } from '@/lib/forms'
import { fmtDateTime, fmtTime, fromNow } from '@/lib/format'
import { notifyError, notifySuccess } from '@/lib/notify'
import { PageHeader } from '@/components/PageHeader'
import { DataTable } from '@/components/DataTable'
import { StatusBadge } from '@/components/StatusBadge'
import { EntityDrawer } from '@/components/EntityDrawer'
import { EntityModal } from '@/components/EntityModal'
import { FormField } from '@/components/FormField'
import { EmptyState } from '@/components/EmptyState'
import { LoadingState } from '@/components/LoadingState'
import { confirmAction } from '@/components/ConfirmDialog'
import { useApiMutation } from '@/hooks/useApiMutation'
import { useDevices, useEmployeesLookup, toOptions } from '@/hooks/useLookups'
import { useListParams } from '@/hooks/useListParams'
import { useAuth } from '@/features/auth/useAuth'
import type { AttendanceDevice, DevicePushLog, RawEvent } from '@/types/models'
import { PushSetup } from './PushSetup'
import { TerminalBridgeStatus } from './TerminalBridge'
import { extensionRequest, requestTerminalSync, terminalOrigin, useTerminalBridge } from './terminalBridge'

interface DeviceValues {
  name: string
  vendor: string
  model: string
  type: string
  connection_mode: string
  ip_address: string
  port: number | null
  protocol: string
  username: string
  password: string
  status: string
  settings: { timezone: string | null }
}

function DeviceForm({ device, opened, onClose }: { device?: AttendanceDevice | null; opened: boolean; onClose: () => void }) {
  const form = useForm<DeviceValues>()
  const mode = useWatch({ control: form.control, name: 'connection_mode' })
  const vendor = useWatch({ control: form.control, name: 'vendor' })

  useEffect(() => {
    if (!opened) return
    form.reset({
      name: device?.name ?? '', vendor: device?.vendor ?? 'hikvision', model: device?.model ?? 'DS-K1A340FWX', type: device?.type ?? 'face_terminal',
      connection_mode: device?.connection_mode ?? 'direct', ip_address: device?.ip_address ?? '', port: device?.port ?? 80, protocol: device?.protocol ?? 'http',
      username: device?.username ?? 'admin', password: '', status: device?.status ?? 'active', settings: { timezone: device?.settings.timezone ?? null },
    })
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [opened, device])

  const save = useApiMutation<AttendanceDevice, DeviceValues>({
    mutationFn: (v) => {
      const payload = { ...v, password: v.password || undefined, settings: { timezone: v.settings.timezone || null } }
      return device ? api.put(`/attendance-devices/${device.id}`, payload) : api.post('/attendance-devices', payload)
    },
    invalidate: [['attendance-devices']],
    successMessage: device ? 'Device saved.' : 'Device added. Run “Test connection” next.',
    onSuccess: onClose,
    silentError: true,
  })

  const direct = mode === 'direct' && vendor === 'hikvision'

  return (
    <EntityDrawer opened={opened} onClose={onClose} title={device ? `Edit ${device.name}` : 'Add attendance device'} formId="device-form" submitting={save.isPending}>
      <form id="device-form" onSubmit={form.handleSubmit((v) => save.mutate(v, { onError: (e) => applyServerErrors(e, form.setError) }))}>
        <FormField control={form.control} name="name" label="Name" required placeholder="Main entrance" autoFocus />
        <SimpleGrid cols={2} spacing="sm" mt="sm">
          <FormField control={form.control} name="vendor" label="Vendor" kind="select" data={[{ value: 'hikvision', label: 'Hikvision' }, { value: 'fake', label: 'Simulator (testing)' }]} />
          <FormField control={form.control} name="model" label="Model" />
          <FormField control={form.control} name="type" label="Type" kind="select" data={[{ value: 'face_terminal', label: 'Face terminal' }, { value: 'fingerprint', label: 'Fingerprint' }, { value: 'card_reader', label: 'Card reader' }]} />
          <FormField control={form.control} name="status" label="Status" kind="select" data={[{ value: 'active', label: 'Active' }, { value: 'inactive', label: 'Paused' }]} />
        </SimpleGrid>
        <Text size="sm" fw={500} mt="md" mb={4}>How does the server reach it?</Text>
        <SegmentedControl
          fullWidth
          value={mode}
          onChange={(v) => form.setValue('connection_mode', v)}
          data={[
            { value: 'direct', label: 'Server connects' },
            { value: 'push', label: 'Terminal pushes' },
            { value: 'bridge', label: 'Bridge agent' },
            { value: 'browser', label: 'Office browser' },
          ]}
        />
        {mode === 'browser' ? (
          <>
            <Alert mt="sm" variant="light">
              A CRM page open in a browser on the office network reads the terminal every minute and sends new punches here. That browser needs
              the Eversoft Terminal Bridge extension, which keeps the terminal password; the CRM never stores it. Punches are read only while the
              CRM is open somewhere in the office. Missed time is caught up automatically, up to 7 days back.
            </Alert>
            <SimpleGrid cols={{ base: 1, sm: 3 }} spacing="sm" mt="sm">
              <FormField control={form.control} name="protocol" label="Protocol" kind="select" data={['http', 'https']} />
              <FormField control={form.control} name="ip_address" label="Terminal address on the office network" required placeholder="192.168.1.64" />
              <FormField control={form.control} name="port" label="Port" kind="number" min={1} max={65535} />
              <FormField control={form.control} name="settings.timezone" label="Device timezone" placeholder="Organization timezone" />
            </SimpleGrid>
          </>
        ) : mode === 'push' ? (
          <Alert mt="sm" variant="light">
            The terminal sends each event to the CRM itself (its HTTP listening host). No inbound access to the terminal is needed.
            After saving, choose <strong>Generate push URL</strong> in the device menu and enter it on the terminal. Missed events during an
            outage are not re-sent, and the CRM cannot send commands to the terminal in this mode.
          </Alert>
        ) : mode === 'bridge' ? (
          <Alert mt="sm" variant="light">Use this when the terminal is on a local network the server cannot reach. After saving, generate a bridge token and configure the hik-bridge agent on-site.</Alert>
        ) : direct && (
          <SimpleGrid cols={{ base: 1, sm: 3 }} spacing="sm" mt="sm">
            <FormField control={form.control} name="protocol" label="Protocol" kind="select" data={['http', 'https']} />
            <FormField control={form.control} name="ip_address" label="IP address or host" required placeholder="192.168.1.64" />
            <FormField control={form.control} name="port" label="Port" kind="number" min={1} max={65535} />
            <FormField control={form.control} name="username" label="Username" />
            <FormField control={form.control} name="password" label="Password" kind="password" description={device?.has_password ? 'Stored encrypted. Leave empty to keep it.' : undefined} />
            <FormField control={form.control} name="settings.timezone" label="Device timezone" placeholder="Organization timezone" />
          </SimpleGrid>
        )}
      </form>
    </EntityDrawer>
  )
}

interface IssuedToken {
  token: string
  push_path: string | null
}

function DeviceCard({ device, onEdit, onToken }: { device: AttendanceDevice; onEdit: () => void; onToken: (issued: IssuedToken) => void }) {
  const { can } = useAuth()
  const bridge = useTerminalBridge()
  // Office-browser devices are tested and read through the extension when this browser has it.
  const viaBrowser = device.connection_mode === 'browser' && bridge.extension === 'ready'
  const invalidate = [['attendance-devices'], ['attendance'], ['raw-events']]
  const test = useApiMutation<{ success: boolean; message: string; latency_ms: number | null; info: Record<string, string> }>({
    mutationFn: async () => {
      if (!viaBrowser) return api.post(`/attendance-devices/${device.id}/test`)
      const started = performance.now()
      const info = await extensionRequest<{ model: string | null; serialNumber: string | null }>('deviceInfo', { origin: terminalOrigin(device) }, 20_000)
      return { success: true, message: null, data: { success: true, message: 'Connected from this browser.', latency_ms: Math.round(performance.now() - started), info: { model: info.model ?? '' } } }
    },
    invalidate: [['attendance-devices']],
    successMessage: (r) => (r.success ? `Connected${r.latency_ms ? ` in ${r.latency_ms} ms` : ''}${r.info.model ? ` · ${r.info.model}` : ''}.` : ''),
    onSuccess: (r) => { if (!r.success) notifyError(new Error(r.message)) },
  })
  const sync = useApiMutation<{ success: boolean; message: string; stored?: number; duplicates?: number; unmatched?: number }>({
    mutationFn: () => api.post(`/attendance-devices/${device.id}/sync`),
    invalidate,
    successMessage: (r) => (r.success ? `Synchronized: ${r.stored ?? 0} new, ${r.duplicates ?? 0} already stored, ${r.unmatched ?? 0} unmatched.` : ''),
    onSuccess: (r) => { if (!r.success) notifyError(new Error(r.message)) },
  })
  const push = useApiMutation({ mutationFn: () => api.post(`/attendance-devices/${device.id}/push-employees`), invalidate: [] })
  const token = useApiMutation<IssuedToken>({ mutationFn: () => api.post(`/attendance-devices/${device.id}/bridge-token`), invalidate: [['attendance-devices']], onSuccess: onToken })
  const remove = useApiMutation({ mutationFn: () => api.delete(`/attendance-devices/${device.id}`), invalidate: [['attendance-devices']], successMessage: 'Device removed.' })

  const healthy = device.last_sync_status === 'ok'

  return (
    <Card>
      <Group justify="space-between" align="flex-start" wrap="nowrap">
        <div>
          <Group gap="xs">
            <Text fw={650}>{device.name}</Text>
            <StatusBadge status={device.status} />
            {device.vendor === 'fake' && <Badge color="gray">Simulator</Badge>}
          </Group>
          <Text size="sm" c="dimmed">
            {[
              device.model,
              device.connection_mode === 'bridge' ? 'via bridge'
                : device.connection_mode === 'push' ? 'terminal pushes events'
                  : device.connection_mode === 'browser' ? `via office browser · ${terminalOrigin(device)}`
                    : device.ip_address ? `${device.protocol}://${device.ip_address}:${device.port ?? 80}` : null,
              device.serial_number,
            ].filter(Boolean).join(' · ')}
          </Text>
        </div>
        {can('devices.manage') && (
          <Menu position="bottom-end">
            <Menu.Target><ActionIcon variant="subtle" color="gray" aria-label={`Actions for ${device.name}`}><IconDots size={18} /></ActionIcon></Menu.Target>
            <Menu.Dropdown>
              <Menu.Item onClick={onEdit}>Edit</Menu.Item>
              {device.connection_mode === 'direct' && device.vendor === 'hikvision' && <Menu.Item onClick={() => push.mutate(undefined, { onSuccess: () => notifySuccess('Employees pushed to the terminal.') })}>Push employees to device</Menu.Item>}
              {device.connection_mode === 'push' && (
                <Menu.Item onClick={() => (device.has_bridge_token
                  ? confirmAction({ title: 'Generate a new push URL?', message: 'The current URL stops working immediately; update it on the terminal.', confirmLabel: 'Generate', onConfirm: () => token.mutate() })
                  : token.mutate())}>
                  {device.has_bridge_token ? 'Regenerate push URL' : 'Generate push URL'}
                </Menu.Item>
              )}
              {device.connection_mode === 'bridge' && <Menu.Item onClick={() => confirmAction({ title: 'Issue a new bridge token?', message: 'The current token stops working immediately.', confirmLabel: 'Issue token', onConfirm: () => token.mutate() })}>{device.has_bridge_token ? 'Rotate bridge token' : 'Generate bridge token'}</Menu.Item>}
              <Menu.Divider />
              <Menu.Item color="red" onClick={() => confirmAction({ title: `Remove ${device.name}?`, message: 'Stored punches and attendance are kept.', confirmLabel: 'Remove', danger: true, onConfirm: () => remove.mutate() })}>Remove</Menu.Item>
            </Menu.Dropdown>
          </Menu>
        )}
      </Group>

      <SimpleGrid cols={3} mt="md" spacing="xs">
        <div><Text size="xs" c="dimmed">Last sync</Text><Text size="sm">{device.last_sync_at ? fromNow(device.last_sync_at) : 'Never'}</Text></div>
        <div><Text size="xs" c="dimmed">Latest punch</Text><Text size="sm">{device.last_event_at ? fmtDateTime(device.last_event_at) : '—'}</Text></div>
        <div><Text size="xs" c="dimmed">Unmatched</Text><Text size="sm" c={device.unmatched_events_count ? 'saffron.7' : undefined} fw={device.unmatched_events_count ? 600 : undefined}>{device.unmatched_events_count ?? 0} punches</Text></div>
      </SimpleGrid>

      {device.last_sync_status === 'failed' && device.last_error && <Alert mt="md" color="red" variant="light" title="Last sync failed">{device.last_error}</Alert>}

      {device.connection_mode === 'browser' && device.status === 'active' && <TerminalBridgeStatus device={device} />}

      {can('devices.sync') && (
        <Group mt="md" gap="xs">
          <Button size="xs" variant="default" leftSection={<IconPlugConnected size={14} />} loading={test.isPending} onClick={() => test.mutate()}>Test connection</Button>
          <Button
            size="xs"
            leftSection={<IconRefresh size={14} />}
            loading={viaBrowser ? bridge.runs[device.id]?.status === 'syncing' : sync.isPending}
            onClick={() => (viaBrowser ? requestTerminalSync(device.id) : sync.mutate())}
            disabled={device.status !== 'active'}
          >
            Sync now
          </Button>
          {healthy && <Tooltip label="The last synchronization succeeded"><Badge color="teal" variant="dot">Healthy</Badge></Tooltip>}
        </Group>
      )}
    </Card>
  )
}

function UnmatchedTab() {
  const { can } = useAuth()
  const { data: employees = [] } = useEmployeesLookup()
  const { data: devices } = useDevices()
  const { data = [], isLoading } = useQuery({
    queryKey: ['raw-events', 'unmatched'],
    queryFn: () => api.get<{ device_id: number; employee_identifier: string | null; card_number: string | null; events: number; last_seen: string }[]>('/attendance-raw-events/unmatched'),
  })
  const [choice, setChoice] = useState<Record<string, string | null>>({})
  const reprocess = useApiMutation<{ processed: number; unmatched: number }>({
    mutationFn: () => api.post('/attendance-raw-events/reprocess'),
    invalidate: [['raw-events'], ['attendance'], ['attendance-devices']],
  })
  const assign = async (identifier: string, employeeId: string) => {
    try {
      await api.post(`/employees/${employeeId}/identifiers`, { identifier })
      notifySuccess(`ID ${identifier} mapped. Reprocessing…`)
      reprocess.mutate()
    } catch (e) {
      notifyError(e)
    }
  }

  if (isLoading) return <LoadingState />

  return (
    <Stack gap="md">
      <Group justify="space-between">
        <Text size="sm" c="dimmed" maw={680}>Punches from terminal IDs that no employee has. They are kept safely; map the ID to an employee and they are applied to attendance.</Text>
        {can('devices.sync') && <Button size="xs" variant="default" leftSection={<IconRefresh size={14} />} loading={reprocess.isPending} onClick={() => reprocess.mutate()}>Reprocess all</Button>}
      </Group>
      {data.length === 0 ? <EmptyState title="Every punch is matched" icon={<IconCheck size={24} />} /> : (
        <DataTable
          rows={data}
          rowKey={(r) => `${r.device_id}-${r.employee_identifier}-${r.card_number}`}
          columns={[
            { key: 'identifier', header: 'Terminal ID', render: (r) => <Code>{r.employee_identifier ?? `card ${r.card_number}`}</Code> },
            { key: 'device', header: 'Device', render: (r) => devices?.find((d) => d.id === r.device_id)?.name ?? '—' },
            { key: 'events', header: 'Punches', align: 'right', render: (r) => <span className="tabular">{r.events}</span> },
            { key: 'last_seen', header: 'Last seen', render: (r) => fmtDateTime(r.last_seen.replace(' ', 'T') + 'Z') },
            {
              key: 'assign',
              header: 'Belongs to',
              render: (r) => {
                const k = `${r.device_id}-${r.employee_identifier}`
                return r.employee_identifier && can('devices.manage') ? (
                  <Group gap="xs" wrap="nowrap">
                    <Select size="xs" placeholder="Choose employee" data={employees} value={choice[k] ?? null} onChange={(v) => setChoice({ ...choice, [k]: v })} searchable w={220} aria-label={`Employee for ID ${r.employee_identifier}`} />
                    <Button size="xs" disabled={!choice[k]} onClick={() => choice[k] && void assign(r.employee_identifier!, choice[k]!)}>Map</Button>
                  </Group>
                ) : '—'
              },
            },
          ]}
          minWidth={760}
        />
      )}
    </Stack>
  )
}

function RawEventsTab() {
  const list = useListParams({ per_page: '50' })
  const { data: devices } = useDevices()
  const { data, isLoading, error } = useQuery({ queryKey: ['raw-events', list.query], queryFn: () => api.list<RawEvent>('/attendance-raw-events', list.query), placeholderData: (p) => p })
  return (
    <>
      <Group mb="md" gap="sm">
        <Select placeholder="Any status" data={['processed', 'unmatched', 'pending', 'failed'].map((v) => ({ value: v, label: v }))} value={list.params.status ?? null} onChange={(v) => list.set({ status: v })} clearable w={160} aria-label="Status" />
        <Select placeholder="All devices" data={toOptions(devices)} value={list.params.device_id ?? null} onChange={(v) => list.set({ device_id: v })} clearable w={200} aria-label="Device" />
      </Group>
      <DataTable
        rows={data?.data}
        meta={data?.meta}
        loading={isLoading}
        error={error}
        {...list.tableProps}
        columns={[
          { key: 'time', header: 'Time', render: (e) => <span className="tabular">{fmtDateTime(e.event_time)}</span> },
          { key: 'device', header: 'Device', render: (e) => e.device?.name ?? '—' },
          { key: 'identifier', header: 'Terminal ID', render: (e) => <Code>{e.employee_identifier ?? e.card_number ?? '—'}</Code> },
          { key: 'name', header: 'Name on device', visibleFrom: 'lg', render: (e) => e.name_on_device ?? '—' },
          { key: 'employee', header: 'Employee', render: (e) => e.employee?.full_name ?? '—' },
          { key: 'type', header: 'Type', visibleFrom: 'md', render: (e) => e.event_type.replace('_', ' ') },
          { key: 'status', header: 'Status', render: (e) => <StatusBadge status={e.processing_status} /> },
          { key: 'at', header: 'Local', visibleFrom: 'xl', render: (e) => fmtTime(e.event_time) },
        ]}
        empty={{ title: 'No punches received yet' }}
      />
    </>
  )
}

function PushLogTab() {
  const list = useListParams({ per_page: '50' })
  const { data: devices } = useDevices()
  const [open, setOpen] = useState<DevicePushLog | null>(null)
  const { data, isLoading, error } = useQuery({
    queryKey: ['push-logs', list.query],
    queryFn: () => api.list<DevicePushLog>('/attendance-devices/push-logs', list.query),
    placeholderData: (p) => p,
    refetchInterval: 15_000,
  })
  const pushDevices = (devices ?? []).filter((d) => d.connection_mode === 'push')

  return (
    <>
      <Group mb="md" gap="sm" justify="space-between">
        <Group gap="sm">
          <Select placeholder="All push devices" data={toOptions(pushDevices)} value={list.params.device_id ?? null} onChange={(v) => list.set({ device_id: v })} clearable w={220} aria-label="Device" />
          <Select placeholder="Any result" data={['accepted', 'heartbeat', 'ignored', 'unparsed', 'rejected', 'failed']} value={list.params.status ?? null} onChange={(v) => list.set({ status: v })} clearable w={160} aria-label="Result" />
        </Group>
        <Text size="xs" c="dimmed">Every request a terminal sends, kept 14 days. Pictures are never stored.</Text>
      </Group>
      <DataTable
        rows={data?.data}
        meta={data?.meta}
        loading={isLoading}
        error={error}
        {...list.tableProps}
        onRowClick={setOpen}
        columns={[
          { key: 'created_at', header: 'Received', render: (l) => <span className="tabular">{fmtDateTime(l.created_at)}</span> },
          { key: 'device', header: 'Device', render: (l) => l.device?.name ?? '—' },
          { key: 'status', header: 'Result', render: (l) => <StatusBadge status={l.status} /> },
          { key: 'event_type', header: 'Event type', visibleFrom: 'md', render: (l) => l.event_type ?? '—' },
          { key: 'summary', header: 'Details', render: (l) => <Text size="sm" lineClamp={1}>{l.error ?? l.summary ?? '—'}</Text> },
          { key: 'stored', header: 'New punches', align: 'right', visibleFrom: 'sm', render: (l) => <span className="tabular">{l.stored_count}/{l.events_count}</span> },
          { key: 'ip', header: 'From', visibleFrom: 'lg', render: (l) => l.remote_ip ?? '—' },
        ]}
        empty={{ title: 'Nothing received yet', message: 'After the push URL is set on the terminal, each scan and heartbeat shows up here.' }}
      />
      <Drawer opened={!!open} onClose={() => setOpen(null)} title="Push request" size="lg">
        {open && (
          <Stack gap="sm">
            <Group gap="xs"><StatusBadge status={open.status} /><Text size="sm">{fmtDateTime(open.created_at)} · {open.remote_ip}</Text></Group>
            <Text size="sm"><strong>Content type:</strong> {open.content_type ?? '—'}</Text>
            {open.error && <Alert color="red" variant="light">{open.error}</Alert>}
            {open.summary && <Text size="sm"><strong>Parsed:</strong> {open.summary}</Text>}
            <Text size="sm" fw={600}>Body (text parts, truncated)</Text>
            <Code block style={{ whiteSpace: 'pre-wrap', wordBreak: 'break-all', maxHeight: 480, overflow: 'auto' }}>{open.body_excerpt ?? '(empty)'}</Code>
          </Stack>
        )}
      </Drawer>
    </>
  )
}

export default function DevicesPage() {
  const { can } = useAuth()
  const { data, isLoading } = useDevices()
  const [editing, setEditing] = useState<AttendanceDevice | null | undefined>(undefined)
  const [issued, setIssued] = useState<IssuedToken | null>(null)

  return (
    <>
      <PageHeader
        title="Attendance devices"
        description="Terminals that send check-ins. Devices are synchronized automatically every 5 minutes."
        actions={can('devices.manage') && <Button leftSection={<IconPlus size={16} />} onClick={() => setEditing(null)}>Add device</Button>}
      />
      <Tabs defaultValue="devices" keepMounted={false}>
        <Tabs.List mb="lg">
          <Tabs.Tab value="devices">Devices</Tabs.Tab>
          <Tabs.Tab value="unmatched">Unmatched punches</Tabs.Tab>
          <Tabs.Tab value="events">Punch log</Tabs.Tab>
          <Tabs.Tab value="pushes">Push log</Tabs.Tab>
        </Tabs.List>
        <Tabs.Panel value="devices">
          {isLoading ? <LoadingState /> : !data?.length ? (
            <EmptyState title="No devices yet" message="Add your Hikvision terminal. If the server cannot reach it, let the terminal push events, or read it from an office browser."action={can('devices.manage') && <Button onClick={() => setEditing(null)}>Add device</Button>} />
          ) : (
            <SimpleGrid cols={{ base: 1, lg: 2 }} spacing="md">
              {data.map((d) => <DeviceCard key={d.id} device={d} onEdit={() => setEditing(d)} onToken={setIssued} />)}
            </SimpleGrid>
          )}
        </Tabs.Panel>
        <Tabs.Panel value="unmatched"><UnmatchedTab /></Tabs.Panel>
        <Tabs.Panel value="events"><RawEventsTab /></Tabs.Panel>
        <Tabs.Panel value="pushes"><PushLogTab /></Tabs.Panel>
      </Tabs>

      <DeviceForm opened={editing !== undefined} onClose={() => setEditing(undefined)} device={editing} />

      <EntityModal opened={!!issued} onClose={() => setIssued(null)} title={issued?.push_path ? 'Push URL for the terminal' : 'Bridge token'} size="lg">
        {issued?.push_path ? (
          <PushSetup path={issued.push_path} />
        ) : (
          <Stack gap="sm">
            <Alert color="saffron" variant="light">Copy it now — it is shown only once. Put it in the bridge agent’s <Code>BRIDGE_TOKEN</Code> setting.</Alert>
            <Group wrap="nowrap" gap="xs">
              <Code block style={{ flex: 1, wordBreak: 'break-all' }}>{issued?.token}</Code>
              <CopyButton value={issued?.token ?? ''}>
                {({ copied, copy }) => <ActionIcon variant="light" onClick={copy} aria-label="Copy token">{copied ? <IconCheck size={16} /> : <IconCopy size={16} />}</ActionIcon>}
              </CopyButton>
            </Group>
          </Stack>
        )}
      </EntityModal>
    </>
  )
}
