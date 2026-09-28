import { useState } from 'react'
import { Alert, Code, CopyButton, Button, Group, NumberInput, SegmentedControl, SimpleGrid, Stack, Text, TextInput } from '@mantine/core'
import { IconCheck, IconCopy } from '@tabler/icons-react'

interface Props {
  /** Secret push path, e.g. /api/v1/hikvision/push/hpt_… (shown once). */
  path: string
}

function CopyBlock({ value, label }: { value: string; label: string }) {
  return (
    <Stack gap={4}>
      <Group justify="space-between">
        <Text size="sm" fw={600}>{label}</Text>
        <CopyButton value={value}>
          {({ copied, copy }) => (
            <Button size="compact-xs" variant="light" leftSection={copied ? <IconCheck size={12} /> : <IconCopy size={12} />} onClick={copy}>
              {copied ? 'Copied' : 'Copy'}
            </Button>
          )}
        </CopyButton>
      </Group>
      <Code block style={{ whiteSpace: 'pre-wrap', wordBreak: 'break-all' }}>{value}</Code>
    </Stack>
  )
}

/**
 * Builds the terminal's listening-host configuration (PUT /ISAPI/Event/notification/httpHosts/1) with the
 * field names documented in the ISAPI Developer Guide: id, url, protocolType, parameterFormatType,
 * addressingFormatType, hostName / ipAddress, portNo, httpAuthenticationMethod.
 */
export function PushSetup({ path }: Props) {
  const [protocol, setProtocol] = useState<'HTTPS' | 'HTTP'>(window.location.protocol === 'https:' ? 'HTTPS' : 'HTTP')
  const [host, setHost] = useState(window.location.hostname)
  const [port, setPort] = useState<number | string>(window.location.port ? Number(window.location.port) : window.location.protocol === 'https:' ? 443 : 80)
  const [format, setFormat] = useState<'JSON' | 'XML'>('JSON')

  const isIp = /^\d{1,3}(\.\d{1,3}){3}$/.test(host)
  const url = `${protocol.toLowerCase()}://${host}${port && ![80, 443].includes(Number(port)) ? `:${port}` : ''}${path}`
  const xml = [
    '<?xml version="1.0" encoding="UTF-8"?>',
    '<HttpHostNotification version="2.0" xmlns="http://www.isapi.org/ver20/XMLSchema">',
    '  <id>1</id>',
    `  <url>${path}</url>`,
    `  <protocolType>${protocol}</protocolType>`,
    `  <parameterFormatType>${format}</parameterFormatType>`,
    `  <addressingFormatType>${isIp ? 'ipaddress' : 'hostname'}</addressingFormatType>`,
    isIp ? `  <ipAddress>${host}</ipAddress>` : `  <hostName>${host}</hostName>`,
    `  <portNo>${port}</portNo>`,
    '  <httpAuthenticationMethod>none</httpAuthenticationMethod>',
    '</HttpHostNotification>',
  ].join('\n')
  const curl = `curl --digest -u admin:TERMINAL_PASSWORD -X PUT -H "Content-Type: application/xml" \\\n  --data-binary @listening-host.xml "http://TERMINAL_IP/ISAPI/Event/notification/httpHosts/1"`

  return (
    <Stack gap="md">
      <Alert color="saffron" variant="light">
        The URL contains this device’s secret. It is shown only once; generating a new one invalidates the old URL.
      </Alert>
      <SimpleGrid cols={{ base: 1, sm: 2 }} spacing="sm">
        <div>
          <Text size="sm" fw={500} mb={4}>Terminal connects with</Text>
          <SegmentedControl fullWidth value={protocol} onChange={(v) => { setProtocol(v as 'HTTPS' | 'HTTP'); setPort(v === 'HTTPS' ? 443 : 80) }} data={['HTTPS', 'HTTP']} />
        </div>
        <div>
          <Text size="sm" fw={500} mb={4}>Event format</Text>
          <SegmentedControl fullWidth value={format} onChange={(v) => setFormat(v as 'JSON' | 'XML')} data={['JSON', 'XML']} />
        </div>
        <TextInput label="Server host or IP" value={host} onChange={(e) => setHost(e.currentTarget.value.trim())} />
        <NumberInput label="Port" value={port} onChange={setPort} min={1} max={65535} />
      </SimpleGrid>
      {protocol === 'HTTP' && (
        <Text size="xs" c="dimmed">Over plain HTTP the secret URL and employee data travel unencrypted. Prefer HTTPS if the terminal firmware supports it.</Text>
      )}
      <CopyBlock label="Push URL" value={url} />
      <CopyBlock label="Listening host settings (save as listening-host.xml)" value={xml} />
      <CopyBlock label="Apply it from a computer in the terminal’s network" value={curl} />
      <Text size="xs" c="dimmed">
        Or enter the same values in the terminal’s web page under its HTTP listening / event notification settings. Then scan a face:
        the request appears under <strong>Push log</strong> within seconds.
      </Text>
    </Stack>
  )
}
