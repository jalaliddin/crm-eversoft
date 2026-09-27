import { Avatar, Group, Menu, Text, UnstyledButton, useMantineColorScheme } from '@mantine/core'
import { IconLanguage, IconLogout, IconMoon, IconSun, IconUserCircle } from '@tabler/icons-react'
import { useNavigate } from 'react-router'
import { useTranslation } from 'react-i18next'
import { useAuth } from '@/features/auth/useAuth'
import { initials } from '@/lib/format'

const LANGUAGES = [
  { code: 'en', label: 'English' },
  { code: 'ru', label: 'Русский' },
  { code: 'uz', label: "O'zbekcha" },
]

export function UserMenu() {
  const { me, logout } = useAuth()
  const navigate = useNavigate()
  const { t, i18n } = useTranslation()
  const { colorScheme, toggleColorScheme } = useMantineColorScheme()
  const user = me?.user

  return (
    <Menu position="bottom-end" width={230} shadow="md">
      <Menu.Target>
        <UnstyledButton aria-label="Account menu">
          <Group gap="xs" wrap="nowrap">
            <Avatar src={user?.avatar_url} radius="xl" size={34} color="khiva">
              {initials(user?.name)}
            </Avatar>
            <div style={{ lineHeight: 1.1 }} className="hide-sm">
              <Text size="sm" fw={600} visibleFrom="md" truncate maw={160}>
                {user?.name}
              </Text>
              <Text size="xs" c="dimmed" visibleFrom="md" truncate maw={160}>
                {user?.roles?.map((r) => r.name).join(', ') || (me?.is_platform_user ? 'Platform' : '')}
              </Text>
            </div>
          </Group>
        </UnstyledButton>
      </Menu.Target>
      <Menu.Dropdown>
        <Menu.Label>{user?.email}</Menu.Label>
        <Menu.Item leftSection={<IconUserCircle size={16} />} onClick={() => navigate(me?.is_platform_user ? '/platform/profile' : '/profile')}>
          {t('nav.profile')}
        </Menu.Item>
        <Menu.Item leftSection={colorScheme === 'dark' ? <IconSun size={16} /> : <IconMoon size={16} />} onClick={() => toggleColorScheme()}>
          {colorScheme === 'dark' ? t('common.lightTheme') : t('common.darkTheme')}
        </Menu.Item>
        <Menu.Divider />
        <Menu.Label>
          <Group gap={6}>
            <IconLanguage size={14} /> {t('common.language')}
          </Group>
        </Menu.Label>
        {LANGUAGES.map((lang) => (
          <Menu.Item key={lang.code} onClick={() => void i18n.changeLanguage(lang.code)} fw={i18n.language === lang.code ? 600 : 400}>
            {lang.label}
          </Menu.Item>
        ))}
        <Menu.Divider />
        <Menu.Item color="red" leftSection={<IconLogout size={16} />} onClick={() => void logout().then(() => navigate('/login'))}>
          {t('common.signOut')}
        </Menu.Item>
      </Menu.Dropdown>
    </Menu>
  )
}
