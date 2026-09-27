import { useState, type ReactNode } from 'react'
import { Card, Group, SegmentedControl, Skeleton, Table, Text, Title } from '@mantine/core'

interface Props {
  title: string
  description?: string
  loading?: boolean
  empty?: boolean
  height?: number
  children: ReactNode
  /** Table view of the same data (accessibility relief for color-only reading). */
  table?: { columns: string[]; rows: (string | number)[][] }
  actions?: ReactNode
  emptyMessage?: string
}

export function ChartCard({ title, description, loading, empty, height = 260, children, table, actions, emptyMessage = 'No data for this period yet.' }: Props) {
  const [view, setView] = useState<'chart' | 'table'>('chart')

  return (
    <Card h="100%">
      <Group justify="space-between" align="flex-start" mb="sm" wrap="nowrap">
        <div>
          <Title order={3} fz="md">
            {title}
          </Title>
          {description && (
            <Text size="xs" c="dimmed">
              {description}
            </Text>
          )}
        </div>
        <Group gap="xs" wrap="nowrap">
          {actions}
          {table && (
            <SegmentedControl
              size="xs"
              value={view}
              onChange={(v) => setView(v as 'chart' | 'table')}
              data={[{ value: 'chart', label: 'Chart' }, { value: 'table', label: 'Table' }]}
              aria-label={`${title}: chart or table view`}
            />
          )}
        </Group>
      </Group>
      {loading ? (
        <Skeleton h={height} />
      ) : empty ? (
        <Text c="dimmed" size="sm" ta="center" py={height / 2 - 12}>
          {emptyMessage}
        </Text>
      ) : view === 'table' && table ? (
        <Table.ScrollContainer minWidth={320} mah={height} type="native">
          <Table striped fz="sm">
            <Table.Thead>
              <Table.Tr>{table.columns.map((c) => <Table.Th key={c}>{c}</Table.Th>)}</Table.Tr>
            </Table.Thead>
            <Table.Tbody>
              {table.rows.map((row, i) => (
                <Table.Tr key={i}>{row.map((cell, j) => <Table.Td key={j} className="tabular">{cell}</Table.Td>)}</Table.Tr>
              ))}
            </Table.Tbody>
          </Table>
        </Table.ScrollContainer>
      ) : (
        children
      )}
    </Card>
  )
}
