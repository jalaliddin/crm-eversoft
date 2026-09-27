import { Button, Stack, Text, ThemeIcon, Title } from '@mantine/core'
import { IconAlertTriangle } from '@tabler/icons-react'
import { ApiError } from '@/lib/api'

interface Props {
  title?: string
  message?: string
  error?: unknown
  onRetry?: () => void
}

export function ErrorState({ title, message, error, onRetry }: Props) {
  const status = error instanceof ApiError ? error.status : undefined
  const resolvedTitle = title ?? (status === 404 ? 'Not found' : status === 403 ? 'No access' : 'Could not load this page')
  const resolvedMessage =
    message ??
    (status === 404
      ? 'It may have been deleted, or it belongs to another organization.'
      : error instanceof Error
        ? error.message
        : 'The server did not respond as expected.')

  return (
    <Stack align="center" gap="xs" py={48} ta="center" role="alert">
      <ThemeIcon variant="light" color="red" size={48} radius="xl">
        <IconAlertTriangle size={24} stroke={1.6} />
      </ThemeIcon>
      <Title order={4}>{resolvedTitle}</Title>
      <Text c="dimmed" size="sm" maw={440}>
        {resolvedMessage}
      </Text>
      {onRetry && status !== 404 && status !== 403 && (
        <Button variant="light" onClick={onRetry} mt="xs">
          Try again
        </Button>
      )}
    </Stack>
  )
}
