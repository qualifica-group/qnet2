import { useMemo } from 'react'
import { useWatch, type UseFormReturn } from 'react-hook-form'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import { useForSelectLabels } from '@/features/for-select/use-for-select'
import { REFERENTS_FOR_SELECT_RESOURCE } from '@/features/referents/for-select-api'
import { VAT_RATES_FOR_SELECT_RESOURCE } from '@/features/vat-rates/for-select-api'
import type {
  AgentFiscalProfileFormValues,
  AgentFiscalProfileSchemaOutput,
} from '@/features/agent-fiscal-profiles/agent-fiscal-profile-schema'
import type { AgentFiscalProfileRefs } from '@/features/agent-fiscal-profiles/types'

const NO_IDS: number[] = []

/** Resolves one picked id to the `{id, name}` its closed row shows, through the for-select hydration. */
function useRelationRef(resource: string, id: number | null): RelationFieldRef | null {
  const ids = useMemo(() => (id === null ? NO_IDS : [id]), [id])
  const labels = useForSelectLabels({ resource, ids })
  const label = id === null ? undefined : labels.get(id)?.label
  return id !== null && label !== undefined ? { id, name: label } : null
}

/** The create draft only holds ids: this gives its closed rows the names (and the pickers their `selected`). */
export function useAgentFiscalProfileDraftRefs(
  form: UseFormReturn<AgentFiscalProfileFormValues, unknown, AgentFiscalProfileSchemaOutput>,
): AgentFiscalProfileRefs {
  const [referentId, vatRateId] = useWatch({ control: form.control, name: ['referent_id', 'vat_rate_id'] })

  return {
    referent: useRelationRef(REFERENTS_FOR_SELECT_RESOURCE, referentId),
    vatRate: useRelationRef(VAT_RATES_FOR_SELECT_RESOURCE, vatRateId),
  }
}
