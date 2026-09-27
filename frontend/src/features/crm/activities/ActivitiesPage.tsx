import { useState } from 'react'
import { Button, Group, SegmentedControl, Select } from '@mantine/core'
import { IconPlus } from '@tabler/icons-react'
import { useQuery } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { PageHeader } from '@/components/PageHeader'
import { DataTable } from '@/components/DataTable'
import { SearchInput } from '@/components/SearchInput'
import { FilterDrawer } from '@/components/FilterDrawer'
import { useListParams } from '@/hooks/useListParams'
import { useOptions, useUsersLookup } from '@/hooks/useLookups'
import { useApiMutation } from '@/hooks/useApiMutation'
import { Can } from '@/features/auth/guards'
import type { Activity } from '@/types/models'
import { ActivityForm } from './ActivityForm'
import { activityColumns } from './activityColumns'

export default function ActivitiesPage() {
  const list = useListParams({ view: 'open', sort: 'due_at' })
  const { params } = list
  const [editing, setEditing] = useState<Activity | null>(null)
  const [open, setOpen] = useState(false)
  const types = useOptions('activity_types')
  const priorities = useOptions('activity_priorities')
  const { data: users = [] } = useUsersLookup()

  const query = {
    ...list.query,
    view: undefined,
    status: params.view === 'open' ? 'pending' : params.view === 'done' ? 'completed' : params.status,
    overdue: params.view === 'overdue' ? 1 : undefined,
  }
  const { data, isLoading, error, refetch, isFetching } = useQuery({ queryKey: ['activities', 'page', query], queryFn: () => api.list<Activity>('/activities', query) })
  const complete = useApiMutation<Activity, number>({ mutationFn: (id) => api.post(`/activities/${id}/complete`), invalidate: [['activities']], successMessage: 'Marked as done.' })

  return (
    <>
      <PageHeader
        title="Activities"
        description="Tasks, calls, meetings and emails across your customers and deals."
        actions={
          <Can permission="activities.create">
            <Button leftSection={<IconPlus size={16} />} onClick={() => { setEditing(null); setOpen(true) }}>New activity</Button>
          </Can>
        }
      />
      <Group mb="md" gap="sm" wrap="wrap">
        <SegmentedControl
          value={params.view}
          onChange={(view) => list.set({ view })}
          data={[{ value: 'open', label: 'Open' }, { value: 'overdue', label: 'Overdue' }, { value: 'done', label: 'Done' }, { value: 'all', label: 'All' }]}
        />
        <SearchInput value={params.search ?? ''} onChange={(search) => list.set({ search })} placeholder="Search activities" />
        <Select placeholder="Assigned to" data={[{ value: 'me', label: 'Me' }, ...users]} value={params.assigned_to ?? null} onChange={(v) => list.set({ assigned_to: v })} clearable w={180} aria-label="Assigned to" />
        <FilterDrawer activeCount={list.activeCount(['type', 'priority'])} onReset={() => list.reset(['type', 'priority'])}>
          <Select label="Type" data={types} value={params.type ?? null} onChange={(v) => list.set({ type: v })} clearable />
          <Select label="Priority" data={priorities} value={params.priority ?? null} onChange={(v) => list.set({ priority: v })} clearable />
        </FilterDrawer>
      </Group>
      <DataTable
        columns={activityColumns((a) => complete.mutate(a.id))}
        rows={data?.data}
        meta={data?.meta}
        loading={isLoading || isFetching}
        error={error}
        onRetry={() => void refetch()}
        {...list.tableProps}
        onRowClick={(a) => { setEditing(a); setOpen(true) }}
        empty={{ title: params.view === 'overdue' ? 'Nothing overdue' : 'No activities', message: 'Create a task or log a call to get started.' }}
      />
      <ActivityForm opened={open} onClose={() => setOpen(false)} activity={editing} />
    </>
  )
}
