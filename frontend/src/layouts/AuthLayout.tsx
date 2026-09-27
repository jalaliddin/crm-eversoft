import { Box, Center, Group, Paper, Stack, Text, ThemeIcon, Title } from '@mantine/core'
import { IconFingerprint, IconTargetArrow, IconUsers } from '@tabler/icons-react'
import { Outlet } from 'react-router'

/** Split screen: a quiet statement of what the product does, and the form. */
export function AuthLayout() {
  const point = (icon: React.ReactNode, title: string, text: string) => (
    <Group align="flex-start" wrap="nowrap" gap="md">
      <ThemeIcon size={36} radius="md" variant="white" color="khiva">
        {icon}
      </ThemeIcon>
      <div>
        <Text fw={600} c="white">{title}</Text>
        <Text size="sm" c="khiva.1">{text}</Text>
      </div>
    </Group>
  )

  return (
    <Box mih="100vh" style={{ display: 'grid', gridTemplateColumns: 'minmax(0, 1fr)' }} className="auth-grid">
      <style>{`@media (min-width: 62em) { .auth-grid { grid-template-columns: minmax(0, 5fr) minmax(0, 6fr) !important; } }`}</style>
      <Box visibleFrom="md" bg="khiva.8" p={56} style={{ display: 'flex', flexDirection: 'column', justifyContent: 'space-between' }}>
        <Group gap="sm">
          <ThemeIcon size={40} radius="md" color="white" variant="white">
            <Text fw={800} c="khiva.8" fz={20}>E</Text>
          </ThemeIcon>
          <Text fw={650} c="white" fz="lg">Eversoft CRM</Text>
        </Group>
        <Stack gap="xl" maw={440}>
          <Title order={1} c="white" fz={34} lh={1.2}>
            Customers, deals and the people who serve them — in one place.
          </Title>
          {point(<IconTargetArrow size={20} />, 'Sales pipeline', 'Leads, deals and follow-ups with a full history per customer.')}
          {point(<IconUsers size={20} />, 'Team records', 'Departments, schedules, penalties and payroll per organization.')}
          {point(<IconFingerprint size={20} />, 'Attendance from the terminal', 'Face and card punches turned into late, early leave and worked hours.')}
        </Stack>
        <Text size="xs" c="khiva.2">crm.eversoft.uz</Text>
      </Box>
      <Center p="lg">
        <Paper w="100%" maw={420} p={{ base: 'lg', sm: 36 }} radius="lg" withBorder style={{ borderColor: 'var(--app-border)' }}>
          <Outlet />
        </Paper>
      </Center>
    </Box>
  )
}
