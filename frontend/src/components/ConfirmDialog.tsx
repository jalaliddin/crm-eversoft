import { Text } from '@mantine/core'
import { modals } from '@mantine/modals'

interface ConfirmOptions {
  title: string
  message: string
  confirmLabel: string
  danger?: boolean
  onConfirm: () => void | Promise<void>
}

/** Confirmation dialog used before destructive or irreversible actions. */
export function confirmAction({ title, message, confirmLabel, danger = false, onConfirm }: ConfirmOptions) {
  modals.openConfirmModal({
    title,
    children: <Text size="sm">{message}</Text>,
    labels: { confirm: confirmLabel, cancel: 'Cancel' },
    confirmProps: { color: danger ? 'red' : undefined },
    onConfirm: () => void onConfirm(),
  })
}
