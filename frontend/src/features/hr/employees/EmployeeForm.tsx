import { useEffect, useState } from 'react'
import { Divider, SimpleGrid } from '@mantine/core'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { EntityDrawer } from '@/components/EntityDrawer'
import { FormField } from '@/components/FormField'
import { api, ApiError } from '@/lib/api'
import { applyServerErrors, nullify } from '@/lib/forms'
import { useDepartments, useEmployeesLookup, useOptions, usePositions, useSchedules, useUsersLookup, toOptions } from '@/hooks/useLookups'
import { useApiMutation } from '@/hooks/useApiMutation'
import { useAuth } from '@/features/auth/useAuth'
import { CustomFieldInputs } from '@/features/crm/shared/CustomFieldInputs'
import type { Employee } from '@/types/models'

const schema = z.object({
  employee_number: z.string().nullable(),
  first_name: z.string().trim().min(1, 'Required'),
  last_name: z.string().trim().min(1, 'Required'),
  middle_name: z.string().nullable(),
  phone: z.string().nullable(),
  email: z.union([z.literal(''), z.string().email('Enter a valid email')]).nullable(),
  birth_date: z.string().nullable(),
  gender: z.string().nullable(),
  address: z.string().nullable(),
  department_id: z.string().nullable(),
  position_id: z.string().nullable(),
  manager_id: z.string().nullable(),
  user_id: z.string().nullable(),
  employment_type: z.string(),
  hire_date: z.string().nullable(),
  termination_date: z.string().nullable(),
  salary_type: z.string(),
  salary_amount: z.number().nullable(),
  status: z.string(),
  work_schedule_id: z.string().nullable(),
  device_identifier: z.string().nullable(),
  notes: z.string().nullable(),
})
type Values = z.infer<typeof schema>

