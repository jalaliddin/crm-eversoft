import type { ReactNode } from 'react'
import { Button, Group, Modal, Stack } from '@mantine/core'

interface Props {
  opened: boolean
  onClose: () => void
  title: ReactNode
  children: ReactNode
  formId?: string
  submitLabel?: string
  submitting?: boolean
  size?: string | number
}

export function EntityModal({ opened, onClose, title, children, formId, submitLabel = 'Save', submitting, size = 'md' }: Props) {
  return (
    <Modal opened={opened} onClose={onClose} title={title} size={size}>
      <Stack gap="md">
        {children}
        {formId && (
          <Group justify="flex-end" gap="xs" mt="xs">
            <Button variant="default" onClick={onClose}>
              Cancel
            </Button>
            <Button type="submit" form={formId} loading={submitting}>
              {submitLabel}
            </Button>
          </Group>
        )}
      </Stack>
    </Modal>
  )
}
