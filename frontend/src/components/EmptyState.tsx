import type { ReactNode } from 'react'
import { Stack, Text, ThemeIcon, Title } from '@mantine/core'
import { IconInbox } from '@tabler/icons-react'

interface Props {
  title: string
  message?: string
  icon?: ReactNode
  action?: ReactNode
  compact?: boolean
}

export function EmptyState({ title, message, icon, action, compact = false }: Props) {
  return (
    <Stack align="center" gap="xs" py={compact ? 'lg' : 48} px="md" ta="center">
      <ThemeIcon variant="light" size={compact ? 36 : 48} radius="xl" color="gray">
        {icon ?? <IconInbox size={compact ? 18 : 24} stroke={1.6} />}
      </ThemeIcon>
      <Title order={4} fw={600}>
        {title}
      </Title>
      {message && (
        <Text c="dimmed" size="sm" maw={420}>
          {message}
        </Text>
      )}
      {action}
    </Stack>
  )
}
