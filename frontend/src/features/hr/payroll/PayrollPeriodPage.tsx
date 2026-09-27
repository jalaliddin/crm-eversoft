import { useState } from 'react'
import { Alert, Button, Group, Menu, NumberInput, SimpleGrid, Stack, Text } from '@mantine/core'
import { IconCalculator, IconCheck, IconDownload, IconLockOpen, IconReceipt } from '@tabler/icons-react'
import { useQuery } from '@tanstack/react-query'
import { useParams } from 'react-router'
import { api } from '@/lib/api'
import { dayjs, fmtDateTime, fmtMinutes, fmtMoney, humanize } from '@/lib/format'
import { notifyError } from '@/lib/notify'
import { PageHeader } from '@/components/PageHeader'
import { DataTable } from '@/components/DataTable'
import { StatusBadge } from '@/components/StatusBadge'
import { StatCard } from '@/components/StatCard'
import { SearchInput } from '@/components/SearchInput'
import { EntityModal } from '@/components/EntityModal'
import { ErrorState } from '@/components/ErrorState'
import { LoadingState } from '@/components/LoadingState'
import { confirmAction } from '@/components/ConfirmDialog'
import { useApiMutation } from '@/hooks/useApiMutation'
import { useListParams } from '@/hooks/useListParams'
import { useAuth } from '@/features/auth/useAuth'
import { DetailList } from '@/features/crm/shared/DetailList'
import type { PayrollPeriod, PayrollRecord } from '@/types/models'

