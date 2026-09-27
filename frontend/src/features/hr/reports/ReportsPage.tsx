import { useState } from 'react'
import { Button, Group, Paper, Select, Table, Tabs, Text } from '@mantine/core'
import { DateInput, MonthPickerInput } from '@mantine/dates'
import { IconDownload } from '@tabler/icons-react'
import { useQuery } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { dayjs, fmtMinutes, fmtNumber, fmtTime, humanize, tenantNow, today } from '@/lib/format'
import { notifyError } from '@/lib/notify'
import { PageHeader } from '@/components/PageHeader'
import { LoadingState } from '@/components/LoadingState'
import { EmptyState } from '@/components/EmptyState'
import { ErrorState } from '@/components/ErrorState'
import { useDepartments, toOptions } from '@/hooks/useLookups'
import { useAuth } from '@/features/auth/useAuth'
import type { ListParams } from '@/types/api'
import type { PayrollPeriod, ReportTable } from '@/types/models'

const MINUTE_KEYS = ['worked_minutes', 'late_minutes', 'early_leave_minutes']
const TIME_KEYS = ['check_in', 'check_out']
const NUMERIC = new Set(['work_days', 'present_days', 'absent_days', 'late_count', 'late_minutes', 'early_leave_count', 'worked_hours', 'overtime_hours', 'base', 'worked_days', 'overtime', 'penalties', 'bonuses', 'net', 'worked_minutes', 'early_leave_minutes'])

function cell(key: string, value: unknown) {
  if (value === null || value === undefined || value === '') return '—'
  if (TIME_KEYS.includes(key)) return fmtTime(String(value))
  if (MINUTE_KEYS.includes(key)) return fmtMinutes(Number(value))
  if (key === 'status' || key === 'salary_type') return humanize(String(value))
  if (typeof value === 'number') return fmtNumber(value)
  return String(value)
}

function ReportView({ url, params, name }: { url: string; params: ListParams; name: string }) {
  const { can } = useAuth()
  const { data, isLoading, error } = useQuery({ queryKey: ['report', url, params], queryFn: () => api.get<ReportTable>(url, params) })
  const download = (format: string) => void api.download(url, { ...params, export: format }, `${name}.${format}`).catch(notifyError)

  return (
    <>
      {can('attendance.export') && (
        <Group justify="flex-end" mb="sm" gap="xs">
          {['xlsx', 'csv', 'pdf'].map((f) => (
            <Button key={f} size="xs" variant="default" leftSection={<IconDownload size={14} />} onClick={() => download(f)}>{f === 'xlsx' ? 'Excel' : f.toUpperCase()}</Button>
          ))}
        </Group>
      )}
      {isLoading ? <LoadingState rows={8} /> : error ? <ErrorState error={error} /> : !data?.rows.length ? <EmptyState title="No data for these filters" /> : (
        <Paper withBorder radius="lg" style={{ overflow: 'hidden', borderColor: 'var(--app-border)' }}>
          <Table.ScrollContainer minWidth={900}>
            <Table striped verticalSpacing="xs" fz="sm" stickyHeader>
              <Table.Thead>
                <Table.Tr>{Object.entries(data.columns).map(([k, label]) => <Table.Th key={k} ta={NUMERIC.has(k) ? 'right' : undefined}>{label}</Table.Th>)}</Table.Tr>
              </Table.Thead>
              <Table.Tbody>
                {data.rows.map((row, i) => (
                  <Table.Tr key={i}>
                    {Object.keys(data.columns).map((k) => <Table.Td key={k} ta={NUMERIC.has(k) ? 'right' : undefined} className="tabular">{cell(k, row[k])}</Table.Td>)}
                  </Table.Tr>
                ))}
              </Table.Tbody>
            </Table>
          </Table.ScrollContainer>
          <Text size="xs" c="dimmed" p="sm">{data.rows.length} rows</Text>
        </Paper>
      )}
    </>
  )
}

export default function ReportsPage() {
  const { can } = useAuth()
  const [tab, setTab] = useState<string | null>('daily')
  const [date, setDate] = useState<string | null>(today())
  const [month, setMonth] = useState(tenantNow().format('YYYY-MM'))
  const [department, setDepartment] = useState<string | null>(null)
  const [periodId, setPeriodId] = useState<string | null>(null)
  const { data: departments } = useDepartments()
  const periods = useQuery({ queryKey: ['payroll'], queryFn: () => api.list<PayrollPeriod>('/payroll'), enabled: can('payroll.view') })
  const periodOptions = (periods.data?.data ?? []).map((p) => ({ value: String(p.id), label: dayjs(`${p.year}-${String(p.month).padStart(2, '0')}-01`).format('MMMM YYYY') }))
  const selectedPeriod = periodId ?? periodOptions[0]?.value ?? null

  return (
    <>
      <PageHeader title="HR reports" description="Every report can be downloaded as Excel, CSV or PDF." />
      <Tabs value={tab} onChange={setTab} keepMounted={false}>
        <Tabs.List mb="md">
          <Tabs.Tab value="daily">Daily attendance</Tabs.Tab>
          <Tabs.Tab value="monthly">Monthly attendance</Tabs.Tab>
          {can('payroll.view') && <Tabs.Tab value="payroll">Payroll</Tabs.Tab>}
        </Tabs.List>

        <Tabs.Panel value="daily">
          <Group mb="md" gap="sm">
            <DateInput value={date} onChange={setDate} valueFormat="DD.MM.YYYY" maxDate={today()} w={160} aria-label="Date" />
            <Select placeholder="All departments" data={toOptions(departments)} value={department} onChange={setDepartment} clearable w={220} aria-label="Department" />
          </Group>
          <ReportView url="/reports/attendance/daily" params={{ date, department_id: department }} name={`attendance-${date}`} />
        </Tabs.Panel>

        <Tabs.Panel value="monthly">
          <Group mb="md" gap="sm">
            <MonthPickerInput value={`${month}-01`} onChange={(v) => v && setMonth(String(v).slice(0, 7))} valueFormat="MMMM YYYY" w={180} aria-label="Month" />
            <Select placeholder="All departments" data={toOptions(departments)} value={department} onChange={setDepartment} clearable w={220} aria-label="Department" />
          </Group>
          <ReportView url="/reports/attendance/monthly" params={{ month, department_id: department }} name={`attendance-${month}`} />
        </Tabs.Panel>

        <Tabs.Panel value="payroll">
          <Group mb="md"><Select placeholder="Choose a month" data={periodOptions} value={selectedPeriod} onChange={setPeriodId} w={220} aria-label="Payroll period" /></Group>
          {selectedPeriod ? <ReportView url={`/reports/payroll/${selectedPeriod}`} params={{}} name="payroll" /> : <EmptyState title="No payroll calculated yet" />}
        </Tabs.Panel>
      </Tabs>
    </>
  )
}
