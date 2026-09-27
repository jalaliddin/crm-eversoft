import type { ReactNode } from 'react'
import { Button, Drawer, Group, ScrollArea, Stack } from '@mantine/core'

interface Props {
  opened: boolean
  onClose: () => void
  title: ReactNode
  children: ReactNode
  /** Form id: renders a footer with Cancel / submit wired to that form. */
  formId?: string
  submitLabel?: string
  submitting?: boolean
  size?: string | number
}

/** Side drawer for create/edit forms; keeps the list visible behind it. */
export function EntityDrawer({ opened, onClose, title, children, formId, submitLabel = 'Save', submitting, size = 'lg' }: Props) {
  return (
    <Drawer opened={opened} onClose={onClose} title={title} size={size} scrollAreaComponent={ScrollArea.Autosize} padding="lg">
      <Stack gap="md" pb={formId ? 72 : 0}>
        {children}
      </Stack>
      {formId && (
        <Group
          justify="flex-end"
          gap="xs"
          pos="absolute"
          bottom={0}
          left={0}
          right={0}
          p="md"
          bg="var(--mantine-color-body)"
          style={{ borderTop: '1px solid var(--app-border)' }}
        >
          <Button variant="default" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" form={formId} loading={submitting}>
            {submitLabel}
          </Button>
        </Group>
      )}
    </Drawer>
  )
}
