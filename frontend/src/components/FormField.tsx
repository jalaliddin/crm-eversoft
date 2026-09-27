import { Controller, type Control, type FieldValues, type Path } from 'react-hook-form'
import { MultiSelect, NumberInput, PasswordInput, Select, Switch, TextInput, Textarea, type ComboboxItem } from '@mantine/core'
import { DateInput, DateTimePicker, TimeInput } from '@mantine/dates'

type Kind = 'text' | 'email' | 'password' | 'textarea' | 'number' | 'select' | 'multiselect' | 'date' | 'datetime' | 'time' | 'switch'

interface Props<T extends FieldValues> {
  control: Control<T>
  name: Path<T>
  label: string
  kind?: Kind
  placeholder?: string
  description?: string
  required?: boolean
  disabled?: boolean
  data?: (ComboboxItem | string)[]
  searchable?: boolean
  clearable?: boolean
  min?: number
  max?: number
  step?: number
  decimalScale?: number
  suffix?: string
  rows?: number
  autoFocus?: boolean
}

/**
 * One form field type for every input kind, bound to React Hook Form.
 * Values are kept in API-friendly shapes (dates as "YYYY-MM-DD", times as "HH:mm").
 */
export function FormField<T extends FieldValues>({ control, name, label, kind = 'text', data = [], ...rest }: Props<T>) {
  const { placeholder, description, required, disabled, searchable, clearable, min, max, step, decimalScale, suffix, rows, autoFocus } = rest

  return (
    <Controller
      control={control}
      name={name}
      render={({ field, fieldState }) => {
        const common = { label, placeholder, description, required, disabled, error: fieldState.error?.message, autoFocus }

        switch (kind) {
          case 'textarea':
            return <Textarea {...common} autosize minRows={rows ?? 3} maxRows={10} value={field.value ?? ''} onChange={field.onChange} onBlur={field.onBlur} />
          case 'number':
            return (
              <NumberInput
                {...common}
                value={field.value ?? ''}
                onChange={(v) => field.onChange(v === '' ? null : Number(v))}
                onBlur={field.onBlur}
                min={min}
                max={max}
                step={step}
                decimalScale={decimalScale}
                thousandSeparator=" "
                suffix={suffix}
                hideControls={!step}
              />
            )
          case 'select':
            return (
              <Select
                {...common}
                data={data}
                value={field.value === null || field.value === undefined || field.value === '' ? null : String(field.value)}
                onChange={(v) => field.onChange(v)}
                searchable={searchable}
                clearable={clearable}
                allowDeselect={clearable}
                nothingFoundMessage="No matches"
                comboboxProps={{ withinPortal: true }}
              />
            )
          case 'multiselect':
            return (
              <MultiSelect
                {...common}
                data={data}
                value={(field.value ?? []).map(String)}
                onChange={field.onChange}
                searchable={searchable}
                clearable
              />
            )
          case 'date':
            return <DateInput {...common} value={field.value || null} onChange={(v) => field.onChange(v ?? null)} valueFormat="DD.MM.YYYY" clearable={clearable} />
          case 'datetime':
            return <DateTimePicker {...common} value={field.value || null} onChange={(v) => field.onChange(v ?? null)} valueFormat="DD.MM.YYYY HH:mm" clearable={clearable} />
          case 'time':
            return <TimeInput {...common} value={field.value ?? ''} onChange={(e) => field.onChange(e.currentTarget.value || null)} />
          case 'switch':
            return <Switch label={label} description={description} disabled={disabled} checked={!!field.value} onChange={(e) => field.onChange(e.currentTarget.checked)} />
          case 'password':
            return <PasswordInput {...common} value={field.value ?? ''} onChange={field.onChange} onBlur={field.onBlur} autoComplete="new-password" />
          default:
            return <TextInput {...common} type={kind === 'email' ? 'email' : 'text'} value={field.value ?? ''} onChange={field.onChange} onBlur={field.onBlur} />
        }
      }}
    />
  )
}
