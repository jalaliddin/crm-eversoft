import { useState } from 'react'
import { Alert, Badge, Button, Divider, Drawer, Group, Select, SimpleGrid, Stack, Text, TextInput, Textarea, Timeline, Title } from '@mantine/core'
import { TimeInput } from '@mantine/dates'
import { IconEdit, IconTrash } from '@tabler/icons-react'
import { useQuery } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { dayjs, fmtDate, fmtDateTime, fmtMinutes, fmtTime, tenantLocal, toTenantIso } from '@/lib/format'
import { useApiMutation } from '@/hooks/useApiMutation'
import { useOptions } from '@/hooks/useLookups'
import { StatusBadge } from '@/components/StatusBadge'
import { LoadingState } from '@/components/LoadingState'
import { ShiftBar } from '@/components/ShiftBar'
import { confirmAction } from '@/components/ConfirmDialog'
import { useAuth } from '@/features/auth/useAuth'
import type { AttendanceRecord } from '@/types/models'
import { DetailList } from '@/features/crm/shared/DetailList'

const FIELD_LABELS: Record<string, string> = {
  actual_check_in: 'Check-in', actual_check_out: 'Check-out', status: 'Status', worked_minutes: 'Worked', late_minutes: 'Late',
  early_leave_minutes: 'Early leave', overtime_minutes: 'Overtime', notes: 'Notes', source: 'Source',
}

function formatValue(key: string, value: unknown): string {
  if (value === null || value === undefined || value === '') return '—'
  if (key.startsWith('actual_')) return fmtTime(String(value))
  if (key.endsWith('_minutes')) return fmtMinutes(Number(value))
  return String(value).replace(/_/g, ' ')
}

/** Record details, schedule vs actual, manual correction with mandatory reason, and the audit trail. */
export function AttendanceRecordDrawer({ recordId, onClose }: { recordId: number | null; onClose: () => void }) {
  const { data: record, isLoading } = useQuery({
    queryKey: ['attendance', 'record', recordId],
    queryFn: () => api.get<AttendanceRecord>(`/attendance/${recordId}`),
    enabled: recordId !== null,
  })

  return (
    <Drawer opened={recordId !== null} onClose={onClose} title={record ? `${record.employee?.full_name} · ${fmtDate(record.date)}` : 'Attendance'} size="lg">
      {isLoading || !record ? <LoadingState /> : <RecordBody key={`${record.id}-${record.edited_at ?? ''}`} record={record} onDeleted={onClose} />}
    </Drawer>
  )
}

