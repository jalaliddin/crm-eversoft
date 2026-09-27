import { useState } from 'react'
import { Alert, Checkbox, NumberInput, Select, Stack, TextInput } from '@mantine/core'
import { useNavigate } from 'react-router'
import { EntityModal } from '@/components/EntityModal'
import { api } from '@/lib/api'
import { useApiMutation } from '@/hooks/useApiMutation'
import { usePipelines } from '@/hooks/useLookups'
import type { Customer, Deal, Lead } from '@/types/models'

export function ConvertLeadModal({ lead, opened, onClose }: { lead: Lead; opened: boolean; onClose: () => void }) {
  const navigate = useNavigate()
  const { data: pipelines = [] } = usePipelines()
  const [createDeal, setCreateDeal] = useState(true)
  const [title, setTitle] = useState(lead.name)
  const [value, setValue] = useState<number | string>(lead.expected_value ?? 0)
  const [pipelineId, setPipelineId] = useState<string | null>(null)

  const convert = useApiMutation<{ lead: Lead; customer: Customer; deal: Deal | null }>({
    mutationFn: () => api.post(`/leads/${lead.id}/convert`, {
      create_deal: createDeal,
      deal_title: title,
      deal_value: Number(value) || 0,
      pipeline_id: pipelineId ? Number(pipelineId) : null,
    }),
    invalidate: [['leads'], ['customers'], ['deals'], ['timeline'], ['dashboard']],
    successMessage: 'Lead converted.',
    onSuccess: (result) => {
      onClose()
      navigate(result.deal ? `/crm/deals/${result.deal.id}` : `/crm/customers/${result.customer.id}`)
    },
  })

  return (
    <EntityModal opened={opened} onClose={onClose} title="Convert lead" formId="convert-form" submitLabel="Convert" submitting={convert.isPending}>
      <form id="convert-form" onSubmit={(e) => { e.preventDefault(); convert.mutate() }}>
        <Stack gap="sm">
          <Alert color="khiva" variant="light">
            {lead.customer
              ? `The lead is already linked to ${lead.customer.display_name}; no new customer is created.`
              : `A customer will be created from ${lead.name}${lead.company_name ? ` (${lead.company_name})` : ''}.`}
          </Alert>
          <Checkbox label="Also create a deal" checked={createDeal} onChange={(e) => setCreateDeal(e.currentTarget.checked)} />
          {createDeal && (
            <>
              <TextInput label="Deal title" value={title} onChange={(e) => setTitle(e.currentTarget.value)} required />
              <NumberInput label="Deal value" value={value} onChange={setValue} min={0} thousandSeparator=" " />
              <Select label="Pipeline" placeholder="Default pipeline" data={pipelines.map((p) => ({ value: String(p.id), label: p.name }))} value={pipelineId} onChange={setPipelineId} clearable />
            </>
          )}
        </Stack>
      </form>
    </EntityModal>
  )
}
