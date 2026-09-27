import { notifications } from '@mantine/notifications'
import { ApiError } from './api'

export function notifySuccess(message: string, title?: string) {
  notifications.show({ color: 'teal', title, message, autoClose: 3500 })
}

export function notifyError(error: unknown, fallback = 'Request failed.') {
  const message = error instanceof ApiError ? error.message : error instanceof Error ? error.message : fallback
  notifications.show({ color: 'red', title: 'Not saved', message, autoClose: 6000 })
}
