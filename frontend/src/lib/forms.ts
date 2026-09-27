import type { FieldValues, Path, UseFormSetError } from 'react-hook-form'
import { ApiError } from './api'
import { notifyError } from './notify'

/** Map Laravel validation errors onto form fields; anything else becomes a toast. */
export function applyServerErrors<T extends FieldValues>(error: unknown, setError: UseFormSetError<T>) {
  if (error instanceof ApiError && error.status === 422 && Object.keys(error.errors).length > 0) {
    let mapped = 0
    for (const [field, messages] of Object.entries(error.errors)) {
      const key = field.replace(/\.(\d+)/g, '.$1') as Path<T>
      setError(key, { type: 'server', message: messages[0] })
      mapped++
    }
    if (mapped > 0) {
      notifyError(new Error(error.message === 'Validation failed.' ? 'Check the highlighted fields.' : error.message))
      return
    }
  }
  notifyError(error)
}

/** Replace empty strings with null so optional API fields are cleared, not sent as "". */
export function nullify<T extends Record<string, unknown>>(values: T): T {
  return Object.fromEntries(Object.entries(values).map(([k, v]) => [k, v === '' ? null : v])) as T
}
