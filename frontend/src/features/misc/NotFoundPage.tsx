import { Button, Stack, Text, Title } from '@mantine/core'
import { Link } from 'react-router'

export default function NotFoundPage() {
  return (
    <Stack align="center" ta="center" py={80} gap="sm">
      <Title order={2}>This page does not exist</Title>
      <Text c="dimmed">The link may be outdated, or the record was removed.</Text>
      <Button component={Link} to="/" variant="light">Go to dashboard</Button>
    </Stack>
  )
}
