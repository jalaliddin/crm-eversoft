import type { ReactNode } from 'react'
import { Group, Paper, Skeleton, Text, UnstyledButton } from '@mantine/core'
import { Link } from 'react-router'

interface Props {
  label: string
  value: ReactNode
  hint?: ReactNode
  icon?: ReactNode
  tone?: string
  to?: string
  loading?: boolean
  /** Smaller figure for long values such as money. */
  compact?: boolean
}

/** Figures read first: large tabular number, plain label, optional hint. */
export function StatCard({ label, value, hint, icon, tone, to, loading, compact = false }: Props) {
  const body = (
    <Paper withBorder p="md" radius="lg" h="100%" style={{ borderColor: 'var(--app-border)' }}>
      <Group justify="space-between" align="flex-start" wrap="nowrap" gap="xs">
        <Text size="sm" c="dimmed" fw={500}>
          {label}
        </Text>
        {icon && <Text c={tone ?? 'dimmed'} lh={0}>{icon}</Text>}
      </Group>
      {loading ? (
        <Skeleton h={30} w="60%" mt={8} />
      ) : (
        <Text fz={compact ? 20 : 28} fw={650} lh={1.2} mt={6} className="tabular" c={tone ? `${tone}.7` : undefined} style={compact ? { whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' } : undefined}>
          {value}
        </Text>
      )}
      {hint && (
        <Text size="xs" c="dimmed" mt={4}>
          {hint}
        </Text>
      )}
    </Paper>
  )

  return to ? (
    <UnstyledButton component={Link} to={to} h="100%" display="block" aria-label={label}>
      {body}
    </UnstyledButton>
  ) : (
    body
  )
}
