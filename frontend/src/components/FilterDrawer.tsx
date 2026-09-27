import type { ReactNode } from 'react'
import { Badge, Button, Drawer, Group, Stack } from '@mantine/core'
import { useDisclosure } from '@mantine/hooks'
import { IconAdjustmentsHorizontal } from '@tabler/icons-react'

interface Props {
  activeCount: number
  onReset: () => void
  children: ReactNode
}

/** Secondary filters live in a drawer so list headers stay uncluttered on small screens. */
export function FilterDrawer({ activeCount, onReset, children }: Props) {
  const [opened, { open, close }] = useDisclosure(false)

  return (
    <>
      <Button
        variant="default"
        leftSection={<IconAdjustmentsHorizontal size={16} />}
        rightSection={activeCount > 0 ? <Badge size="sm" circle>{activeCount}</Badge> : null}
        onClick={open}
      >
        Filters
      </Button>
      <Drawer opened={opened} onClose={close} title="Filters" size="sm">
        <Stack gap="md">
          {children}
          <Group justify="space-between" mt="md">
            <Button variant="subtle" color="gray" onClick={onReset}>
              Reset filters
            </Button>
            <Button onClick={close}>Show results</Button>
          </Group>
        </Stack>
      </Drawer>
    </>
  )
}
