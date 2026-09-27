import { Group, Text, Timeline as MTimeline, ThemeIcon } from '@mantine/core'
import {
  IconArrowRight, IconCalendarEvent, IconCheck, IconFileUpload, IconNote, IconPencil, IconPlus, IconUserPlus,
} from '@tabler/icons-react'
import type { TimelineEvent } from '@/types/models'
import { fmtDateTime, fromNow } from '@/lib/format'
import { EmptyState } from './EmptyState'

const ICONS: Record<string, typeof IconPlus> = {
  'customer.created': IconUserPlus,
  'customer.updated': IconPencil,
  'lead.created': IconPlus,
  'lead.status_changed': IconArrowRight,
  'deal.created': IconPlus,
  'deal.stage_changed': IconArrowRight,
  'activity.created': IconCalendarEvent,
  'activity.completed': IconCheck,
  'note.created': IconNote,
  'document.uploaded': IconFileUpload,
}

/** Reusable CRM timeline used on customer, lead and deal pages. */
export function Timeline({ events }: { events: TimelineEvent[] }) {
  if (events.length === 0) {
    return <EmptyState compact title="No history yet" message="Changes, activities, notes and files will appear here." />
  }

  return (
    <MTimeline bulletSize={26} lineWidth={2} active={-1}>
      {events.map((event) => {
        const Icon = ICONS[event.event] ?? IconPencil
        return (
          <MTimeline.Item
            key={event.id}
            bullet={
              <ThemeIcon size={26} radius="xl" variant="light" color={event.event.endsWith('completed') ? 'teal' : 'khiva'}>
                <Icon size={14} />
              </ThemeIcon>
            }
          >
            <Text size="sm" fw={500}>
              {event.description}
            </Text>
            <Group gap={6}>
              <Text size="xs" c="dimmed" title={fmtDateTime(event.occurred_at)}>
                {fromNow(event.occurred_at)}
              </Text>
              {event.causer && (
                <Text size="xs" c="dimmed">
                  by {event.causer.name}
                </Text>
              )}
            </Group>
          </MTimeline.Item>
        )
      })}
    </MTimeline>
  )
}
