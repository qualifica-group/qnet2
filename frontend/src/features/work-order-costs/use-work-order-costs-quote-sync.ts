import { useState } from 'react'
import axios from 'axios'
import type { i18n as I18nInstance } from 'i18next'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import type { ApiErrorResponse } from '@/api/types'
import { apiErrorMessage } from '@/lib/forbidden-message'
import { useSyncWorkOrderCostsFromQuote } from '@/features/work-order-costs/use-work-order-costs'
import type { WorkOrderCostOverview } from '@/features/work-order-costs/types'

const HTTP_UNPROCESSABLE = 422

/** A 422 carries the server's reason on `errors.lines` (no cost lines, products no longer usable). */
function syncErrorMessage(error: unknown, fallback: string, i18n: I18nInstance): string {
  if (axios.isAxiosError<Partial<ApiErrorResponse>>(error) && error.response?.status === HTTP_UNPROCESSABLE) {
    return error.response.data?.errors?.lines?.[0] ?? fallback
  }
  return apiErrorMessage(error, fallback, i18n)
}

/**
 * Spec 0436: the "sync from the offer" confirm flow of the actual costs. The
 * offer's budget lines replace every saved actual cost; the dialog stays open
 * while the request runs and closes only on success.
 */
export function useWorkOrderCostsQuoteSync(workOrderId: number, overview: WorkOrderCostOverview) {
  const { t, i18n } = useTranslation()
  const [isConfirmOpen, setConfirmOpen] = useState(false)
  const sync = useSyncWorkOrderCostsFromQuote(workOrderId)

  const confirm = async () => {
    try {
      await sync.mutateAsync()
      setConfirmOpen(false)
      toast.success(t('workOrders.costs.quoteSync.synced'))
    } catch (error) {
      toast.error(syncErrorMessage(error, t('workOrders.costs.quoteSync.error'), i18n))
    }
  }

  return {
    budgetLineCount: overview.budget.allocated_lines.length + overview.budget.unallocated_lines.length,
    actualCostCount: overview.lines.length,
    isConfirmOpen,
    setConfirmOpen: (open: boolean) => {
      if (!sync.isPending) setConfirmOpen(open)
    },
    confirm,
    isSyncing: sync.isPending,
  }
}