export function EmployeeForm({ opened, onClose, employee, onSaved }: { opened: boolean; onClose: () => void; employee?: Employee | null; onSaved?: (e: Employee) => void }) {
  const { can, me } = useAuth()
  const { data: departments } = useDepartments()
  const { data: positions } = usePositions()
  const { data: schedules } = useSchedules()
  const { data: employees = [] } = useEmployeesLookup()
  const { data: users = [] } = useUsersLookup()
  const employmentTypes = useOptions('employment_types')
  const salaryTypes = useOptions('salary_types')
  const statuses = useOptions('employee_statuses')
  const genders = useOptions('genders')
  const [custom, setCustom] = useState<Record<string, unknown>>({})
  const form = useForm<Values>({ resolver: zodResolver(schema) })
  const canSalary = can('payroll.view')

  useEffect(() => {
    if (!opened) return
    const id = (v: number | null | undefined) => (v ? String(v) : null)
    form.reset({
      employee_number: employee?.employee_number ?? '',
      first_name: employee?.first_name ?? '',
      last_name: employee?.last_name ?? '',
      middle_name: employee?.middle_name ?? '',
      phone: employee?.phone ?? '',
      email: employee?.email ?? '',
      birth_date: employee?.birth_date ?? null,
      gender: employee?.gender ?? null,
      address: employee?.address ?? '',
      department_id: id(employee?.department_id),
      position_id: id(employee?.position_id),
      manager_id: id(employee?.manager_id),
      user_id: id(employee?.user_id),
      employment_type: employee?.employment_type ?? 'full_time',
      hire_date: employee?.hire_date ?? null,
      termination_date: employee?.termination_date ?? null,
      salary_type: employee?.salary_type ?? 'monthly',
      salary_amount: employee?.salary_amount ?? null,
      status: employee?.status ?? 'active',
      work_schedule_id: id(employee?.work_schedule?.id) ?? id(schedules?.find((s) => s.is_default)?.id),
      device_identifier: '',
      notes: employee?.notes ?? '',
    })
    setCustom(employee?.custom_fields ?? {})
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [opened, employee])

  const save = useApiMutation<Employee, Values>({
    mutationFn: (values) => {
      const toId = (v: string | null) => (v ? Number(v) : null)
      const payload: Record<string, unknown> = {
        ...nullify(values),
        department_id: toId(values.department_id),
        position_id: toId(values.position_id),
        manager_id: toId(values.manager_id),
        user_id: toId(values.user_id),
        work_schedule_id: toId(values.work_schedule_id),
        custom_fields: custom,
      }
      if (!values.employee_number) delete payload.employee_number
      if (!canSalary) {
        delete payload.salary_amount
        delete payload.salary_type
      }
      if (employee) delete payload.device_identifier
      return employee ? api.put(`/employees/${employee.id}`, payload) : api.post('/employees', payload)
    },
    invalidate: [['employees'], ['attendance']],
    successMessage: employee ? 'Employee saved.' : 'Employee added.',
    onSuccess: (data) => {
      onSaved?.(data)
      onClose()
    },
    silentError: true,
  })

  return (
    <EntityDrawer opened={opened} onClose={onClose} title={employee ? `Edit ${employee.full_name}` : 'New employee'} size="xl" formId="employee-form" submitting={save.isPending} submitLabel={employee ? 'Save changes' : 'Add employee'}>
      <form id="employee-form" onSubmit={form.handleSubmit((v) => save.mutate(v, { onError: (e) => { if (!(e instanceof ApiError)) return; applyServerErrors(e, form.setError) } }))}>
        <SimpleGrid cols={{ base: 1, sm: 3 }} spacing="sm">
          <FormField control={form.control} name="last_name" label="Last name" required autoFocus />
          <FormField control={form.control} name="first_name" label="First name" required />
          <FormField control={form.control} name="middle_name" label="Middle name" />
          <FormField control={form.control} name="phone" label="Phone" />
          <FormField control={form.control} name="email" label="Email" kind="email" />
          <FormField control={form.control} name="birth_date" label="Birth date" kind="date" clearable />
          <FormField control={form.control} name="gender" label="Gender" kind="select" data={genders} clearable />
          <FormField control={form.control} name="employee_number" label="Employee No." placeholder="Assigned automatically" />
          <FormField control={form.control} name="status" label="Status" kind="select" data={statuses} />
        </SimpleGrid>
        <FormField control={form.control} name="address" label="Address" />

        <Divider label="Employment" labelPosition="left" my="md" />
        <SimpleGrid cols={{ base: 1, sm: 3 }} spacing="sm">
          <FormField control={form.control} name="department_id" label="Department" kind="select" data={toOptions(departments)} searchable clearable />
          <FormField control={form.control} name="position_id" label="Position" kind="select" data={toOptions(positions)} searchable clearable />
          <FormField control={form.control} name="manager_id" label="Manager" kind="select" data={employees.filter((e) => e.value !== String(employee?.id))} searchable clearable />
          <FormField control={form.control} name="employment_type" label="Employment type" kind="select" data={employmentTypes} />
          <FormField control={form.control} name="hire_date" label="Hire date" kind="date" clearable />
          <FormField control={form.control} name="termination_date" label="Termination date" kind="date" clearable />
          <FormField control={form.control} name="work_schedule_id" label="Work schedule" kind="select" data={toOptions(schedules)} clearable description={employee ? 'Changing it starts the new schedule today.' : undefined} />
          {!employee && <FormField control={form.control} name="device_identifier" label="Terminal ID (employeeNo)" description="The number this person has on the attendance terminal." />}
          <FormField control={form.control} name="user_id" label="Login account" kind="select" data={users} searchable clearable description="Lets the employee see their own attendance." />
        </SimpleGrid>

        {canSalary && (
          <>
            <Divider label="Pay" labelPosition="left" my="md" />
            <SimpleGrid cols={{ base: 1, sm: 3 }} spacing="sm">
              <FormField control={form.control} name="salary_type" label="Paid" kind="select" data={salaryTypes} />
              <FormField control={form.control} name="salary_amount" label="Rate" kind="number" min={0} suffix={` ${me?.organization?.currency ?? ''}`} description={form.watch('salary_type') === 'hourly' ? 'Per hour' : form.watch('salary_type') === 'daily' ? 'Per worked day' : 'Per month'} />
            </SimpleGrid>
          </>
        )}

        <FormField control={form.control} name="notes" label="Notes" kind="textarea" />
        <CustomFieldInputs entity="employee" value={custom} onChange={setCustom} />
      </form>
    </EntityDrawer>
  )
}
