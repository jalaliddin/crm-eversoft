import { useState } from 'react'
import { ActionIcon, Badge, Button, Card, Checkbox, ColorSwatch, Grid, Group, NavLink, NumberInput, Select, Stack, Text, TextInput } from '@mantine/core'
import { IconArrowDown, IconArrowUp, IconPlus, IconTrash } from '@tabler/icons-react'
import { api } from '@/lib/api'
import { PageHeader } from '@/components/PageHeader'
import { LoadingState } from '@/components/LoadingState'
import { confirmAction } from '@/components/ConfirmDialog'
import { useApiMutation } from '@/hooks/useApiMutation'
import { usePipelines } from '@/hooks/useLookups'
import type { Pipeline, PipelineStage } from '@/types/models'

type StageDraft = Pick<PipelineStage, 'name' | 'color' | 'probability' | 'type'> & { id?: number }
const COLORS = ['gray', 'blue', 'cyan', 'khiva', 'violet', 'grape', 'orange', 'saffron', 'green', 'teal', 'red']

const NEW_STAGES: StageDraft[] = [
  { name: 'New', color: 'gray', probability: 10, type: 'open' },
  { name: 'In progress', color: 'blue', probability: 50, type: 'open' },
  { name: 'Won', color: 'teal', probability: 100, type: 'won' },
  { name: 'Lost', color: 'red', probability: 0, type: 'lost' },
]

function PipelineEditor({ pipeline, canDelete, onSaved, onDeleted }: { pipeline: Pipeline | null; canDelete: boolean; onSaved: (p: Pipeline) => void; onDeleted: () => void }) {
  const [name, setName] = useState(pipeline?.name ?? 'New pipeline')
  const [isDefault, setIsDefault] = useState(pipeline?.is_default ?? false)
  const [stages, setStages] = useState<StageDraft[]>(
    pipeline ? pipeline.stages.map(({ id, name: n, color, probability, type }) => ({ id, name: n, color, probability, type })) : NEW_STAGES,
  )

  const save = useApiMutation<Pipeline>({
    mutationFn: () => {
      const payload = { name, is_default: isDefault, stages }
      return pipeline ? api.put(`/pipelines/${pipeline.id}`, payload) : api.post('/pipelines', payload)
    },
    invalidate: [['pipelines'], ['deals']],
    successMessage: 'Pipeline saved.',
    onSuccess: onSaved,
  })
  const remove = useApiMutation({ mutationFn: () => api.delete(`/pipelines/${pipeline?.id}`), invalidate: [['pipelines']], successMessage: 'Pipeline deleted.', onSuccess: onDeleted })

  const update = (i: number, patch: Partial<StageDraft>) => setStages((s) => s.map((st, j) => (j === i ? { ...st, ...patch } : st)))
  const move = (i: number, dir: -1 | 1) => setStages((s) => {
    const next = [...s]
    const [item] = next.splice(i, 1)
    next.splice(i + dir, 0, item)
    return next
  })

  return (
    <Card>
      <Stack gap="md">
        <Group align="flex-end">
          <TextInput label="Pipeline name" value={name} onChange={(e) => setName(e.currentTarget.value)} w={300} />
          <Checkbox label="Default pipeline" checked={isDefault} onChange={(e) => setIsDefault(e.currentTarget.checked)} mb={8} />
        </Group>
        <Text size="sm" c="dimmed">Each pipeline needs at least one open, one won and one lost stage. Stages that still hold deals cannot be removed.</Text>
        {stages.map((stage, i) => (
          <Group key={stage.id ?? `new-${i}`} gap="xs" wrap="nowrap" align="flex-end">
            <Stack gap={0}>
              <ActionIcon size="xs" variant="subtle" disabled={i === 0} onClick={() => move(i, -1)} aria-label="Move up"><IconArrowUp size={12} /></ActionIcon>
              <ActionIcon size="xs" variant="subtle" disabled={i === stages.length - 1} onClick={() => move(i, 1)} aria-label="Move down"><IconArrowDown size={12} /></ActionIcon>
            </Stack>
            <TextInput label={i === 0 ? 'Stage' : undefined} value={stage.name} onChange={(e) => update(i, { name: e.currentTarget.value })} flex={1} miw={140} aria-label="Stage name" />
            <Select label={i === 0 ? 'Kind' : undefined} w={110} data={[{ value: 'open', label: 'Open' }, { value: 'won', label: 'Won' }, { value: 'lost', label: 'Lost' }]} value={stage.type} onChange={(v) => v && update(i, { type: v as StageDraft['type'] })} aria-label="Stage kind" />
            <NumberInput label={i === 0 ? 'Probability' : undefined} w={110} value={stage.probability} onChange={(v) => update(i, { probability: Number(v) || 0 })} min={0} max={100} suffix="%" aria-label="Probability" />
            <Select
              label={i === 0 ? 'Color' : undefined}
              w={130}
              data={COLORS}
              value={stage.color}
              onChange={(v) => v && update(i, { color: v })}
              leftSection={<ColorSwatch size={12} color={`var(--mantine-color-${stage.color}-6)`} />}
              aria-label="Color"
            />
            <ActionIcon variant="subtle" color="red" mb={4} onClick={() => setStages((s) => s.filter((_, j) => j !== i))} aria-label="Remove stage" disabled={stages.length <= 3}><IconTrash size={16} /></ActionIcon>
          </Group>
        ))}
        <Group justify="space-between">
          <Button variant="subtle" leftSection={<IconPlus size={14} />} onClick={() => setStages((s) => [...s.filter((x) => x.type === 'open'), { name: 'New stage', color: 'blue', probability: 50, type: 'open' }, ...s.filter((x) => x.type !== 'open')])}>Add stage</Button>
          <Group>
            {pipeline && canDelete && (
              <Button variant="subtle" color="red" onClick={() => confirmAction({ title: 'Delete pipeline?', message: 'Only pipelines without deals can be deleted.', confirmLabel: 'Delete', danger: true, onConfirm: () => remove.mutate() })}>Delete pipeline</Button>
            )}
            <Button onClick={() => save.mutate()} loading={save.isPending}>Save pipeline</Button>
          </Group>
        </Group>
      </Stack>
    </Card>
  )
}

