import { useState } from 'react'
import { Group, Pagination, Paper, Select, Table, Text, Tooltip, UnstyledButton } from '@mantine/core'
import { MonthPickerInput } from '@mantine/dates'
import { useQuery } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { api } from '@/lib/api'
import type { PageMeta, Paginated } from '@/types/api'
import { dayjs, tenantNow } from '@/lib/format'
import { PageHeader } from '@/components/PageHeader'
import { SearchInput } from '@/components/SearchInput'
import { LoadingState } from '@/components/LoadingState'
import { ErrorState } from '@/components/ErrorState'
import { EmptyState } from '@/components/EmptyState'
import { useListParams } from '@/hooks/useListParams'
import { useDepartments, toOptions } from '@/hooks/useLookups'
import type { CalendarDay } from '@/types/models'
import { CalendarLegend } from './MonthCalendar'
import { CODE_STYLE } from './calendarCodes'
import { AttendanceRecordDrawer } from './AttendanceRecordDrawer'

interface MatrixRow {
  employee: { id: number; full_name: string; employee_number: string; department: string | null }
  days: CalendarDay[]
}

/** Organization-wide month grid: employees × days, every cell opens that day's record. */
export default function CalendarPage() {
  const { t } = useTranslation()
  const list = useListParams({ month: tenantNow().format('YYYY-MM'), per_page: '25' })
  const { params } = list
  const [recordId, setRecordId] = useState<number | null>(null)
  const { data: departments } = useDepartments()

  const { data, isLoading, error, refetch } = useQuery({
    queryKey: ['attendance', 'calendar', list.query],
    queryFn: () => api.list<MatrixRow>('/attendance/calendar', list.query) as Promise<Paginated<MatrixRow, PageMeta>>,
    placeholderData: (p) => p,
  })
  const month = dayjs(`${params.month}-01`)
  const days = data?.data[0]?.days ?? []

  return (
    <>
      <PageHeader
        title="Attendance calendar"
        description={month.format('MMMM YYYY')}
        actions={<MonthPickerInput value={`${params.month}-01`} onChange={(v) => v && list.set({ month: String(v).slice(0, 7) })} valueFormat="MMMM YYYY" w={180} aria-label="Month" />}
      />
      <Group mb="md" gap="sm" justify="space-between" wrap="wrap">
        <Group gap="sm">
          <SearchInput value={params.search ?? ''} onChange={(search) => list.set({ search })} placeholder="Employee" />
          <Select placeholder="All departments" data={toOptions(departments)} value={params.department_id ?? null} onChange={(v) => list.set({ department_id: v })} clearable w={200} aria-label="Department" />
        </Group>
        <CalendarLegend />
      </Group>

      {isLoading ? <LoadingState /> : error ? <ErrorState error={error} onRetry={() => void refetch()} /> : !data?.data.length ? <EmptyState title="No employees" /> : (
        <Paper withBorder radius="lg" style={{ overflow: 'hidden', borderColor: 'var(--app-border)' }}>
          <Table.ScrollContainer minWidth={260 + days.length * 34}>
            <Table withColumnBorders={false} verticalSpacing={4} horizontalSpacing={4} stickyHeader>
              <Table.Thead>
                <Table.Tr>
                  <Table.Th miw={220} pl="md" style={{ position: 'sticky', left: 0, background: 'var(--mantine-color-body)', zIndex: 2 }}>Employee</Table.Th>
                  {days.map((d) => {
                    const date = dayjs(d.date)
                    return (
                      <Table.Th key={d.date} ta="center" p={2}>
                        <Text size="10px" c="dimmed">{date.format('dd').slice(0, 2)}</Text>
                        <Text size="xs" fw={600} className="tabular">{date.date()}</Text>
                      </Table.Th>
                    )
                  })}
                </Table.Tr>
              </Table.Thead>
              <Table.Tbody>
                {data.data.map((row) => (
                  <Table.Tr key={row.employee.id}>
                    <Table.Td pl="md" style={{ position: 'sticky', left: 0, background: 'var(--mantine-color-body)', zIndex: 1 }}>
                      <Text size="sm" fw={500} truncate maw={210}>{row.employee.full_name}</Text>
                      <Text size="xs" c="dimmed" truncate maw={210}>{row.employee.department ?? row.employee.employee_number}</Text>
                    </Table.Td>
                    {row.days.map((d) => {
                      const style = d.code ? CODE_STYLE[d.code] : undefined
                      const label = `${row.employee.full_name}, ${dayjs(d.date).format('D MMM')}: ${d.status ? t(`status.${d.status}`) : '—'}`
                      return (
                        <Table.Td key={d.date} p={2}>
                          <Tooltip label={label}>
                            <UnstyledButton
                              w={30}
                              h={28}
                              aria-label={label}
                              disabled={!d.record_id}
                              onClick={() => d.record_id && setRecordId(d.record_id)}
                              style={{ borderRadius: 6, background: style?.bg, color: style?.fg, fontSize: 11, fontWeight: 700, display: 'grid', placeItems: 'center', cursor: d.record_id ? 'pointer' : 'default' }}
                            >
                              {d.code ?? ''}
                            </UnstyledButton>
                          </Tooltip>
                        </Table.Td>
                      )
                    })}
                  </Table.Tr>
                ))}
              </Table.Tbody>
            </Table>
          </Table.ScrollContainer>
          {data.meta.last_page > 1 && (
            <Group justify="space-between" px="md" py="sm" style={{ borderTop: '1px solid var(--app-border)' }}>
              <Text size="sm" c="dimmed" className="tabular">{data.meta.total} employees</Text>
              <Pagination size="sm" total={data.meta.last_page} value={data.meta.current_page} onChange={(page) => list.set({ page }, false)} />
            </Group>
          )}
        </Paper>
      )}
      <AttendanceRecordDrawer recordId={recordId} onClose={() => setRecordId(null)} />
    </>
  )
}
