import { Stack, Text, ThemeIcon, Title } from '@mantine/core'
import { IconCalendarEvent, IconCoin, IconPhoneCall } from '@tabler/icons-react'

const MODULES = {
  finance: { icon: IconCoin, title: 'Finance', text: 'Income, expenses, accounts and transactions will live here, connected to deals and payroll.' },
  booking: { icon: IconCalendarEvent, title: 'Booking', text: 'Reservations for tables, rooms and resources, with availability calendars.' },
  telephony: { icon: IconPhoneCall, title: 'Telephony', text: 'Incoming call pop-ups, click-to-call and call history from Asterisk, FreePBX or Grandstream UCM.' },
} as const

export default function ComingSoonPage({ module }: { module: keyof typeof MODULES }) {
  const m = MODULES[module]
  return (
    <Stack align="center" ta="center" py={80} gap="sm" maw={520} mx="auto">
      <ThemeIcon size={56} radius="xl" variant="light"><m.icon size={28} /></ThemeIcon>
      <Title order={2}>{m.title} is on the way</Title>
      <Text c="dimmed">{m.text}</Text>
    </Stack>
  )
}
