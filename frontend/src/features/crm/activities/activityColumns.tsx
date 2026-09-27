import { ActionIcon, Anchor, Group, Text, Tooltip } from '@mantine/core'
import { IconCalendarEvent, IconCheck, IconChecklist, IconMail, IconNote, IconPhone } from '@tabler/icons-react'
import { Link } from 'react-router'
import type { Column } from '@/components/DataTable'
import { StatusBadge } from '@/components/StatusBadge'
import { fmtDateTime } from '@/lib/format'
import type { Activity } from '@/types/models'

export const ACTIVITY_ICONS = { task: IconChecklist, call: IconPhone, meeting: IconCalendarEvent, email: IconMail, note: IconNote }

export function activityColumns(onComplete: (a: Activity) => void, withLinks = true): Column<Activity>[] {
  return [
    {
      key: 'title',
      header: 'Activity',
      sortable: true,
      render: (a) => {
        const Icon = ACTIVITY_ICONS[a.type]
        return (
          <Group gap="sm" wrap="nowrap">
            <Icon size={18} stroke={1.6} style={{ flexShrink: 0, opacity: 0.7 }} />
            <div>
              <Text size="sm" fw={500} td={a.status === 'completed' ? 'line-through' : undefined}>{a.title}</Text>
              {withLinks && (a.customer || a.deal || a.lead) && (
                <Text size="xs" c="dimmed">
                  {a.deal ? <Anchor component={Link} to={`/crm/deals/${a.deal.id}`} size="xs" onClick={(e) => e.stopPropagation()}>{a.deal.title}</Anchor>
                    : a.lead ? <Anchor component={Link} to={`/crm/leads/${a.lead.id}`} size="xs" onClick={(e) => e.stopPropagation()}>{a.lead.name}</Anchor>
                      : a.customer ? <Anchor component={Link} to={`/crm/customers/${a.customer.id}`} size="xs" onClick={(e) => e.stopPropagation()}>{a.customer.display_name}</Anchor> : null}
                </Text>
              )}
            </div>
          </Group>
        )
      },
    },
    {
      key: 'due_at',
      header: 'Due',
      sortable: true,
      render: (a) => <Text size="sm" c={a.is_overdue ? 'red' : undefined} fw={a.is_overdue ? 600 : undefined} className="tabular">{fmtDateTime(a.due_at)}</Text>,
    },
    { key: 'priority', header: 'Priority', sortable: true, render: (a) => <StatusBadge status={a.priority} />, visibleFrom: 'md' },
    { key: 'assignee', header: 'Assigned to', render: (a) => a.assignee?.name ?? '—', visibleFrom: 'md' },
    { key: 'status', header: 'Status', sortable: true, render: (a) => <StatusBadge status={a.is_overdue ? 'urgent' : a.status} label={a.is_overdue ? 'Overdue' : undefined} /> },
    {
      key: 'complete',
      header: '',
      width: 48,
      render: (a) =>
        a.status !== 'completed' && a.status !== 'cancelled' ? (
          <Tooltip label="Mark as done">
            <ActionIcon variant="subtle" color="teal" aria-label={`Mark ${a.title} as done`} onClick={(e) => { e.stopPropagation(); onComplete(a) }}>
              <IconCheck size={18} />
            </ActionIcon>
          </Tooltip>
        ) : null,
    },
  ]
}
