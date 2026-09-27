import { useState } from 'react'
import { ActionIcon, Button, Card, Group, NumberInput, Select, SimpleGrid, Stack, Switch, Table, Text, TextInput } from '@mantine/core'
import { IconPencil, IconPlus, IconTrash } from '@tabler/icons-react'
import { useQuery } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { fmtMoney, humanize } from '@/lib/format'
import { useApiMutation } from '@/hooks/useApiMutation'
import { useOptions } from '@/hooks/useLookups'
import { EntityModal } from '@/components/EntityModal'
import { EmptyState } from '@/components/EmptyState'
import { StatusBadge } from '@/components/StatusBadge'
import { confirmAction } from '@/components/ConfirmDialog'
import { useAuth } from '@/features/auth/useAuth'
import type { PenaltyRule } from '@/types/models'

type Draft = Omit<PenaltyRule, 'id'>
const blank: Draft = { name: '', type: 'late', min_minutes: 15, max_minutes: null, calculation: 'fixed', amount: 0, percentage: null, is_active: true }

function describe(rule: PenaltyRule): string {
  const range = rule.type === 'absence' ? 'Each absence' : rule.max_minutes ? `${rule.min_minutes}–${rule.max_minutes} min` : `${rule.min_minutes}+ min`
  const amount = {
    fixed: fmtMoney(rule.amount),
    per_minute: `${fmtMoney(rule.amount)} per minute`,
    percent_of_daily: `${rule.percentage}% of a day's pay`,
    daily_salary: "one day's pay",
  }[rule.calculation]
  return `${range} → ${amount}`
}

export function PenaltyRules() {
  const { can } = useAuth()
  const types = useOptions('penalty_types').filter((t) => t.value !== 'manual')
  const calculations = useOptions('penalty_calculations')
  const { data = [] } = useQuery({ queryKey: ['penalty-rules'], queryFn: () => api.get<PenaltyRule[]>('/penalty-rules') })
  const [editing, setEditing] = useState<PenaltyRule | null | undefined>(undefined)
  const [draft, setDraft] = useState<Draft>(blank)

  const open = (rule: PenaltyRule | null) => {
    setDraft(rule ?? blank)
    setEditing(rule)
  }

  const save = useApiMutation({
    mutationFn: () => (editing ? api.put(`/penalty-rules/${editing.id}`, draft) : api.post('/penalty-rules', draft)),
    invalidate: [['penalty-rules']],
    successMessage: 'Rule saved. It applies to attendance processed from now on.',
    onSuccess: () => setEditing(undefined),
  })
  const remove = useApiMutation<null, number>({ mutationFn: (id) => api.delete(`/penalty-rules/${id}`), invalidate: [['penalty-rules']], successMessage: 'Rule deleted.' })
  const set = <K extends keyof Draft>(key: K, value: Draft[K]) => setDraft((d) => ({ ...d, [key]: value }))

  return (
    <Card>
      <Group justify="space-between" mb="sm">
        <Text size="sm" c="dimmed" maw={640}>For each late arrival, early leave or absence, the most specific matching rule applies — rules for the same type never add up.</Text>
        {can('penalties.manage') && <Button size="xs" leftSection={<IconPlus size={14} />} onClick={() => open(null)}>New rule</Button>}
      </Group>
      {data.length === 0 ? <EmptyState compact title="No penalty rules" message="Without rules, late arrivals are recorded but not penalized." /> : (
        <Table.ScrollContainer minWidth={560}>
          <Table verticalSpacing="sm">
            <Table.Tbody>
              {data.map((rule) => (
                <Table.Tr key={rule.id} opacity={rule.is_active ? 1 : 0.55}>
                  <Table.Td><Text size="sm" fw={600}>{rule.name}</Text><Text size="xs" c="dimmed">{humanize(rule.type)}</Text></Table.Td>
                  <Table.Td><Text size="sm">{describe(rule)}</Text></Table.Td>
                  <Table.Td><StatusBadge status={rule.is_active ? 'active' : 'inactive'} /></Table.Td>
                  {can('penalties.manage') && (
                    <Table.Td ta="right" w={90}>
                      <ActionIcon variant="subtle" aria-label="Edit rule" onClick={() => open(rule)}><IconPencil size={16} /></ActionIcon>
                      <ActionIcon variant="subtle" color="red" aria-label="Delete rule" onClick={() => confirmAction({ title: 'Delete rule?', message: 'Existing penalties are kept.', confirmLabel: 'Delete', danger: true, onConfirm: () => remove.mutate(rule.id) })}><IconTrash size={16} /></ActionIcon>
                    </Table.Td>
                  )}
                </Table.Tr>
              ))}
            </Table.Tbody>
          </Table>
        </Table.ScrollContainer>
      )}
      <EntityModal opened={editing !== undefined} onClose={() => setEditing(undefined)} title={editing ? 'Edit rule' : 'New penalty rule'} formId="rule-form" submitting={save.isPending}>
        <form id="rule-form" onSubmit={(e) => { e.preventDefault(); save.mutate() }}>
          <Stack gap="sm">
            <TextInput label="Name" required value={draft.name} onChange={(e) => set('name', e.currentTarget.value)} placeholder="e.g. Late more than 15 minutes" />
            <SimpleGrid cols={2} spacing="sm">
              <Select label="Applies to" data={types} value={draft.type} onChange={(v) => v && set('type', v as Draft['type'])} />
              <Select label="Amount" data={calculations} value={draft.calculation} onChange={(v) => v && set('calculation', v as Draft['calculation'])} />
            </SimpleGrid>
            {draft.type !== 'absence' && (
              <SimpleGrid cols={2} spacing="sm">
                <NumberInput label="From (minutes)" value={draft.min_minutes} onChange={(v) => set('min_minutes', Number(v) || 0)} min={0} />
                <NumberInput label="Up to (minutes)" placeholder="No limit" value={draft.max_minutes ?? ''} onChange={(v) => set('max_minutes', v === '' ? null : Number(v))} min={0} />
              </SimpleGrid>
            )}
            {(draft.calculation === 'fixed' || draft.calculation === 'per_minute') && (
              <NumberInput label={draft.calculation === 'fixed' ? 'Fixed amount' : 'Amount per minute'} value={draft.amount} onChange={(v) => set('amount', Number(v) || 0)} min={0} thousandSeparator=" " />
            )}
            {draft.calculation === 'percent_of_daily' && (
              <NumberInput label="Percent of a day's pay" value={draft.percentage ?? ''} onChange={(v) => set('percentage', v === '' ? null : Number(v))} min={0} max={100} suffix="%" />
            )}
            <Switch label="Active" checked={draft.is_active} onChange={(e) => set('is_active', e.currentTarget.checked)} />
          </Stack>
        </form>
      </EntityModal>
    </Card>
  )
}
