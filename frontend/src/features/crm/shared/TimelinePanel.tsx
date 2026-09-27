import { useQuery } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { Timeline } from '@/components/Timeline'
import { LoadingState } from '@/components/LoadingState'
import type { TimelineEvent } from '@/types/models'

export function TimelinePanel({ url }: { url: string }) {
  const { data, isLoading } = useQuery({ queryKey: ['timeline', url], queryFn: () => api.list<TimelineEvent>(url, { per_page: 50 }) })
  return isLoading ? <LoadingState rows={4} /> : <Timeline events={data?.data ?? []} />
}
