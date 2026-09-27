import { useState } from 'react'
import { Spotlight, spotlight } from '@mantine/spotlight'
import { Badge, Button, Group, Kbd, Text } from '@mantine/core'
import { useDebouncedValue } from '@mantine/hooks'
import { IconSearch } from '@tabler/icons-react'
import { useQuery } from '@tanstack/react-query'
import { useNavigate } from 'react-router'
import { useTranslation } from 'react-i18next'
import { api } from '@/lib/api'

interface SearchHit {
  type: 'customer' | 'lead' | 'deal' | 'employee'
  id: number
  title: string
  subtitle: string | null
  link: string
}

const TYPE_COLOR = { customer: 'khiva', lead: 'blue', deal: 'violet', employee: 'saffron' } as const

/** Ctrl/⌘+K command palette searching CRM and HR records the user may see. */
export function GlobalSearch() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const [query, setQuery] = useState('')
  const [debounced] = useDebouncedValue(query, 250)

  const { data, isFetching } = useQuery({
    queryKey: ['search', debounced],
    queryFn: () => api.get<SearchHit[]>('/search', { q: debounced }),
    enabled: debounced.trim().length >= 2,
  })

  return (
    <>
      <Button
        variant="default"
        leftSection={<IconSearch size={16} />}
        rightSection={<Kbd size="xs" visibleFrom="md">Ctrl K</Kbd>}
        onClick={() => spotlight.open()}
        justify="space-between"
        w={{ base: 42, sm: 320 }}
        px={{ base: 0, sm: 'md' }}
        aria-label={t('common.searchEverything')}
        styles={{ label: { flex: 1 }, section: { marginInlineEnd: 0 } }}
      >
        <Text size="sm" c="dimmed" visibleFrom="sm" truncate>
          {t('common.search')}
        </Text>
      </Button>

      <Spotlight.Root query={query} onQueryChange={setQuery} shortcut={['mod + K', '/']} scrollable maxHeight={420}>
        <Spotlight.Search placeholder={t('common.searchEverything')} leftSection={<IconSearch size={18} />} />
        <Spotlight.ActionsList>
          {query.trim().length < 2 ? (
            <Spotlight.Empty>Type at least two characters.</Spotlight.Empty>
          ) : data && data.length > 0 ? (
            data.map((hit) => (
              <Spotlight.Action key={`${hit.type}-${hit.id}`} onClick={() => navigate(hit.link)}>
                <Group wrap="nowrap" w="100%" justify="space-between">
                  <div>
                    <Text size="sm" fw={500}>{hit.title}</Text>
                    {hit.subtitle && <Text size="xs" c="dimmed">{hit.subtitle}</Text>}
                  </div>
                  <Badge color={TYPE_COLOR[hit.type]} size="sm">{hit.type}</Badge>
                </Group>
              </Spotlight.Action>
            ))
          ) : (
            <Spotlight.Empty>{isFetching ? 'Searching…' : 'No matches.'}</Spotlight.Empty>
          )}
        </Spotlight.ActionsList>
      </Spotlight.Root>
    </>
  )
}
