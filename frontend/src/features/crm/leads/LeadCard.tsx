import { Avatar, Group, Paper, Text, Tooltip } from '@mantine/core'
import { useNavigate } from 'react-router'
import { fmtMoney, fromNow, initials } from '@/lib/format'
import type { Lead } from '@/types/models'

export function LeadCard({ lead, dragging }: { lead: Lead; dragging?: boolean }) {
  const navigate = useNavigate()
  return (
    <Paper
      p="sm"
      radius="md"
      withBorder
      shadow={dragging ? 'md' : undefined}
      style={{ borderColor: 'var(--app-border)' }}
      onClick={() => navigate(`/crm/leads/${lead.id}`)}
      onKeyDown={(e) => e.key === 'Enter' && navigate(`/crm/leads/${lead.id}`)}
    >
      <Text size="sm" fw={600} lineClamp={2}>{lead.name}</Text>
      {lead.company_name && <Text size="xs" c="dimmed" truncate>{lead.company_name}</Text>}
      <Group justify="space-between" mt={8} wrap="nowrap">
        <Text size="xs" fw={600} className="tabular">{lead.expected_value ? fmtMoney(lead.expected_value) : ''}</Text>
        <Group gap={6} wrap="nowrap">
          <Text size="xs" c="dimmed">{fromNow(lead.created_at)}</Text>
          {lead.assignee && (
            <Tooltip label={lead.assignee.name}>
              <Avatar size={22} radius="xl" color="khiva">{initials(lead.assignee.name)}</Avatar>
            </Tooltip>
          )}
        </Group>
      </Group>
    </Paper>
  )
}
