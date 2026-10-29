import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { apiErrorMessage } from '@/lib/forbidden-message'
import type { CommissionRecalculationMode } from '@/features/commission-configurations/types'
import {
  fetchQuoteCommissionRecalculation,
  quoteCommissionRecalculationQueryKey,
  quoteDetailQueryKey,
  recalculateQuoteCommissions,
  type QuoteCommissionRecalculationImpact,
} from '@/features/quotes/api'
import type { QuoteDetailWithPermissions } from '@/features/quotes/types'

/**
 * Spec 0308: what the recalculation would touch (fetched only while the
 * dialog is open) and the synchronous recalculation itself. The refreshed
 * detail replaces the cached one, so commissions, margin and summary update.
 */
export function useQuoteCommissionRecalculation(quoteId: number, open: boolean, onDone: () => void) {
  const { t, i18n } = useTranslation()
  const queryClient = useQueryClient()

  const impact = useQuery<QuoteCommissionRecalculationImpact, AxiosError>({
    queryKey: quoteCommissionRecalculationQueryKey(quoteId),
    queryFn: () => fetchQuoteCommissionRecalculation(quoteId),
    enabled: open,
    staleTime: 0,
    gcTime: 0,
  })

  const recalculate = useMutation<QuoteDetailWithPermissions, AxiosError, CommissionRecalculationMode>({
    mutationFn: (mode) => recalculateQuoteCommissions(quoteId, mode),
    onSuccess: (saved) => {
      queryClient.setQueryData(quoteDetailQueryKey(quoteId), saved)
      toast.success(t('quotes.recalculateCommissions.done'))
      onDone()
    },
    onError: (error) => toast.error(apiErrorMessage(error, t('quotes.recalculateCommissions.error'), i18n)),
  })

  return { impact, recalculate }
}
