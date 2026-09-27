import { useState } from 'react'
import { ActionIcon, Button, Card, Group, NumberInput, Stack, Table, Text, TextInput } from '@mantine/core'
import { DateInput } from '@mantine/dates'
import { IconPlus, IconTrash } from '@tabler/icons-react'
import { useQuery } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { dayjs, fmtDate } from '@/lib/format'
import { PageHeader } from '@/components/PageHeader'
import { EmptyState } from '@/components/EmptyState'
import { useApiMutation } from '@/hooks/useApiMutation'
import { useAuth } from '@/features/auth/useAuth'
import type { Holiday } from '@/types/models'

export default function HolidaysPage() {
  const { can } = useAuth()
  const [year, setYear] = useState(dayjs().year())
  const [date, setDate] = useState<string | null>(null)
  const [name, setName] = useState('')
  const { data = [] } = useQuery({ queryKey: ['holidays', year], queryFn: () => api.get<Holiday[]>('/holidays', { year }) })
  const add = useApiMutation({ mutationFn: () => api.post('/holidays', { date, name }), invalidate: [['holidays']], successMessage: 'Holiday added.', onSuccess: () => { setDate(null); setName('') } })
  const remove = useApiMutation<null, number>({ mutationFn: (id) => api.delete(`/holidays/${id}`), invalidate: [['holidays']], successMessage: 'Holiday removed.' })

  return (
    <>
      <PageHeader title="Holidays" description="Public holidays are never counted as absences." actions={<NumberInput value={year} onChange={(v) => setYear(Number(v))} w={110} aria-label="Year" min={2000} max={2100} />} />
      <Card maw={720}>
        {can('schedules.manage') && (
          <Group align="flex-end" mb="md" wrap="wrap">
            <DateInput label="Date" value={date} onChange={setDate} valueFormat="DD.MM.YYYY" w={170} />
            <TextInput label="Name" value={name} onChange={(e) => setName(e.currentTarget.value)} flex={1} miw={200} />
            <Button leftSection={<IconPlus size={16} />} disabled={!date || !name.trim()} loading={add.isPending} onClick={() => add.mutate()}>Add</Button>
          </Group>
        )}
        {data.length === 0 ? (
          <EmptyState compact title={`No holidays in ${year}`} />
        ) : (
          <Table verticalSpacing="sm">
            <Table.Tbody>
              {data.map((h) => (
                <Table.Tr key={h.id}>
                  <Table.Td w={140} className="tabular">{fmtDate(h.date)}</Table.Td>
                  <Table.Td><Stack gap={0}><Text size="sm" fw={500}>{h.name}</Text><Text size="xs" c="dimmed">{dayjs(h.date).format('dddd')}</Text></Stack></Table.Td>
                  {can('schedules.manage') && (
                    <Table.Td ta="right"><ActionIcon variant="subtle" color="red" aria-label={`Remove ${h.name}`} onClick={() => remove.mutate(h.id)}><IconTrash size={16} /></ActionIcon></Table.Td>
                  )}
                </Table.Tr>
              ))}
            </Table.Tbody>
          </Table>
        )}
      </Card>
    </>
  )
}
