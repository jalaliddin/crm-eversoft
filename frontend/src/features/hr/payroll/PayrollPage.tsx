import { useState } from 'react'
import { Button, Group, NumberInput, Select, SimpleGrid, Stack } from '@mantine/core'
import { IconCalculator } from '@tabler/icons-react'
import { useQuery } from '@tanstack/react-query'
import { useNavigate } from 'react-router'
import { api } from '@/lib/api'
import { dayjs, fmtDateTime, fmtMoney } from '@/lib/format'
import { PageHeader } from '@/components/PageHeader'
import { DataTable } from '@/components/DataTable'
import { StatusBadge } from '@/components/StatusBadge'
import { EntityModal } from '@/components/EntityModal'
import { useApiMutation } from '@/hooks/useApiMutation'
import { Can } from '@/features/auth/guards'
import type { PayrollPeriod } from '@/types/models'

export default function PayrollPage() {
  const navigate = useNavigate()
  const previous = dayjs().subtract(1, 'month')
  const [open, setOpen] = useState(false)
  const [year, setYear] = useState<number | string>(previous.year())
  const [month, setMonth] = useState<string | null>(String(previous.month() + 1))
  const { data, isLoading, error } = useQuery({ queryKey: ['payroll'], queryFn: () => api.list<PayrollPeriod>('/payroll') })
  const calculate = useApiMutation<PayrollPeriod>({
    mutationFn: () => api.post('/payroll/calculate', { year: Number(year), month: Number(month) }),
    invalidate: [['payroll']],
    successMessage: 'Payroll calculated.',
    onSuccess: (period) => navigate(`/hr/payroll/${period.id}`),
  })

  return (
    <>
      <PageHeader
        title="Payroll"
        description="Monthly salary, daily and hourly pay calculated from attendance, penalties and bonuses."
        actions={<Can permission="payroll.calculate"><Button leftSection={<IconCalculator size={16} />} onClick={() => setOpen(true)}>Calculate a month</Button></Can>}
      />
      <DataTable
        rows={data?.data}
        meta={data?.meta}
        loading={isLoading}
        error={error}
        onRowClick={(p) => navigate(`/hr/payroll/${p.id}`)}
        columns={[
          { key: 'period', header: 'Month', render: (p) => <strong>{dayjs(`${p.year}-${String(p.month).padStart(2, '0')}-01`).format('MMMM YYYY')}</strong> },
          { key: 'records', header: 'Employees', align: 'right', render: (p) => <span className="tabular">{p.records_count ?? 0}</span> },
          { key: 'gross', header: 'Gross', align: 'right', visibleFrom: 'md', render: (p) => <span className="tabular">{fmtMoney(p.totals?.gross)}</span> },
          { key: 'penalties', header: 'Penalties', align: 'right', visibleFrom: 'md', render: (p) => <span className="tabular">{fmtMoney(p.totals?.penalties)}</span> },
          { key: 'net', header: 'Net to pay', align: 'right', render: (p) => <strong className="tabular">{fmtMoney(p.totals?.net)}</strong> },
          { key: 'status', header: 'Status', render: (p) => <StatusBadge status={p.status} /> },
          { key: 'calculated_at', header: 'Calculated', visibleFrom: 'lg', render: (p) => fmtDateTime(p.calculated_at) },
        ]}
        empty={{ title: 'No payroll periods yet', message: 'Calculate the previous month once attendance is complete.' }}
      />
      <EntityModal opened={open} onClose={() => setOpen(false)} title="Calculate payroll" formId="calc-form" submitLabel="Calculate" submitting={calculate.isPending} size="sm">
        <form id="calc-form" onSubmit={(e) => { e.preventDefault(); calculate.mutate() }}>
          <Stack gap="sm">
            <SimpleGrid cols={2} spacing="sm">
              <Select label="Month" data={Array.from({ length: 12 }, (_, i) => ({ value: String(i + 1), label: dayjs().month(i).format('MMMM') }))} value={month} onChange={setMonth} />
              <NumberInput label="Year" value={year} onChange={setYear} min={2000} max={2100} />
            </SimpleGrid>
            <Group gap={4}><span style={{ fontSize: 13, color: 'var(--mantine-color-dimmed)' }}>Recalculating keeps manual bonuses. Only approved penalties are deducted.</span></Group>
          </Stack>
        </form>
      </EntityModal>
    </>
  )
}
