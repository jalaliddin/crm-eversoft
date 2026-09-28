import { useEffect } from 'react'
import { Alert, Anchor, Badge, Button, Group, List, Text } from '@mantine/core'
import { IconDownload, IconSettings } from '@tabler/icons-react'
import { useQueryClient } from '@tanstack/react-query'
import { fromNow } from '@/lib/format'
import { notifyError } from '@/lib/notify'
import { useAuth } from '@/features/auth/useAuth'
import type { AttendanceDevice } from '@/types/models'
import { EXTENSION_DOWNLOAD, extensionRequest, startTerminalBridge, terminalOrigin, useTerminalBridge } from './terminalBridge'

/** Mounted once in the app layout: reads office-browser terminals in the background while the CRM is open. */
export function TerminalBridgeRunner() {
  const { me, can } = useAuth()
  const queryClient = useQueryClient()
  const timezone = me?.organization?.timezone
  const enabled = !!timezone && can('devices.sync')

  useEffect(() => (enabled && timezone ? startTerminalBridge({ queryClient, timezone }) : undefined), [enabled, timezone, queryClient])

  return null
}

function InstallSteps() {
  return (
    <List size="sm" type="ordered" spacing={2} mt={6}>
      <List.Item><Anchor href={EXTENSION_DOWNLOAD} download>Download the extension</Anchor> and unzip it into a folder that stays on this computer.</List.Item>
      <List.Item>Open <Text span ff="monospace" size="sm">chrome://extensions</Text> (or <Text span ff="monospace" size="sm">edge://extensions</Text>), turn on <strong>Developer mode</strong>, choose <strong>Load unpacked</strong> and select the folder.</List.Item>
      <List.Item>Reload this page.</List.Item>
    </List>
  )
}

/** What this browser is doing for a device in "Office browser" mode. */
export function TerminalBridgeStatus({ device }: { device: AttendanceDevice }) {
  const bridge = useTerminalBridge()
  const run = bridge.runs[device.id]
  const origin = terminalOrigin(device)

  const openSetup = () => extensionRequest('openSetup', { origin }).catch(notifyError)

  // Not running here (no sync permission, or still detecting the extension).
  if (bridge.extension === 'checking' || (bridge.role === 'off' && bridge.extension !== 'missing')) return null

  if (bridge.extension === 'missing') {
    return (
      <Alert mt="md" variant="light" title="This browser cannot read the terminal yet" icon={<IconDownload size={18} />}>
        <Text size="sm">On a computer in the office network, install the Eversoft Terminal Bridge extension (Chrome or Edge). While the CRM is open there, punches are read every minute.</Text>
        <InstallSteps />
      </Alert>
    )
  }

  // No finished run yet (first read in progress, or not started).
  if (!run || (run.status === 'syncing' && run.received === undefined && !run.message)) {
    return <Text size="sm" c="dimmed" mt="md">{run ? 'Reading the terminal from this browser…' : 'This browser reads the terminal every minute while the CRM is open.'}</Text>
  }

  switch (run.status) {
    case 'needs_setup':
      return (
        <Alert mt="md" variant="light" color="saffron" title="Allow this browser to read the terminal">
          <Text size="sm">Enter the terminal password in the extension. It stays in this browser and is never sent to the CRM.</Text>
          <Button mt="sm" size="xs" leftSection={<IconSettings size={14} />} onClick={openSetup}>Set up {origin}</Button>
        </Alert>
      )
    case 'unreachable':
      return (
        <Alert mt="md" variant="light" color="saffron" title="Terminal not reachable from this browser">
          <Text size="sm">{run.message} If this computer is not on the office network, that is expected: another office browser can read it.</Text>
        </Alert>
      )
    case 'error':
      // Already shown as the device's "Last sync failed" message.
      if (run.message && device.last_error?.includes(run.message)) {
        return <Button mt="sm" size="xs" variant="default" leftSection={<IconSettings size={14} />} onClick={openSetup}>Check terminal settings</Button>
      }
      return (
        <Alert mt="md" variant="light" color="red" title="This browser could not read the terminal">
          <Text size="sm">{run.message}</Text>
          <Button mt="sm" size="xs" variant="default" leftSection={<IconSettings size={14} />} onClick={openSetup}>Check terminal settings</Button>
        </Alert>
      )
    default:
      return (
        <Group mt="md" gap="xs">
          <Badge color="teal" variant="dot">{bridge.role === 'leader' ? 'Read by this browser' : 'Read by another tab of this browser'}</Badge>
          <Text size="sm" c="dimmed">
            {run.status === 'syncing' ? 'Reading now…' : `${fromNow(run.at)}: ${run.received ?? 0} punches read, ${run.stored ?? 0} new.`}
          </Text>
        </Group>
      )
  }
}
