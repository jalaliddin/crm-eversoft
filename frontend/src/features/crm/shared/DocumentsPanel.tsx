import { ActionIcon, Button, FileButton, Group, Table, Text } from '@mantine/core'
import { IconDownload, IconPaperclip, IconTrash } from '@tabler/icons-react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { api } from '@/lib/api'
import { fmtBytes, fmtDateTime } from '@/lib/format'
import { notifyError, notifySuccess } from '@/lib/notify'
import { useApiMutation } from '@/hooks/useApiMutation'
import { EmptyState } from '@/components/EmptyState'
import { LoadingState } from '@/components/LoadingState'
import { confirmAction } from '@/components/ConfirmDialog'
import type { DocumentFile } from '@/types/models'

const ACCEPT = '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt,.ods,.txt,.csv,.rtf,.jpg,.jpeg,.png,.gif,.webp,.zip'

export function DocumentsPanel({ type, id, canWrite }: { type: 'customers' | 'leads' | 'deals' | 'employees'; id: number; canWrite: boolean }) {
  const queryClient = useQueryClient()
  const [uploading, setUploading] = useState(false)
  const key = ['documents', type, id]
  const { data, isLoading } = useQuery({ queryKey: key, queryFn: () => api.get<DocumentFile[]>(`/${type}/${id}/documents`) })
  const remove = useApiMutation<null, number>({ mutationFn: (docId) => api.delete(`/documents/${docId}`), invalidate: [key], successMessage: 'File deleted.' })

  const upload = async (file: File | null) => {
    if (!file) return
    if (file.size > 20 * 1024 * 1024) {
      notifyError(new Error('Files can be up to 20 MB.'))
      return
    }
    setUploading(true)
    try {
      await api.upload(`/${type}/${id}/documents`, 'file', file)
      await queryClient.invalidateQueries({ queryKey: key })
      await queryClient.invalidateQueries({ queryKey: ['timeline'] })
      notifySuccess(`${file.name} uploaded.`)
    } catch (e) {
      notifyError(e)
    } finally {
      setUploading(false)
    }
  }

  return (
    <>
      {canWrite && (
        <Group justify="space-between" mb="sm">
          <Text size="sm" c="dimmed">PDF, Office, images or ZIP · up to 20 MB</Text>
          <FileButton onChange={upload} accept={ACCEPT}>
            {(props) => <Button {...props} size="xs" leftSection={<IconPaperclip size={14} />} loading={uploading}>Upload file</Button>}
          </FileButton>
        </Group>
      )}
      {isLoading ? (
        <LoadingState rows={2} />
      ) : !data?.length ? (
        <EmptyState compact title="No files attached" message="Contracts, invoices and scans attached here stay private to your organization." />
      ) : (
        <Table.ScrollContainer minWidth={520}>
          <Table verticalSpacing="xs">
            <Table.Tbody>
              {data.map((doc) => (
                <Table.Tr key={doc.id}>
                  <Table.Td>
                    <Text size="sm" fw={500}>{doc.original_name}</Text>
                    <Text size="xs" c="dimmed">{fmtBytes(doc.size)} · {doc.uploader?.name ?? '—'} · {fmtDateTime(doc.created_at)}</Text>
                  </Table.Td>
                  <Table.Td ta="right" w={90}>
                    <ActionIcon variant="subtle" aria-label={`Download ${doc.original_name}`} onClick={() => void api.download(`/documents/${doc.id}/download`, undefined, doc.original_name).catch(notifyError)}>
                      <IconDownload size={16} />
                    </ActionIcon>
                    {canWrite && (
                      <ActionIcon
                        variant="subtle"
                        color="red"
                        aria-label={`Delete ${doc.original_name}`}
                        onClick={() => confirmAction({ title: 'Delete file?', message: `${doc.original_name} will be removed.`, confirmLabel: 'Delete', danger: true, onConfirm: () => remove.mutate(doc.id) })}
                      >
                        <IconTrash size={16} />
                      </ActionIcon>
                    )}
                  </Table.Td>
                </Table.Tr>
              ))}
            </Table.Tbody>
          </Table>
        </Table.ScrollContainer>
      )}
    </>
  )
}
