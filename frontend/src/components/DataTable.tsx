import type { ReactNode } from 'react'
import { Checkbox, Group, Pagination, Paper, Select, Skeleton, Table, Text, UnstyledButton } from '@mantine/core'
import { IconArrowsSort, IconSortAscending, IconSortDescending } from '@tabler/icons-react'
import type { MantineBreakpoint } from '@mantine/core'
import type { PageMeta } from '@/types/api'
import { EmptyState } from './EmptyState'
import { ErrorState } from './ErrorState'

export interface Column<T> {
  key: string
  header: ReactNode
  render?: (row: T) => ReactNode
  /** Enables server-side sorting on this key. */
  sortable?: boolean
  width?: number | string
  align?: 'left' | 'right' | 'center'
  /** Hide on small screens to keep tables usable on phones. */
  visibleFrom?: MantineBreakpoint
}

interface Props<T> {
  columns: Column<T>[]
  rows: T[] | undefined
  rowKey?: (row: T) => string | number
  loading?: boolean
  error?: unknown
  onRetry?: () => void
  meta?: PageMeta
  onPageChange?: (page: number) => void
  onPerPageChange?: (perPage: number) => void
  sort?: string
  onSortChange?: (sort: string) => void
  onRowClick?: (row: T) => void
  empty?: { title: string; message?: string; action?: ReactNode }
  selection?: { selected: (string | number)[]; onChange: (ids: (string | number)[]) => void; isSelectable?: (row: T) => boolean }
  minWidth?: number
  withPaper?: boolean
}

export function DataTable<T>({
  columns, rows, rowKey = (row) => (row as { id: number }).id, loading, error, onRetry, meta, onPageChange, onPerPageChange,
  sort, onSortChange, onRowClick, empty, selection, minWidth = 720, withPaper = true,
}: Props<T>) {
  const selectableRows = (rows ?? []).filter((r) => selection?.isSelectable?.(r) ?? true)
  const allSelected = selection && selectableRows.length > 0 && selectableRows.every((r) => selection.selected.includes(rowKey(r)))

  const sortIcon = (key: string) => {
    if (sort === key) return <IconSortAscending size={14} />
    if (sort === `-${key}`) return <IconSortDescending size={14} />
    return <IconArrowsSort size={14} opacity={0.35} />
  }

  const header = (
    <Table.Thead>
      <Table.Tr>
        {selection && (
          <Table.Th w={40}>
            <Checkbox
              aria-label="Select all rows"
              checked={!!allSelected}
              indeterminate={!allSelected && selection.selected.length > 0}
              onChange={() => selection.onChange(allSelected ? [] : selectableRows.map(rowKey))}
            />
          </Table.Th>
        )}
        {columns.map((col) => (
          <Table.Th key={col.key} w={col.width} ta={col.align} visibleFrom={col.visibleFrom}
            aria-sort={sort === col.key ? 'ascending' : sort === `-${col.key}` ? 'descending' : undefined}>
            {col.sortable && onSortChange ? (
              <UnstyledButton onClick={() => onSortChange(sort === col.key ? `-${col.key}` : col.key)} fw={600} fz="sm">
                <Group gap={4} wrap="nowrap" justify={col.align === 'right' ? 'flex-end' : undefined}>
                  {col.header}
                  {sortIcon(col.key)}
                </Group>
              </UnstyledButton>
            ) : (
              col.header
            )}
          </Table.Th>
        ))}
      </Table.Tr>
    </Table.Thead>
  )

  let body: ReactNode
  if (error) {
    body = <ErrorState error={error} onRetry={onRetry} />
  } else if (loading && !rows) {
    body = (
      <Table.ScrollContainer minWidth={minWidth}>
        <Table verticalSpacing="sm">
          {header}
          <Table.Tbody>
            {Array.from({ length: 6 }, (_, i) => (
              <Table.Tr key={i}>
                {selection && <Table.Td />}
                {columns.map((c) => (
                  <Table.Td key={c.key} visibleFrom={c.visibleFrom}>
                    <Skeleton h={14} w={`${50 + ((i * 17 + c.key.length * 7) % 45)}%`} />
                  </Table.Td>
                ))}
              </Table.Tr>
            ))}
          </Table.Tbody>
        </Table>
      </Table.ScrollContainer>
    )
  } else if (!rows || rows.length === 0) {
    body = <EmptyState title={empty?.title ?? 'Nothing here yet'} message={empty?.message} action={empty?.action} />
  } else {
    body = (
      <Table.ScrollContainer minWidth={minWidth}>
        <Table verticalSpacing="sm" highlightOnHover={!!onRowClick} style={{ opacity: loading ? 0.6 : 1, transition: 'opacity 120ms' }}>
          {header}
          <Table.Tbody>
            {rows.map((row) => {
              const id = rowKey(row)
              return (
                <Table.Tr
                  key={id}
                  onClick={onRowClick ? () => onRowClick(row) : undefined}
                  style={onRowClick ? { cursor: 'pointer' } : undefined}
                  bg={selection?.selected.includes(id) ? 'var(--mantine-primary-color-light)' : undefined}
                >
                  {selection && (
                    <Table.Td onClick={(e) => e.stopPropagation()}>
                      {(selection.isSelectable?.(row) ?? true) && (
                        <Checkbox
                          aria-label="Select row"
                          checked={selection.selected.includes(id)}
                          onChange={() => selection.onChange(selection.selected.includes(id) ? selection.selected.filter((s) => s !== id) : [...selection.selected, id])}
                        />
                      )}
                    </Table.Td>
                  )}
                  {columns.map((col) => (
                    <Table.Td key={col.key} ta={col.align} visibleFrom={col.visibleFrom}>
                      {col.render ? col.render(row) : String((row as Record<string, unknown>)[col.key] ?? '—')}
                    </Table.Td>
                  ))}
                </Table.Tr>
              )
            })}
          </Table.Tbody>
        </Table>
      </Table.ScrollContainer>
    )
  }

  const footer = meta && meta.total > 0 && (
    <Group justify="space-between" px="md" py="sm" style={{ borderTop: '1px solid var(--app-border)' }} wrap="wrap" gap="sm">
      <Text size="sm" c="dimmed" className="tabular">
        {(meta.current_page - 1) * meta.per_page + 1}–{Math.min(meta.current_page * meta.per_page, meta.total)} of {meta.total}
      </Text>
      <Group gap="sm">
        {onPerPageChange && (
          <Select
            aria-label="Rows per page"
            size="xs"
            w={80}
            data={['10', '25', '50', '100']}
            value={String(meta.per_page)}
            onChange={(v) => v && onPerPageChange(Number(v))}
          />
        )}
        {meta.last_page > 1 && onPageChange && <Pagination size="sm" total={meta.last_page} value={meta.current_page} onChange={onPageChange} siblings={1} />}
      </Group>
    </Group>
  )

  if (!withPaper) {
    return (
      <>
        {body}
        {footer}
      </>
    )
  }

  return (
    <Paper withBorder radius="lg" style={{ overflow: 'hidden', borderColor: 'var(--app-border)' }}>
      {body}
      {footer}
    </Paper>
  )
}
