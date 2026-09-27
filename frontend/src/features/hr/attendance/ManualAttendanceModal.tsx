import { useState } from 'react'
import { Alert, Select, SimpleGrid, Stack, TextInput, Textarea } from '@mantine/core'
import { DateInput, TimeInput } from '@mantine/dates'
import { useQuery } from '@tanstack/react-query'
import { EntityModal } from '@/components/EntityModal'
import { api } from '@/lib/api'
import { dayjs, today, toTenantIso } from '@/lib/format'
import { useApiMutation } from '@/hooks/useApiMutation'
import { useEmployeesLookup, useOptions } from '@/hooks/useLookups'

interface Expected {
  is_working_day: boolean
  holiday: string | null
  start: string | null
  end: string | null
  grace_minutes: number
}

export function ManualAttendanceModal({ opened, onClose, defaultEmployeeId }: { opened: boolean; onClose: () => void; defaultEmployeeId?: number }) {
  const { data: employees = [] } = useEmployeesLookup()
  const statuses = useOptions('attendance_statuses')
  const [employeeId, setEmployeeId] = useState<string | null>(defaultEmployeeId ? String(defaultEmployeeId) : null)
  const [date, setDate] = useState<string | null>(today())
  const [checkIn, setCheckIn] = useState('')
  const [checkOut, setCheckOut] = useState('')
  const [status, setStatus] = useState<string | null>(null)
  const [notes, setNotes] = useState('')
  const [reason, setReason] = useState('')

  const expected = useQuery({
    queryKey: ['attendance', 'expected', employeeId, date],
    queryFn: () => api.get<Expected>('/attendance/expected', { employee_id: employeeId, date }),
    enabled: !!employeeId && !!date,
  })

  const save = useApiMutation({
    mutationFn: () => api.post('/attendance', {
      employee_id: Number(employeeId),
      date,
      check_in: checkIn ? toTenantIso(`${date} ${checkIn}`) : null,
      check_out: checkOut ? toTenantIso(`${checkIn && checkOut < checkIn ? dayjs(date).add(1, 'day').format('YYYY-MM-DD') : date} ${checkOut}`) : null,
      status,
      notes,
      reason,
    }),
    invalidate: [['attendance'], ['penalties']],
    successMessage: 'Attendance recorded.',
    onSuccess: () => {
      setCheckIn('')
      setCheckOut('')
      setReason('')
      onClose()
    },
  })

  const e = expected.data

  return (
    <EntityModal opened={opened} onClose={onClose} title="Add attendance manually" formId="manual-attendance" submitLabel="Save record" submitting={save.isPending} size="lg">
      <form id="manual-attendance" onSubmit={(ev) => { ev.preventDefault(); save.mutate() }}>
        <Stack gap="sm">
          <SimpleGrid cols={{ base: 1, sm: 2 }} spacing="sm">
            <Select label="Employee" data={employees} value={employeeId} onChange={setEmployeeId} searchable required />
            <DateInput label="Date" value={date} onChange={setDate} valueFormat="DD.MM.YYYY" required maxDate={today()} />
          </SimpleGrid>
          {e && (
            <Alert variant="light" color={e.is_working_day && !e.holiday ? 'khiva' : 'gray'}>
              {e.holiday ? `Holiday: ${e.holiday}.` : !e.is_working_day ? 'Day off in this employee’s schedule.' : e.start ? `Scheduled ${e.start}–${e.end}, grace ${e.grace_minutes} min.` : 'Flexible schedule.'}
            </Alert>
          )}
          <SimpleGrid cols={{ base: 1, sm: 3 }} spacing="sm">
            <TimeInput label="Check-in" value={checkIn} onChange={(ev) => setCheckIn(ev.currentTarget.value)} />
            <TimeInput label="Check-out" value={checkOut} onChange={(ev) => setCheckOut(ev.currentTarget.value)} />
            <Select label="Status" placeholder="Calculated" data={statuses} value={status} onChange={setStatus} clearable />
          </SimpleGrid>
          <Textarea label="Notes" value={notes} onChange={(ev) => setNotes(ev.currentTarget.value)} autosize minRows={2} />
          <TextInput label="Reason" required value={reason} onChange={(ev) => setReason(ev.currentTarget.value)} placeholder="Why is this entered manually?" />
        </Stack>
      </form>
    </EntityModal>
  )
}