function RecordBody({ record, onDeleted }: { record: AttendanceRecord; onDeleted: () => void }) {
  const { can } = useAuth()
  const statuses = useOptions('attendance_statuses')
  const recordId = record.id
  const [editing, setEditing] = useState(false)
  const [checkIn, setCheckIn] = useState(tenantLocal(record.check_in))
  const [checkOut, setCheckOut] = useState(tenantLocal(record.check_out))
  const [status, setStatus] = useState<string | null>(null)
  const [notes, setNotes] = useState(record.notes ?? '')
  const [reason, setReason] = useState('')

  const invalidate = [['attendance'], ['penalties'], ['dashboard']]
  const save = useApiMutation<AttendanceRecord>({
    mutationFn: () => {
      // Wall-clock times in the organization timezone, anchored to the business date.
      // A check-out earlier than the check-in belongs to the next day (overnight shift).
      const date = record.date.slice(0, 10)
      const next = dayjs(date).add(1, 'day').format('YYYY-MM-DD')
      const at = (hm: string, day: string) => (hm ? toTenantIso(`${day} ${hm}`) : null)
      const payload = { check_in: at(checkIn, date), check_out: at(checkOut, checkIn && checkOut < checkIn ? next : date), status: status || null, notes, reason }
      return api.put(`/attendance/${recordId}`, payload)
    },
    invalidate,
    successMessage: 'Attendance corrected.',
    onSuccess: () => setEditing(false),
  })
  const remove = useApiMutation({ mutationFn: () => api.delete(`/attendance/${recordId}`, { reason: reason || 'Deleted by HR' }), invalidate, successMessage: 'Record deleted.', onSuccess: onDeleted })

  return (
        <Stack gap="lg">
          <Group gap="xs">
            <StatusBadge status={record.status} size="lg" />
            <Badge variant="default">{record.source}</Badge>
            {record.is_manual && <Badge color="grape">Corrected</Badge>}
          </Group>

          <Stack gap={6}>
            <ShiftBar
              start={record.scheduled_start ? fmtTime(record.scheduled_start) : null}
              end={record.scheduled_end ? fmtTime(record.scheduled_end) : null}
              checkIn={record.check_in}
              checkOut={record.check_out}
              lateMinutes={record.late_minutes}
              earlyMinutes={record.early_leave_minutes}
            />
          </Stack>

          <DetailList
            items={[
              { label: 'Scheduled', value: record.scheduled_start ? `${fmtTime(record.scheduled_start)}–${fmtTime(record.scheduled_end)}` : 'No fixed hours' },
              { label: 'Schedule', value: record.schedule?.name },
              { label: 'Check-in', value: fmtTime(record.check_in) },
              { label: 'Check-out', value: fmtTime(record.check_out) },
              { label: 'Worked', value: fmtMinutes(record.worked_minutes) },
              { label: 'Overtime', value: fmtMinutes(record.overtime_minutes) },
              { label: 'Late', value: fmtMinutes(record.late_minutes) },
              { label: 'Early leave', value: fmtMinutes(record.early_leave_minutes) },
              { label: 'Device', value: record.device?.name },
              { label: 'Notes', value: record.notes },
            ]}
          />

          {record.is_manual && (
            <Alert color="grape" variant="light">
              Last corrected by {record.edited_by?.name ?? '—'} on {fmtDateTime(record.edited_at)}: “{record.edit_reason}”. Device punches no longer change this record.
            </Alert>
          )}

          {can('attendance.correct') && !editing && (
            <Group>
              <Button leftSection={<IconEdit size={16} />} variant="light" onClick={() => setEditing(true)}>Correct</Button>
              <Button leftSection={<IconTrash size={16} />} variant="subtle" color="red"
                onClick={() => confirmAction({ title: 'Delete this record?', message: 'The deletion is kept in the audit trail.', confirmLabel: 'Delete', danger: true, onConfirm: () => remove.mutate() })}>
                Delete
              </Button>
            </Group>
          )}

          {editing && (
            <form onSubmit={(e) => { e.preventDefault(); save.mutate() }}>
              <Stack gap="sm">
                <Title order={4}>Correct attendance</Title>
                <SimpleGrid cols={2} spacing="sm">
                  <TimeInput label="Check-in" value={checkIn} onChange={(e) => setCheckIn(e.currentTarget.value)} />
                  <TimeInput label="Check-out" value={checkOut} onChange={(e) => setCheckOut(e.currentTarget.value)} />
                </SimpleGrid>
                <Select label="Override status" description="Leave empty to recalculate from the times." data={statuses} value={status} onChange={setStatus} clearable />
                <Textarea label="Notes" value={notes} onChange={(e) => setNotes(e.currentTarget.value)} autosize minRows={2} />
                <TextInput label="Reason for the change" required value={reason} onChange={(e) => setReason(e.currentTarget.value)} placeholder="e.g. Terminal was offline, confirmed by manager" />
                <Group justify="flex-end">
                  <Button variant="default" onClick={() => setEditing(false)}>Cancel</Button>
                  <Button type="submit" loading={save.isPending} disabled={reason.trim().length < 3}>Save correction</Button>
                </Group>
              </Stack>
            </form>
          )}

          {record.audit_logs && record.audit_logs.length > 0 && (
            <>
              <Divider label="Change history" labelPosition="left" />
              <Timeline bulletSize={14} lineWidth={2}>
                {record.audit_logs.map((log) => (
                  <Timeline.Item key={log.id} title={<Text size="sm" fw={600}>{log.action === 'created' ? 'Created manually' : log.action === 'deleted' ? 'Deleted' : 'Corrected'}</Text>}>
                    <Text size="xs" c="dimmed">{log.user?.name ?? 'System'} · {fmtDateTime(log.created_at)}</Text>
                    {log.reason && <Text size="sm" mt={2}>“{log.reason}”</Text>}
                    {Object.keys(log.new_values).filter((k) => FIELD_LABELS[k]).map((key) => (
                      <Text key={key} size="xs" mt={2}>
                        {FIELD_LABELS[key]}: <Text span c="dimmed" td="line-through">{formatValue(key, log.old_values[key])}</Text> → {formatValue(key, log.new_values[key])}
                      </Text>
                    ))}
                  </Timeline.Item>
                ))}
              </Timeline>
            </>
          )}
        </Stack>
  )
}
