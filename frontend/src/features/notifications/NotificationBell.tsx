import { ActionIcon, Button, Divider, Group, Indicator, Popover, ScrollArea, Stack, Text, UnstyledButton } from '@mantine/core'
import { IconBell } from '@tabler/icons-react'
import { useQuery } from '@tanstack/react-query'
import { useNavigate } from 'react-router'
import { api } from '@/lib/api'
import { fromNow } from '@/lib/format'
import { useApiMutation } from '@/hooks/useApiMutation'
import type { AppNotification } from '@/types/models'

export function NotificationBell() {
  const navigate = useNavigate()
  const unread = useQuery({ queryKey: ['notifications', 'unread'], queryFn: () => api.get<{ count: number }>('/notifications/unread-count'), refetchInterval: 60_000 })
  const list = useQuery({ queryKey: ['notifications', 'list'], queryFn: () => api.list<AppNotification>('/notifications', { per_page: 15 }), enabled: false })
  const markRead = useApiMutation<null, string>({ mutationFn: (id) => api.post(`/notifications/${id}/read`), invalidate: [['notifications']] })
  const markAll = useApiMutation({ mutationFn: () => api.post('/notifications/read-all'), invalidate: [['notifications']] })

  const count = unread.data?.count ?? 0

  return (
    <Popover width={360} position="bottom-end" shadow="md" onOpen={() => void list.refetch()}>
      <Popover.Target>
        <Indicator label={count > 9 ? '9+' : count} size={16} disabled={count === 0} offset={4} color="red">
          <ActionIcon variant="subtle" color="gray" size="lg" aria-label={`Notifications, ${count} unread`}>
            <IconBell size={20} stroke={1.6} />
          </ActionIcon>
        </Indicator>
      </Popover.Target>
      <Popover.Dropdown p={0}>
        <Group justify="space-between" px="md" py="sm">
          <Text fw={600}>Notifications</Text>
          {count > 0 && (
            <Button size="compact-xs" variant="subtle" onClick={() => markAll.mutate()}>
              Mark all as read
            </Button>
          )}
        </Group>
        <Divider />
        <ScrollArea.Autosize mah={420}>
          {list.data?.data.length ? (
            <Stack gap={0}>
              {list.data.data.map((n) => (
                <UnstyledButton
                  key={n.id}
                  px="md"
                  py="sm"
                  style={{ borderBottom: '1px solid var(--app-border)', background: n.read_at ? undefined : 'var(--mantine-primary-color-light)' }}
                  onClick={() => {
                    if (!n.read_at) markRead.mutate(n.id)
                    if (n.link) navigate(n.link)
                  }}
                >
                  <Text size="sm" fw={n.read_at ? 400 : 600}>
                    {n.title}
                  </Text>
                  <Text size="xs" c="dimmed" lineClamp={2}>
                    {n.body}
                  </Text>
                  <Text size="xs" c="dimmed" mt={2}>
                    {fromNow(n.created_at)}
                  </Text>
                </UnstyledButton>
              ))}
            </Stack>
          ) : (
            <Text c="dimmed" size="sm" ta="center" py="xl">
              {list.isFetching ? 'Loading…' : 'You are all caught up.'}
            </Text>
          )}
        </ScrollArea.Autosize>
      </Popover.Dropdown>
    </Popover>
  )
}