export default function PayrollPeriodPage() {
  const id = Number(useParams().id)
  const { can } = useAuth()
  const list = useListParams({ per_page: '50' })
  const [record, setRecord] = useState<PayrollRecord | null>(null)
  const [bonus, setBonus] = useState<number | string>(0)
  const period = useQuery({ queryKey: ['payroll', id], queryFn: () => api.get<PayrollPeriod>(`/payroll/${id}`) })
  const records = useQuery({ queryKey: ['payroll', id, 'records', list.query], queryFn: () => api.list<PayrollRecord>(`/payroll/${id}/records`, list.query), placeholderData: (p) => p })
  const invalidate = [['payroll']]
  const recalc = useApiMutation({ mutationFn: () => api.post('/payroll/calculate', { period_id: id }), invalidate, successMessage: 'Recalculated.' })
  const approve = useApiMutation({ mutationFn: () => api.post(`/payroll/${id}/approve`), invalidate, successMessage: 'Payroll approved.' })
  const pay = useApiMutation({ mutationFn: () => api.post(`/payroll/${id}/pay`), invalidate, successMessage: 'Marked as paid.' })
  const reopen = useApiMutation({ mutationFn: () => api.post(`/payroll/${id}/reopen`), invalidate, successMessage: 'Payroll reopened.' })
  const saveBonus = useApiMutation({ mutationFn: () => api.put(`/payroll-records/${record?.id}`, { bonus_amount: Number(bonus) }), invalidate, successMessage: 'Bonus saved.', onSuccess: () => setRecord(null) })

  if (period.isLoading) return <LoadingState />
  if (period.error || !period.data) return <ErrorState error={period.error} />
  const p = period.data
  const title = dayjs(`${p.year}-${String(p.month).padStart(2, '0')}-01`).format('MMMM YYYY')
  const locked = p.status === 'approved' || p.status === 'closed'
  const exportAs = (format: string) => void api.download(`/reports/payroll/${id}`, { export: format }, `payroll.${format}`).catch(notifyError)

  return (
    <>
      <PageHeader
        breadcrumbs={[{ label: 'Payroll', to: '/hr/payroll' }, { label: title }]}
        title={<Group gap="sm">Payroll · {title}<StatusBadge status={p.status} size="lg" /></Group>}
        description={p.approved_at ? `Approved by ${p.approved_by?.name ?? '—'} on ${fmtDateTime(p.approved_at)}` : p.calculated_at ? `Calculated ${fmtDateTime(p.calculated_at)}` : undefined}
        actions={
          <>
            <Menu position="bottom-end">
              <Menu.Target><Button variant="default" leftSection={<IconDownload size={16} />}>Export</Button></Menu.Target>
              <Menu.Dropdown>
                <Menu.Item onClick={() => exportAs('xlsx')}>Excel (.xlsx)</Menu.Item>
                <Menu.Item onClick={() => exportAs('csv')}>CSV</Menu.Item>
                <Menu.Item onClick={() => exportAs('pdf')}>PDF</Menu.Item>
              </Menu.Dropdown>
            </Menu>
            {!locked && can('payroll.calculate') && <Button variant="default" leftSection={<IconCalculator size={16} />} loading={recalc.isPending} onClick={() => recalc.mutate()}>Recalculate</Button>}
            {p.status === 'calculated' && can('payroll.approve') && (
              <Button leftSection={<IconCheck size={16} />} onClick={() => confirmAction({ title: `Approve payroll for ${title}?`, message: 'Approved payroll is locked. It can be reopened until it is marked as paid.', confirmLabel: 'Approve', onConfirm: () => approve.mutate() })}>Approve</Button>
            )}
            {p.status === 'approved' && can('payroll.approve') && (
              <>
                <Button variant="default" leftSection={<IconLockOpen size={16} />} onClick={() => reopen.mutate()}>Reopen</Button>
                <Button color="teal" leftSection={<IconReceipt size={16} />} onClick={() => confirmAction({ title: 'Mark as paid?', message: 'This closes the period permanently.', confirmLabel: 'Mark as paid', onConfirm: () => pay.mutate() })}>Mark as paid</Button>
              </>
            )}
          </>
        }
      />

      <SimpleGrid cols={{ base: 2, md: 4 }} spacing="sm" mb="md">
        <StatCard label="Employees" value={p.records_count ?? 0} />
        <StatCard compact label="Gross" value={fmtMoney(p.totals?.gross)} />
        <StatCard compact label="Penalties" value={fmtMoney(p.totals?.penalties)} hint={`Bonuses ${fmtMoney(p.totals?.bonuses)}`} />
        <StatCard compact label="Net to pay" value={fmtMoney(p.totals?.net)} tone="teal" />
      </SimpleGrid>

      <Group mb="md"><SearchInput value={list.params.search ?? ''} onChange={(search) => list.set({ search })} placeholder="Employee" /></Group>

      <DataTable
        rows={records.data?.data}
        meta={records.data?.meta}
        loading={records.isLoading}
        error={records.error}
        {...list.tableProps}
        onRowClick={(r) => { setRecord(r); setBonus(r.bonus_amount) }}
        columns={[
          { key: 'employee', header: 'Employee', render: (r) => (<div><Text size="sm" fw={600}>{r.employee?.full_name}</Text><Text size="xs" c="dimmed">{r.employee?.department ?? ''} · {humanize(r.salary_type)}</Text></div>) },
          { key: 'worked', header: 'Worked', visibleFrom: 'md', render: (r) => <Text size="sm" className="tabular">{r.worked_days}/{r.scheduled_days} d · {fmtMinutes(r.worked_minutes)}</Text> },
          { key: 'base', header: 'Base', align: 'right', render: (r) => <span className="tabular">{fmtMoney(r.base_salary, r.currency)}</span> },
          { key: 'overtime', header: 'Overtime', align: 'right', visibleFrom: 'lg', render: (r) => <span className="tabular">{fmtMoney(r.overtime_amount, r.currency)}</span> },
          { key: 'penalty', header: 'Penalties', align: 'right', visibleFrom: 'md', render: (r) => <Text size="sm" className="tabular" c={r.penalty_amount ? 'red.7' : undefined}>{fmtMoney(r.penalty_amount, r.currency)}</Text> },
          { key: 'bonus', header: 'Bonus', align: 'right', visibleFrom: 'md', render: (r) => <span className="tabular">{fmtMoney(r.bonus_amount, r.currency)}</span> },
          { key: 'net', header: 'Net', align: 'right', render: (r) => <Text size="sm" fw={650} className="tabular">{fmtMoney(r.net_amount, r.currency)}</Text> },
          { key: 'status', header: 'Status', render: (r) => <StatusBadge status={r.status} /> },
        ]}
        empty={{ title: 'No payroll records' }}
        minWidth={820}
      />

      <EntityModal opened={!!record} onClose={() => setRecord(null)} title={record?.employee?.full_name ?? 'Payroll record'} size="lg" formId={!locked && can('payroll.calculate') ? 'bonus-form' : undefined} submitLabel="Save bonus" submitting={saveBonus.isPending}>
        {record && (
          <Stack gap="md">
            <DetailList items={[
              { label: 'Pay type', value: `${humanize(record.salary_type)} · ${fmtMoney(record.rate, record.currency)}` },
              { label: 'Scheduled', value: `${record.scheduled_days} days · ${fmtMinutes(record.scheduled_minutes)}` },
              { label: 'Worked', value: `${record.worked_days} days · ${fmtMinutes(record.worked_minutes)}` },
              { label: 'Absences', value: `${record.absence_days} days` },
              { label: 'Late / early leave', value: `${fmtMinutes(record.late_minutes)} / ${fmtMinutes(record.early_leave_minutes)}` },
              { label: 'Overtime', value: `${fmtMinutes(record.overtime_minutes)} · ${fmtMoney(record.overtime_amount, record.currency)}` },
              { label: 'Base pay', value: fmtMoney(record.base_salary, record.currency) },
              { label: 'Gross', value: fmtMoney(record.gross_amount, record.currency) },
              { label: 'Approved penalties', value: fmtMoney(record.penalty_amount, record.currency) },
              { label: 'Net', value: <Text fw={650} className="tabular">{fmtMoney(record.net_amount, record.currency)}</Text> },
            ]} />
            {Boolean(record.details.monthly_mode) && record.salary_type === 'monthly' && (
              <Alert variant="light" color="gray">
                Monthly pay mode: {String(record.details.monthly_mode) === 'fixed' ? 'full salary for the days employed; absences are handled by penalty rules.' : 'salary × worked days ÷ scheduled days.'}
              </Alert>
            )}
            {!locked && can('payroll.calculate') && (
              <form id="bonus-form" onSubmit={(e) => { e.preventDefault(); saveBonus.mutate() }}>
                <NumberInput label="Bonus" value={bonus} onChange={setBonus} min={0} thousandSeparator=" " description="Kept when the month is recalculated." />
              </form>
            )}
          </Stack>
        )}
      </EntityModal>
    </>
  )
}
