import { Avatar, Badge, Box, Group, NavLink, ScrollArea, Stack, Text } from '@mantine/core'
import { Link, useLocation } from 'react-router'
import { useTranslation } from 'react-i18next'
import { useAuth } from '@/features/auth/useAuth'
import type { NavSection } from './navigation'

interface Props {
  sections: NavSection[]
  onNavigate?: () => void
}

export function Sidebar({ sections, onNavigate }: Props) {
  const { t } = useTranslation()
  const { can, me } = useAuth()
  const { pathname } = useLocation()
  const org = me?.organization

  // Longest matching prefix wins, so "/hr/attendance/monitor" does not also light up "/hr/attendance".
  const allTargets = sections.flatMap((s) => s.items.map((i) => i.to))
  const activeTarget = allTargets
    .filter((to) => pathname === to || pathname.startsWith(`${to}/`))
    .sort((a, b) => b.length - a.length)[0]

  return (
    <Stack h="100%" gap={0}>
      <Group gap="sm" px="md" py="md" wrap="nowrap" style={{ borderBottom: '1px solid var(--app-border)' }}>
        <Avatar src={org?.logo_url} color="khiva" variant="filled" radius="md" size={34}>
          {(org?.name ?? 'Eversoft').slice(0, 1)}
        </Avatar>
        <Box style={{ minWidth: 0 }}>
          <Text fw={650} size="sm" truncate>
            {org?.name ?? 'Eversoft platform'}
          </Text>
          <Text size="xs" c="dimmed" truncate>
            Eversoft CRM
          </Text>
        </Box>
      </Group>

      <ScrollArea flex={1} px="xs" py="sm" type="scroll">
        <Stack gap="md">
          {sections.map((section) => {
            const items = section.items.filter((item) => item.soon || !item.permission || can(item.permission))
            if (items.length === 0 || (section.items.every((i) => i.soon) && items.length === 0)) return null

            return (
              <Stack key={section.labelKey || 'main'} gap={2}>
                {section.labelKey && (
                  <Text size="xs" fw={600} c="dimmed" px="sm" pb={2}>
                    {t(section.labelKey)}
                  </Text>
                )}
                {items.map((item) =>
                  item.soon ? (
                    <NavLink
                      key={item.to}
                      label={t(item.labelKey)}
                      leftSection={<item.icon size={18} stroke={1.6} />}
                      rightSection={<Badge size="xs" variant="default">{t('nav.soon')}</Badge>}
                      disabled
                      style={{ borderRadius: 8 }}
                    />
                  ) : (
                    <NavLink
                      key={item.to}
                      component={Link}
                      to={item.to}
                      label={t(item.labelKey)}
                      leftSection={<item.icon size={18} stroke={1.6} />}
                      active={item.to === activeTarget}
                      aria-current={item.to === activeTarget ? 'page' : undefined}
                      onClick={onNavigate}
                      variant="light"
                      style={{ borderRadius: 8 }}
                      fw={item.to === activeTarget ? 600 : 500}
                    />
                  ),
                )}
              </Stack>
            )
          })}
        </Stack>
      </ScrollArea>
    </Stack>
  )
}
