import { useState } from 'react'
import { ActionIcon, Badge, Button, Card, Grid, Group, Select, Stack, Switch, Table, Text, TextInput, Textarea, Title } from '@mantine/core'
import { IconPlus, IconTrash } from '@tabler/icons-react'
import { useQuery } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { humanize } from '@/lib/format'
import { PageHeader } from '@/components/PageHeader'
import { EntityModal } from '@/components/EntityModal'
import { EmptyState } from '@/components/EmptyState'
import { confirmAction } from '@/components/ConfirmDialog'
import { useApiMutation } from '@/hooks/useApiMutation'
import { useOptions, useTags } from '@/hooks/useLookups'
import type { CustomFieldDefinition } from '@/types/models'

const TAG_COLORS = ['gray', 'blue', 'khiva', 'teal', 'green', 'saffron', 'orange', 'red', 'grape', 'violet']

export default function FieldsPage() {
  const { data: tags = [] } = useTags()
  const entities = useOptions('custom_field_entities')
  const fieldTypes = useOptions('custom_field_types')
  const fields = useQuery({ queryKey: ['custom-fields'], queryFn: () => api.get<CustomFieldDefinition[]>('/custom-fields') })
  const [tagName, setTagName] = useState('')
  const [tagColor, setTagColor] = useState<string | null>('blue')
  const [fieldOpen, setFieldOpen] = useState(false)
  const [draft, setDraft] = useState({ entity_type: 'customer', key: '', label: '', field_type: 'text', options: '', is_required: false })

  const addTag = useApiMutation({ mutationFn: () => api.post('/tags', { name: tagName, color: tagColor }), invalidate: [['tags']], successMessage: 'Tag added.', onSuccess: () => setTagName('') })
  const removeTag = useApiMutation<null, number>({ mutationFn: (id) => api.delete(`/tags/${id}`), invalidate: [['tags']] })
  const addField = useApiMutation({
    mutationFn: () => api.post('/custom-fields', {
      ...draft,
      options: draft.field_type === 'select' ? draft.options.split('\n').map((l) => l.trim()).filter(Boolean).map((l) => ({ value: l.toLowerCase().replace(/\W+/g, '_'), label: l })) : null,
    }),
    invalidate: [['custom-fields']],
    successMessage: 'Field added.',
    onSuccess: () => { setFieldOpen(false); setDraft({ ...draft, key: '', label: '', options: '' }) },
  })
  const removeField = useApiMutation<null, number>({ mutationFn: (id) => api.delete(`/custom-fields/${id}`), invalidate: [['custom-fields']], successMessage: 'Field deleted.' })

  return (
    <>
      <PageHeader title="Tags & custom fields" description="Shape records around how your business works." />
      <Grid gap="lg">
        <Grid.Col span={{ base: 12, lg: 5 }}>
          <Card>
            <Title order={3} fz="md" mb="sm">Tags</Title>
            <Group align="flex-end" mb="md" gap="xs">
              <TextInput label="New tag" value={tagName} onChange={(e) => setTagName(e.currentTarget.value)} flex={1} />
              <Select label="Color" data={TAG_COLORS} value={tagColor} onChange={setTagColor} w={120} />
              <Button disabled={!tagName.trim()} onClick={() => addTag.mutate()} loading={addTag.isPending}>Add</Button>
            </Group>
            <Group gap="xs">
              {tags.length === 0 && <Text size="sm" c="dimmed">No tags yet.</Text>}
              {tags.map((t) => (
                <Badge key={t.id} color={t.color} size="lg" rightSection={
                  <ActionIcon size="xs" variant="transparent" color={t.color} aria-label={`Delete tag ${t.name}`} onClick={() => confirmAction({ title: `Delete tag ${t.name}?`, message: 'It is removed from all records.', confirmLabel: 'Delete', danger: true, onConfirm: () => removeTag.mutate(t.id) })}>
                    <IconTrash size={10} />
                  </ActionIcon>
                }>{t.name}</Badge>
              ))}
            </Group>
          </Card>
        </Grid.Col>
        <Grid.Col span={{ base: 12, lg: 7 }}>
          <Card>
            <Group justify="space-between" mb="sm">
              <Title order={3} fz="md">Custom fields</Title>
              <Button size="xs" leftSection={<IconPlus size={14} />} onClick={() => setFieldOpen(true)}>New field</Button>
            </Group>
            {!fields.data?.length ? <EmptyState compact title="No custom fields" message="Add fields such as “Industry” for customers or “Passport number” for employees." /> : (
              <Table verticalSpacing="sm">
                <Table.Thead><Table.Tr><Table.Th>Field</Table.Th><Table.Th>On</Table.Th><Table.Th>Type</Table.Th><Table.Th /></Table.Tr></Table.Thead>
                <Table.Tbody>
                  {fields.data.map((f) => (
                    <Table.Tr key={f.id}>
                      <Table.Td><Text size="sm" fw={600}>{f.label}{f.is_required ? ' *' : ''}</Text><Text size="xs" c="dimmed">{f.key}</Text></Table.Td>
                      <Table.Td>{humanize(f.entity_type)}</Table.Td>
                      <Table.Td>{humanize(f.field_type)}{f.field_type === 'select' ? ` (${f.options.length})` : ''}</Table.Td>
                      <Table.Td ta="right"><ActionIcon variant="subtle" color="red" aria-label="Delete field" onClick={() => confirmAction({ title: `Delete ${f.label}?`, message: 'Values already saved stay in the records but are no longer shown.', confirmLabel: 'Delete', danger: true, onConfirm: () => removeField.mutate(f.id) })}><IconTrash size={16} /></ActionIcon></Table.Td>
                    </Table.Tr>
                  ))}
                </Table.Tbody>
              </Table>
            )}
          </Card>
        </Grid.Col>
      </Grid>
      <EntityModal opened={fieldOpen} onClose={() => setFieldOpen(false)} title="New custom field" formId="field-form" submitting={addField.isPending}>
        <form id="field-form" onSubmit={(e) => { e.preventDefault(); addField.mutate() }}>
          <Stack gap="sm">
            <Select label="Record type" data={entities} value={draft.entity_type} onChange={(v) => v && setDraft({ ...draft, entity_type: v })} />
            <TextInput label="Label" required value={draft.label} onChange={(e) => setDraft({ ...draft, label: e.currentTarget.value })} onBlur={() => !draft.key && setDraft((d) => ({ ...d, key: d.label.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '').replace(/^(\d)/, 'f_$1') }))} />
            <TextInput label="Key" description="Lowercase letters, digits and underscores. Cannot be changed later." required value={draft.key} onChange={(e) => setDraft({ ...draft, key: e.currentTarget.value })} />
            <Select label="Type" data={fieldTypes} value={draft.field_type} onChange={(v) => v && setDraft({ ...draft, field_type: v })} />
            {draft.field_type === 'select' && <Textarea label="Options" description="One per line." autosize minRows={3} value={draft.options} onChange={(e) => setDraft({ ...draft, options: e.currentTarget.value })} />}
            <Switch label="Required" checked={draft.is_required} onChange={(e) => setDraft({ ...draft, is_required: e.currentTarget.checked })} />
          </Stack>
        </form>
      </EntityModal>
    </>
  )
}
