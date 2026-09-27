export interface ApiEnvelope<T> {
  success: boolean
  data: T
  message: string | null
}

export interface PageMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
  [key: string]: unknown
}

export interface Paginated<T, M extends PageMeta = PageMeta> {
  success: boolean
  data: T[]
  meta: M
}

export interface ApiErrorBody {
  success: false
  message: string
  errors: Record<string, string[]>
}

export interface Option {
  value: string
  label: string
}

export type ListParams = Record<string, string | number | boolean | null | undefined | string[]>
