import { useState } from 'react'
import { ActionIcon, Badge, Button, Group, SimpleGrid, Switch, Table, Text, TextInput } from '@mantine/core'
import { IconPencil, IconPlus, IconTrash } from '@tabler/icons-react'
import { useQuery } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { useApiMutation } from '@/hooks/useApiMutation'
import { EntityModal } from '@/components/EntityModal'
import { EmptyState } from '@/components/EmptyState'
import { confirmAction } from '@/components/ConfirmDialog'
import type { CustomerContact } from '@/types/models'

const blank = { first_name: '', last_name: '', position: '', email: '', phone: '', is_primary: false }

export function ContactsPanel({ customerId, canWrite }: { customerId: number; canWrite: boolean }) {
  const key = ['customers', customerId, 'contacts']
  const { data = [] } = useQuery({ queryKey: key, queryFn: () => api.get<CustomerContact[]>(`/customers/${customerId}/contacts`) })
  const [editing, setEditing] = useState<CustomerContact | null>(null)
  const [open, setOpen] = useState(false)
  const [values, setValues] = useState(blank)

  const save = useApiMutation({
    mutationFn: () => (editing ? api.put(`/customers/${customerId}/contacts/${editing.id}`, values) : api.post(`/customers/${customerId}/contacts`, values)),
    invalidate: [key],
    successMessage: 'Contact saved.',
    onSuccess: () => setOpen(false),
  })
  const remove = useApiMutation<null, number>({ mutationFn: (id) => api.delete(`/customers/${customerId}/contacts/${id}`), invalidate: [key], successMessage: 'Contact deleted.' })

  const edit = (c: CustomerContact | null) => {
    setEditing(c)
    setValues(c ? { first_name: c.first_name, last_name: c.last_name ?? '', position: c.position ?? '', email: c.email ?? '', phone: c.phone ?? '', is_primary: c.is_primary } : blank)
    setOpen(true)
  }

  const field = (name: keyof typeof blank, label: string) => (
    <TextInput label={label} value={String(values[name] ?? '')} onChange={(e) => setValues({ ...values, [name]: e.currentTarget.value })} required={name === 'first_name'} />
  )

  return (
    <>
      {canWrite && (
        <Group justify="flex-end" mb="sm">
          <Button size="xs" leftSection={<IconPlus size={14} />} onClick={() => edit(null)}>Add contact</Button>
        </Group>
      )}
      {data.length === 0 ? (
        <EmptyState compact title="No contact people" message="For companies, add the people you talk to: director, accountant, buyer." />
      ) : (
        <Table.ScrollContainer minWidth={520}>
          <Table verticalSpacing="sm">
            <Table.Tbody>
              {data.map((c) => (
                <Table.Tr key={c.id}>
                  <Table.Td>
                    <Group gap="xs">
                      <Text size="sm" fw={500}>{c.first_name} {c.last_name}</Text>
                      {c.is_primary && <Badge size="xs">Primary</Badge>}
                    </Group>
                    <Text size="xs" c="dimmed">{c.position ?? ''}</Text>
                  </Table.Td>
                  <Table.Td className="tabular"><Text size="sm">{c.phone ?? '—'}</Text></Table.Td>
                  <Table.Td><Text size="sm">{c.email ?? '—'}</Text></Table.Td>
                  {canWrite && (
                    <Table.Td ta="right" w={90}>
                      <ActionIcon variant="subtle" aria-label="Edit contact" onClick={() => edit(c)}><IconPencil size={16} /></ActionIcon>
                      <ActionIcon variant="subtle" color="red" aria-label="Delete contact" onClick={() => confirmAction({ title: 'Delete contact?', message: `${c.first_name} will be removed from this customer.`, confirmLabel: 'Delete', danger: true, onConfirm: () => remove.mutate(c.id) })}>
                        <IconTrash size={16} />
                      </ActionIcon>
                    </Table.Td>
                  )}
                </Table.Tr>
              ))}
            </Table.Tbody>
          </Table>
        </Table.ScrollContainer>
      )}
      <EntityModal opened={open} onClose={() => setOpen(false)} title={editing ? 'Edit contact' : 'New contact'} formId="contact-form" submitting={save.isPending}>
        <form id="contact-form" onSubmit={(e) => { e.preventDefault(); save.mutate() }}>
          <SimpleGrid cols={2} spacing="sm">
            {field('first_name', 'First name')}
            {field('last_name', 'Last name')}
            {field('position', 'Position')}
            {field('phone', 'Phone')}
          </SimpleGrid>
          {field('email', 'Email')}
          <Switch mt="sm" label="Primary contact" checked={values.is_primary} onChange={(e) => setValues({ ...values, is_primary: e.currentTarget.checked })} />
        </form>
      </EntityModal>
    </>
  )
}
