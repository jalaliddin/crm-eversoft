import { useMutation, useQueryClient, type QueryKey } from '@tanstack/react-query'
import type { ApiEnvelope } from '@/types/api'
import { notifyError, notifySuccess } from '@/lib/notify'

interface Options<TData, TVars> {
  mutationFn: (vars: TVars) => Promise<ApiEnvelope<TData>>
  /** Query keys to refresh after success. */
  invalidate?: QueryKey[]
  successMessage?: string | ((data: TData) => string)
  onSuccess?: (data: TData, vars: TVars) => void
  /** Leave error display to the caller (e.g. forms mapping 422 to fields). */
  silentError?: boolean
}

export function useApiMutation<TData = unknown, TVars = void>({ mutationFn, invalidate = [], successMessage, onSuccess, silentError }: Options<TData, TVars>) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn,
    onSuccess: async (envelope, vars) => {
      await Promise.all(invalidate.map((queryKey) => queryClient.invalidateQueries({ queryKey })))
      const message = typeof successMessage === 'function' ? successMessage(envelope.data) : (successMessage ?? envelope.message)
      if (message) notifySuccess(message)
      onSuccess?.(envelope.data, vars)
    },
    onError: (error) => {
      if (!silentError) notifyError(error)
    },
  })
}
