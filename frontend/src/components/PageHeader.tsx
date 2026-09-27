import type { ReactNode } from 'react'
import { Anchor, Breadcrumbs, Group, Stack, Text, Title } from '@mantine/core'
import { Link } from 'react-router'

export interface Crumb {
  label: string
  to?: string
}

interface Props {
  title: ReactNode
  description?: ReactNode
  breadcrumbs?: Crumb[]
  actions?: ReactNode
}

export function PageHeader({ title, description, breadcrumbs, actions }: Props) {
  return (
    <Stack gap={6} mb="lg">
      {breadcrumbs && breadcrumbs.length > 0 && (
        <Breadcrumbs separatorMargin={6} fz="sm">
          {breadcrumbs.map((crumb) =>
            crumb.to ? (
              <Anchor key={crumb.label} component={Link} to={crumb.to} c="dimmed" fz="sm">
                {crumb.label}
              </Anchor>
            ) : (
              <Text key={crumb.label} c="dimmed" fz="sm">
                {crumb.label}
              </Text>
            ),
          )}
        </Breadcrumbs>
      )}
      <Group justify="space-between" align="flex-end" gap="md" wrap="wrap">
        <Stack gap={2} style={{ minWidth: 0 }}>
          <Title order={1} style={{ overflowWrap: 'anywhere' }}>
            {title}
          </Title>
          {description && (
            <Text c="dimmed" size="sm">
              {description}
            </Text>
          )}
        </Stack>
        {actions && (
          <Group gap="xs" wrap="wrap">
            {actions}
          </Group>
        )}
      </Group>
    </Stack>
  )
}
