import type { ReactNode } from 'react'
import { SimpleGrid, Text } from '@mantine/core'

/** Label/value pairs for detail pages, as a semantic description list. */
export function DetailList({ items, cols = 2 }: { items: { label: string; value: ReactNode }[]; cols?: number }) {
  return (
    <SimpleGrid component="dl" cols={{ base: 1, sm: cols }} spacing="md" verticalSpacing="sm" m={0}>
      {items.map((item) => (
        <div key={item.label}>
          <Text component="dt" size="xs" c="dimmed" fw={500}>
            {item.label}
          </Text>
          <Text component="dd" size="sm" m={0} style={{ overflowWrap: 'anywhere' }}>
            {item.value ?? '—'}
          </Text>
        </div>
      ))}
    </SimpleGrid>
  )
}
