import { useQuery } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { useAuth } from '@/features/auth/useAuth'
import type {
  AttendanceDevice, CustomFieldDefinition, Department, MetaOptions, Pipeline, Position, Tag, WorkSchedule,
} from '@/types/models'

const LONG = 5 * 60_000

export function useMeta() {
  return useQuery({ queryKey: ['meta', 'options'], queryFn: () => api.get<MetaOptions>('/meta/options'), staleTime: Infinity })
}

/** Options for a named enum, e.g. useOptions('lead_statuses'). */
export function useOptions(name: string) {
  return useMeta().data?.[name] ?? []
}

export function useUsersLookup() {
  return useQuery({
    queryKey: ['meta', 'users'],
    queryFn: () => api.get<{ id: number; name: string; email: string; avatar_url: string | null }[]>('/meta/users'),
    staleTime: LONG,
    select: (users) => users.map((u) => ({ value: String(u.id), label: u.name })),
  })
}

export function useDepartments() {
  const { can } = useAuth()
  return useQuery({
    queryKey: ['departments', 'all'],
    queryFn: () => api.get<Department[]>('/departments', { all: 1 }),
    staleTime: LONG,
    enabled: can('departments.view'),
  })
}

export function usePositions() {
  const { can } = useAuth()
  return useQuery({ queryKey: ['positions', 'all'], queryFn: () => api.get<Position[]>('/positions', { all: 1 }), staleTime: LONG, enabled: can('positions.view') })
}

export function useSchedules() {
  const { can } = useAuth()
  return useQuery({ queryKey: ['work-schedules'], queryFn: () => api.get<WorkSchedule[]>('/work-schedules'), staleTime: LONG, enabled: can('schedules.view') })
}

export function useEmployeesLookup() {
  const { can } = useAuth()
  return useQuery({
    queryKey: ['employees', 'lookup'],
    queryFn: () => api.get<{ id: number; full_name: string; employee_number: string }[]>('/employees', { all: 1 }),
    staleTime: LONG,
    enabled: can('employees.view'),
    select: (rows) => rows.map((e) => ({ value: String(e.id), label: `${e.full_name} · ${e.employee_number}` })),
  })
}

export function useTags() {
  return useQuery({ queryKey: ['tags'], queryFn: () => api.get<Tag[]>('/tags'), staleTime: LONG })
}

export function usePipelines() {
  const { can } = useAuth()
  return useQuery({ queryKey: ['pipelines'], queryFn: () => api.get<Pipeline[]>('/pipelines'), staleTime: LONG, enabled: can('deals.view') })
}

export function useDevices() {
  const { can } = useAuth()
  return useQuery({ queryKey: ['attendance-devices'], queryFn: () => api.get<AttendanceDevice[]>('/attendance-devices'), enabled: can('devices.view') })
}

export function useCustomFields(entity: CustomFieldDefinition['entity_type']) {
  return useQuery({
    queryKey: ['custom-fields'],
    queryFn: () => api.get<CustomFieldDefinition[]>('/custom-fields'),
    staleTime: LONG,
    select: (rows) => rows.filter((f) => f.entity_type === entity),
  })
}

export const toOptions = <T extends { id: number; name: string }>(rows?: T[]) => (rows ?? []).map((r) => ({ value: String(r.id), label: r.name }))
