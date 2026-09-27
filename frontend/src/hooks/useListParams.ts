import { useCallback, useMemo } from 'react'
import { useSearchParams } from 'react-router'
import type { ListParams } from '@/types/api'

/**
 * List state (page, per_page, sort, search, filters) kept in the URL, so filtered views
 * survive reloads and can be shared as links.
 */
export function useListParams(defaults: Record<string, string> = {}) {
  const [searchParams, setSearchParams] = useSearchParams()

  const params = useMemo(() => {
    const result: Record<string, string> = { ...defaults }
    searchParams.forEach((value, key) => {
      result[key] = value
    })
    return result
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [searchParams])

  const set = useCallback(
    (patch: Record<string, string | number | null | undefined>, resetPage = true) => {
      setSearchParams(
        (prev) => {
          const next = new URLSearchParams(prev)
          for (const [key, value] of Object.entries(patch)) {
            if (value === null || value === undefined || value === '' || value === defaults[key]) next.delete(key)
            else next.set(key, String(value))
          }
          if (resetPage && !('page' in patch)) next.delete('page')
          return next
        },
        { replace: true },
      )
    },
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [setSearchParams],
  )

  const reset = useCallback((keys: string[]) => set(Object.fromEntries(keys.map((k) => [k, null]))), [set])

  const activeCount = (keys: string[]) => keys.filter((k) => params[k] && params[k] !== defaults[k]).length

  return {
    params,
    query: params as ListParams,
    page: Number(params.page ?? 1),
    set,
    reset,
    activeCount,
    tableProps: {
      sort: params.sort,
      onSortChange: (sort: string) => set({ sort }),
      onPageChange: (page: number) => set({ page }, false),
      onPerPageChange: (perPage: number) => set({ per_page: perPage }),
    },
  }
}
