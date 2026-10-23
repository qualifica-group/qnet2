import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import {
  commissionConfigurationQuoteImpactKey,
  fetchCommissionConfigurationQuoteImpact,
  recalculateConfigurationQuotes,
} from './api'
import type { CommissionConfigurationQuoteImpact, CommissionRecalculationMode } from './types'

/** The `name` row is cosmetic: saving it never changes what a quote's commissions would be. */
const NON_PROPAGATING_FIELD = 'name'

/** The answer NONE: only quote rows created from now on follow the rule (spec 0308 D-6). */
export type PropagationChoice = CommissionRecalculationMode | 'NONE'

/**
 * Spec 0308 D-6/D-7: after an in-place save of a field other than `name`, asks
 * the server what the rule would touch on the open quotes and, when something
 * is touched and the actor may apply it, exposes the impact for the
 * controlled-choice dialog. `NONE` closes without calling the server; the
 * other answers queue the recalculation.
 */
export function useCommissionConfigurationPropagation(configurationId: number) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [impact, setImpact] = useState<CommissionConfigurationQuoteImpact | null>(null)

  const checkImpact = async (savedField: string | null) => {
    if (savedField === NON_PROPAGATING_FIELD) return
    try {
      const found = await queryClient.fetchQuery({
        queryKey: commissionConfigurationQuoteImpactKey(configurationId),
        queryFn: () => fetchCommissionConfigurationQuoteImpact(configurationId),
        staleTime: 0,
      })
      setImpact(found.quotes_count > 0 && found.can_apply ? found : null)
    } catch {
      toast.error(t('commissionConfigurations.propagation.impactError'))
    }
  }

  const recalculate = useMutation<{ quotes_count: number }, AxiosError, CommissionRecalculationMode>({
    mutationFn: (mode) => recalculateConfigurationQuotes(configurationId, mode),
    onSuccess: () => {
      toast.success(t('commissionConfigurations.propagation.started'))
      setImpact(null)
    },
    onError: () => toast.error(t('commissionConfigurations.propagation.error')),
  })

  const answer = (choice: PropagationChoice) => {
    if (choice === 'NONE') {
      setImpact(null)
      return
    }
    recalculate.mutate(choice)
  }

  return { impact, checkImpact, answer, isPending: recalculate.isPending }
}
