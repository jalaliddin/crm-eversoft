import { useState } from 'react'
import { ActionIcon, Button, Group, Paper, Stack, Text, Textarea } from '@mantine/core'
import { IconTrash } from '@tabler/icons-react'
import { useQuery } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { fmtDateTime } from '@/lib/format'
import { useApiMutation } from '@/hooks/useApiMutation'
import { EmptyState } from '@/components/EmptyState'
import { LoadingState } from '@/components/LoadingState'
import { confirmAction } from '@/components/ConfirmDialog'
import { useAuth } from '@/features/auth/useAuth'
import type { Note } from '@/types/models'

export function NotesPanel({ type, id, canWrite }: { type: 'customers' | 'leads' | 'deals' | 'employees'; id: number; canWrite: boolean }) {
  const { me } = useAuth()
  const [body, setBody] = useState('')
  const key = ['notes', type, id]
  const { data, isLoading } = useQuery({ queryKey: key, queryFn: () => api.get<Note[]>(`/${type}/${id}/notes`) })
  const add = useApiMutation({
    mutationFn: () => api.post(`/${type}/${id}/notes`, { body }),
    invalidate: [key, ['timeline']],
    successMessage: 'Note added.',
    onSuccess: () => setBody(''),
  })
  const remove = useApiMutation<null, number>({ mutationFn: (noteId) => api.delete(`/notes/${noteId}`), invalidate: [key], successMessage: 'Note deleted.' })

  return (
    <Stack gap="md">
      {canWrite && (
        <Paper withBorder p="sm" radius="md">
          <Textarea placeholder="Write a note for your team…" autosize minRows={2} value={body} onChange={(e) => setBody(e.currentTarget.value)} aria-label="New note" />
          <Group justify="flex-end" mt="xs">
            <Button size="xs" disabled={!body.trim()} loading={add.isPending} onClick={() => add.mutate()}>
              Add note
            </Button>
          </Group>
        </Paper>
      )}
      {isLoading ? (
        <LoadingState rows={3} />
      ) : !data?.length ? (
        <EmptyState compact title="No notes yet" message="Notes are visible to everyone who can open this record." />
      ) : (
        data.map((note) => (
          <Paper key={note.id} withBorder p="md" radius="md">
            <Group justify="space-between" align="flex-start" wrap="nowrap">
              <Text size="sm" style={{ whiteSpace: 'pre-wrap' }}>{note.body}</Text>
              {(note.created_by === me?.user.id || canWrite) && (
                <ActionIcon
                  variant="subtle"
                  color="gray"
                  aria-label="Delete note"
                  onClick={() => confirmAction({ title: 'Delete note?', message: 'This cannot be undone.', confirmLabel: 'Delete', danger: true, onConfirm: () => remove.mutate(note.id) })}
                >
                  <IconTrash size={16} />
                </ActionIcon>
              )}
            </Group>
            <Text size="xs" c="dimmed" mt={6}>
              {note.author?.name ?? 'Unknown'} · {fmtDateTime(note.created_at)}
            </Text>
          </Paper>
        ))
      )}
    </Stack>
  )
}
