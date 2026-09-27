import { useState, type ReactNode } from 'react'
import {
  DndContext, DragOverlay, KeyboardSensor, PointerSensor, useDraggable, useDroppable, useSensor, useSensors,
  type DragEndEvent, type DragStartEvent,
} from '@dnd-kit/core'
import { Badge, Box, Group, Paper, ScrollArea, Stack, Text } from '@mantine/core'

export interface KanbanColumn<T> {
  id: string
  title: string
  color?: string
  items: T[]
  footer?: ReactNode
}

interface Props<T> {
  columns: KanbanColumn<T>[]
  itemId: (item: T) => number
  renderCard: (item: T, dragging: boolean) => ReactNode
  onMove: (item: T, toColumn: string) => void
  canDrag?: boolean
}

function DraggableCard({ id, disabled, children }: { id: number; disabled: boolean; children: ReactNode }) {
  const { attributes, listeners, setNodeRef, isDragging } = useDraggable({ id, disabled })
  return (
    <div ref={setNodeRef} {...listeners} {...attributes} style={{ opacity: isDragging ? 0.35 : 1, cursor: disabled ? 'default' : 'grab', touchAction: 'manipulation' }}>
      {children}
    </div>
  )
}

function DroppableColumn({ id, children }: { id: string; children: ReactNode }) {
  const { setNodeRef, isOver } = useDroppable({ id })
  return (
    <Box
      ref={setNodeRef}
      p={6}
      style={{
        borderRadius: 12,
        minHeight: 120,
        background: isOver ? 'var(--mantine-primary-color-light)' : 'transparent',
        outline: isOver ? '2px dashed var(--mantine-primary-color-filled)' : undefined,
        transition: 'background 120ms',
      }}
    >
      {children}
    </Box>
  )
}

/** Horizontal board; each column scrolls independently. Moves are confirmed by the server (optimistic UI in callers). */
export function KanbanBoard<T>({ columns, itemId, renderCard, onMove, canDrag = true }: Props<T>) {
  const [active, setActive] = useState<T | null>(null)
  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 6 } }), useSensor(KeyboardSensor))
  const all = columns.flatMap((c) => c.items.map((item) => ({ item, column: c.id })))

  const onDragStart = (e: DragStartEvent) => setActive(all.find((x) => itemId(x.item) === e.active.id)?.item ?? null)
  const onDragEnd = (e: DragEndEvent) => {
    setActive(null)
    const found = all.find((x) => itemId(x.item) === e.active.id)
    if (found && e.over && e.over.id !== found.column) onMove(found.item, String(e.over.id))
  }

  return (
    <DndContext sensors={sensors} onDragStart={onDragStart} onDragEnd={onDragEnd} onDragCancel={() => setActive(null)}>
      <ScrollArea type="auto" offsetScrollbars>
        <Group align="flex-start" gap="sm" wrap="nowrap" pb="sm">
          {columns.map((column) => (
            <Paper key={column.id} w={288} miw={288} p="xs" radius="lg" bg="var(--app-muted-surface)" style={{ border: 'none' }}>
              <Group justify="space-between" px={6} pt={4} pb={6} wrap="nowrap">
                <Group gap={8} wrap="nowrap">
                  <Box w={8} h={8} style={{ borderRadius: 4, background: `var(--mantine-color-${column.color ?? 'gray'}-6)` }} />
                  <Text fw={600} size="sm">{column.title}</Text>
                  <Badge size="sm" variant="default" className="tabular">{column.items.length}</Badge>
                </Group>
              </Group>
              {column.footer && <Box px={6} pb={6}>{column.footer}</Box>}
              <DroppableColumn id={column.id}>
                <ScrollArea.Autosize mah="calc(100vh - 300px)" type="hover">
                  <Stack gap={8}>
                    {column.items.map((item) => (
                      <DraggableCard key={itemId(item)} id={itemId(item)} disabled={!canDrag}>
                        {renderCard(item, false)}
                      </DraggableCard>
                    ))}
                    {column.items.length === 0 && (
                      <Text size="xs" c="dimmed" ta="center" py="lg">
                        Drop here
                      </Text>
                    )}
                  </Stack>
                </ScrollArea.Autosize>
              </DroppableColumn>
            </Paper>
          ))}
        </Group>
      </ScrollArea>
      <DragOverlay>{active ? <div style={{ width: 272, transform: 'rotate(1.5deg)' }}>{renderCard(active, true)}</div> : null}</DragOverlay>
    </DndContext>
  )
}
