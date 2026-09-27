import axios, { AxiosError, type AxiosRequestConfig } from 'axios'
import type { ApiEnvelope, ApiErrorBody, ListParams, Paginated } from '@/types/api'

const TOKEN_KEY = 'eversoft.token'

export const tokenStore = {
  get: (): string | null => {
    try {
      return localStorage.getItem(TOKEN_KEY)
    } catch {
      return null
    }
  },
  set: (token: string) => {
    try {
      localStorage.setItem(TOKEN_KEY, token)
    } catch {
      /* storage unavailable: session lasts for this tab only */
    }
  },
  clear: () => {
    try {
      localStorage.removeItem(TOKEN_KEY)
    } catch {
      /* ignore */
    }
  },
}

/** Normalized API error: message for toasts, field errors for forms. */
export class ApiError extends Error {
  status: number
  errors: Record<string, string[]>

  constructor(message: string, status: number, errors: Record<string, string[]> = {}) {
    super(message)
    this.status = status
    this.errors = errors
  }

  firstError(field: string): string | undefined {
    return this.errors[field]?.[0]
  }
}

export const http = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? '/api/v1',
  headers: { Accept: 'application/json' },
  timeout: 60_000,
})

http.interceptors.request.use((config) => {
  const token = tokenStore.get()
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

http.interceptors.response.use(
  (response) => response,
  (error: AxiosError<ApiErrorBody>) => {
    const status = error.response?.status ?? 0
    const body = error.response?.data

    if (status === 401 && !error.config?.url?.includes('/auth/login')) {
      tokenStore.clear()
      window.dispatchEvent(new CustomEvent('auth:expired'))
    }

    const message =
      body && typeof body === 'object' && 'message' in body && body.message
        ? body.message
        : status === 0
          ? 'Cannot reach the server. Check your connection.'
          : 'Something went wrong. Please try again.'

    return Promise.reject(new ApiError(message, status, (body && typeof body === 'object' && body.errors) || {}))
  },
)

function clean(params?: ListParams) {
  if (!params) return undefined
  return Object.fromEntries(Object.entries(params).filter(([, v]) => v !== undefined && v !== null && v !== ''))
}

export const api = {
  async get<T>(url: string, params?: ListParams): Promise<T> {
    const { data } = await http.get<ApiEnvelope<T>>(url, { params: clean(params) })
    return data.data
  },
  async list<T, M extends Paginated<T>['meta'] = Paginated<T>['meta']>(url: string, params?: ListParams): Promise<Paginated<T, M>> {
    const { data } = await http.get<Paginated<T, M>>(url, { params: clean(params) })
    return data
  },
  async post<T>(url: string, body?: unknown, config?: AxiosRequestConfig): Promise<ApiEnvelope<T>> {
    const { data } = await http.post<ApiEnvelope<T>>(url, body, config)
    return data
  },
  async put<T>(url: string, body?: unknown): Promise<ApiEnvelope<T>> {
    const { data } = await http.put<ApiEnvelope<T>>(url, body)
    return data
  },
  async delete<T = null>(url: string, body?: unknown): Promise<ApiEnvelope<T>> {
    const { data } = await http.delete<ApiEnvelope<T>>(url, { data: body })
    return data
  },
  async upload<T>(url: string, field: string, file: File): Promise<ApiEnvelope<T>> {
    const form = new FormData()
    form.append(field, file)
    const { data } = await http.post<ApiEnvelope<T>>(url, form)
    return data
  },
  /** Authenticated file download (exports, attachments). */
  async download(url: string, params?: ListParams, fallbackName = 'download'): Promise<void> {
    const response = await http.get<Blob>(url, { params: clean(params), responseType: 'blob' })
    const disposition = String(response.headers['content-disposition'] ?? '')
    const match = /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i.exec(disposition)
    const name = match ? decodeURIComponent(match[1]) : fallbackName
    const href = URL.createObjectURL(response.data)
    const link = document.createElement('a')
    link.href = href
    link.download = name
    document.body.appendChild(link)
    link.click()
    link.remove()
    URL.revokeObjectURL(href)
  },
}
