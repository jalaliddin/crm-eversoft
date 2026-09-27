import { Suspense } from 'react'
import { AppShell, Burger, Group } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { Outlet } from 'react-router'
import { LoadingState } from '@/components/LoadingState'
import { NotificationBell } from '@/features/notifications/NotificationBell'
import { GlobalSearch } from './GlobalSearch'
import { platformNavigation, tenantNavigation } from './navigation'
import { Sidebar } from './Sidebar'
import { UserMenu } from './UserMenu'

export function AppLayout({ platform = false }: { platform?: boolean }) {
  const [opened, { toggle, close }] = useDisclosure()

  return (
    <AppShell
      header={{ height: 60 }}
      navbar={{ width: 260, breakpoint: 'md', collapsed: { mobile: !opened } }}
      padding={{ base: 'md', md: 'xl' }}
      styles={{
        main: { background: 'var(--app-bg)' },
        navbar: { borderColor: 'var(--app-border)' },
        header: { borderColor: 'var(--app-border)' },
      }}
    >
      <AppShell.Header>
        <Group h="100%" px="md" justify="space-between" wrap="nowrap">
          <Group gap="sm" wrap="nowrap">
            <Burger opened={opened} onClick={toggle} hiddenFrom="md" size="sm" aria-label="Toggle navigation" />
            {!platform && <GlobalSearch />}
          </Group>
          <Group gap="xs" wrap="nowrap">
            <NotificationBell />
            <UserMenu />
          </Group>
        </Group>
      </AppShell.Header>

      <AppShell.Navbar>
        <Sidebar sections={platform ? platformNavigation : tenantNavigation} onNavigate={close} />
      </AppShell.Navbar>

      <AppShell.Main>
        <Suspense fallback={<LoadingState />}>
          <Outlet />
        </Suspense>
      </AppShell.Main>
    </AppShell>
  )
}