export default function PipelinesPage() {
  const { data: pipelines, isLoading } = usePipelines()
  const [selectedId, setSelectedId] = useState<number | 'new' | null>(null)

  if (isLoading) return <LoadingState />
  const isNew = selectedId === 'new'
  const selected = isNew ? null : pipelines?.find((p) => p.id === selectedId) ?? pipelines?.[0] ?? null

  return (
    <>
      <PageHeader title="Sales pipelines" description="Stages deals move through. Rename, reorder, or add pipelines for different kinds of sales." actions={<Button leftSection={<IconPlus size={16} />} onClick={() => setSelectedId('new')}>New pipeline</Button>} />
      <Grid gap="lg">
        <Grid.Col span={{ base: 12, md: 4, lg: 3 }}>
          <Card p="xs">
            {pipelines?.map((p) => (
              <NavLink key={p.id} label={p.name} description={`${p.stages.length} stages · ${p.deals_count ?? 0} deals`} active={!isNew && selected?.id === p.id} onClick={() => setSelectedId(p.id)}
                rightSection={p.is_default ? <Badge size="xs">Default</Badge> : null} style={{ borderRadius: 8 }} />
            ))}
          </Card>
        </Grid.Col>
        <Grid.Col span={{ base: 12, md: 8, lg: 9 }}>
          <PipelineEditor
            key={isNew ? 'new' : `${selected?.id}-${selected?.stages.map((s) => s.id).join('.')}`}
            pipeline={selected}
            canDelete={(pipelines?.length ?? 0) > 1}
            onSaved={(p) => setSelectedId(p.id)}
            onDeleted={() => setSelectedId(null)}
          />
        </Grid.Col>
      </Grid>
    </>
  )
}
