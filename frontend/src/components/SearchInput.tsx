import { useEffect, useState } from 'react'
import { CloseButton, TextInput, type TextInputProps } from '@mantine/core'
import { useDebouncedValue } from '@mantine/hooks'
import { IconSearch } from '@tabler/icons-react'

interface Props extends Omit<TextInputProps, 'value' | 'onChange'> {
  value: string
  onChange: (value: string) => void
  delay?: number
}

/** Debounced search box; typing does not hit the API on every keystroke. */
export function SearchInput({ value, onChange, delay = 350, placeholder = 'Search', ...props }: Props) {
  const [text, setText] = useState(value)
  const [debounced] = useDebouncedValue(text, delay)

  useEffect(() => setText(value), [value])
  useEffect(() => {
    if (debounced !== value) onChange(debounced)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [debounced])

  return (
    <TextInput
      value={text}
      onChange={(e) => setText(e.currentTarget.value)}
      placeholder={placeholder}
      aria-label={placeholder}
      leftSection={<IconSearch size={16} />}
      rightSection={text ? <CloseButton size="sm" aria-label="Clear search" onClick={() => { setText(''); onChange('') }} /> : null}
      miw={220}
      {...props}
    />
  )
}
