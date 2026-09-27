import { useState } from 'react'
import { Button, Group } from '@mantine/core'
import { IconPlus } from '@tabler/icons-react'
import { useQuery } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { useApiMutation } from '@/hooks/useApiMutation'
import { DataTable } from '@/components/DataTable'
import { useAuth } from '@/features/auth/useAuth'
import type { Activity } from '@/types/models'
import { ActivityForm } from './ActivityForm'
import { activityColumns } from './activityColumns'

/** Activities embedded in customer / lead / deal pages. */
export function ActivitiesPanel({ filter }: { filter: { customer_id?: number; lead_id?: number; deal_id?: number } }) {
  const { can } = useAuth()
  const [editing, setEditing] = useState<Activity | null>(null)
  const [open, setOpen] = useState(false)
  const { data, isLoading, error } = useQuery({ queryKey: ['activities', filter], queryFn: () => api.list<Activity>('/activities', { ...filter, per_page: 50 }) })
  const complete = useApiMutation<Activity, number>({ mutationFn: (id) => api.post(`/activities/${id}/complete`), invalidate: [['activities'], ['timeline']] })

  return (
    <>
      {can('activities.create') && (
        <Group justify="flex-end" mb="sm">
          <Button size="xs" leftSection={<IconPlus size={14} />} onClick={() => { setEditing(null); setOpen(true) }}>
            Add activity
          </Button>
        </Group>
      )}
      <DataTable
        withPaper={false}
        columns={activityColumns((a) => complete.mutate(a.id), false)}
        rows={data?.data}
        loading={isLoading}
        error={error}
        onRowClick={can('activities.update') ? (a) => { setEditing(a); setOpen(true) } : undefined}
        empty={{ title: 'No activities yet', message: 'Log calls, meetings and tasks to keep the history complete.' }}
        minWidth={560}
      />
      <ActivityForm opened={open} onClose={() => setOpen(false)} activity={editing} links={filter} />
    </>
  )
}
