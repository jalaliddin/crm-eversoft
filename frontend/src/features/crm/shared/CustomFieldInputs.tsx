import { Checkbox, NumberInput, Select, SimpleGrid, TextInput, Textarea } from '@mantine/core'
import { DateInput } from '@mantine/dates'
import { useCustomFields } from '@/hooks/useLookups'
import type { CustomFieldDefinition } from '@/types/models'

interface Props {
  entity: CustomFieldDefinition['entity_type']
  value: Record<string, unknown>
  onChange: (value: Record<string, unknown>) => void
  errors?: Record<string, string>
}

/** Tenant-defined fields rendered from their definitions; validated again on the server. */
export function CustomFieldInputs({ entity, value, onChange, errors = {} }: Props) {
  const { data: fields } = useCustomFields(entity)
  if (!fields?.length) return null

  const set = (key: string, v: unknown) => onChange({ ...value, [key]: v })

  return (
    <SimpleGrid cols={{ base: 1, sm: 2 }} spacing="sm">
      {fields.map((f) => {
        const common = { label: f.label, required: f.is_required, error: errors[`custom_fields.${f.key}`] }
        const v = value[f.key]
        switch (f.field_type) {
          case 'number':
            return <NumberInput key={f.key} {...common} value={(v as number) ?? ''} onChange={(n) => set(f.key, n === '' ? null : n)} />
          case 'date':
            return <DateInput key={f.key} {...common} value={(v as string) || null} onChange={(d) => set(f.key, d)} valueFormat="DD.MM.YYYY" clearable />
          case 'boolean':
            return <Checkbox key={f.key} label={f.label} checked={!!v} onChange={(e) => set(f.key, e.currentTarget.checked)} mt="lg" />
          case 'select':
            return <Select key={f.key} {...common} data={f.options} value={(v as string) ?? null} onChange={(s) => set(f.key, s)} clearable />
          case 'textarea':
            return <Textarea key={f.key} {...common} value={(v as string) ?? ''} onChange={(e) => set(f.key, e.currentTarget.value)} autosize minRows={2} />
          default:
            return <TextInput key={f.key} {...common} value={(v as string) ?? ''} onChange={(e) => set(f.key, e.currentTarget.value)} />
        }
      })}
    </SimpleGrid>
  )
}

export function CustomFieldValues({ entity, values }: { entity: CustomFieldDefinition['entity_type']; values: Record<string, unknown> }) {
  const { data: fields } = useCustomFields(entity)
  if (!fields?.length) return null
  return (
    <>
      {fields.map((f) => {
        const raw = values[f.key]
        const shown = raw === null || raw === undefined || raw === '' ? '—' : f.field_type === 'boolean' ? (raw ? 'Yes' : 'No') : f.field_type === 'select' ? (f.options.find((o) => o.value === raw)?.label ?? String(raw)) : String(raw)
        return { label: f.label, value: shown }
      }).map((row) => (
        <div key={row.label}>
          <dt style={{ color: 'var(--mantine-color-dimmed)', fontSize: 13 }}>{row.label}</dt>
          <dd style={{ margin: 0, fontSize: 14 }}>{row.value}</dd>
        </div>
      ))}
    </>
  )
}
