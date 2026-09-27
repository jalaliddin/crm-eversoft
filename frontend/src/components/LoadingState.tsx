import { Center, Loader, Skeleton, Stack } from '@mantine/core'

export function LoadingState({ fullscreen = false, rows = 0 }: { fullscreen?: boolean; rows?: number }) {
  if (rows > 0) {
    return (
      <Stack gap="xs" aria-busy="true" aria-label="Loading">
        {Array.from({ length: rows }, (_, i) => (
          <Skeleton key={i} height={38} radius="sm" />
        ))}
      </Stack>
    )
  }

  return (
    <Center h={fullscreen ? '100vh' : 240} aria-busy="true" aria-label="Loading">
      <Loader size="md" />
    </Center>
  )
}
