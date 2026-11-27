import { RefreshCw } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { Button } from '@/components/ui/button'
import { useWorkOrderCostsQuoteSync } from '@/features/work-order-costs/use-work-order-costs-quote-sync'
import type { WorkOrderCostOverview } from '@/features/work-order-costs/types'

interface WorkOrderCostsQuoteSyncButtonProps {
  workOrderId: number
  overview: WorkOrderCostOverview
  /** Locks the button while the actual costs are being saved. */
  disabled: boolean
}

/**
 * Spec 0436 D-6: "Sincronizza con l'offerta" in the header of the actual costs
 * rows ("Costi effettivi (N)"). Always asks first; with actual costs already
 * saved the dialog says how many will be replaced; without offer cost lines for
 * the commessa it explains why there is nothing to sync, with Close only. The
 * caller mounts it only with `manage_costs`.
 */
export function WorkOrderCostsQuoteSyncButton({ workOrderId, overview, disabled }: WorkOrderCostsQuoteSyncButtonProps) {
  const { t } = useTranslation()
  const sync = useWorkOrderCostsQuoteSync(workOrderId, overview)
  const hasBudget = sync.budgetLineCount > 0

  const description = !hasBudget
    ? t('workOrders.costs.quoteSync.noBudget')
    : sync.actualCostCount > 0
      ? t('workOrders.costs.quoteSync.replaceDescription', { count: sync.actualCostCount, budget: sync.budgetLineCount })
      : t('workOrders.costs.quoteSync.description', { count: sync.budgetLineCount })

  return (
    <>
      <Button
        type="button"
        variant="outline"
        size="xs"
        className="bg-card"
        disabled={disabled || sync.isSyncing}
        onClick={() => sync.setConfirmOpen(true)}
      >
        <RefreshCw className="size-3.5" aria-hidden="true" />
        {t('workOrders.costs.quoteSync.button')}
      </Button>

      <AlertDialog open={sync.isConfirmOpen} onOpenChange={sync.setConfirmOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{t('workOrders.costs.quoteSync.title')}</AlertDialogTitle>
            <AlertDialogDescription>{description}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={sync.isSyncing}>
              {hasBudget ? t('common.cancel') : t('common.close')}
            </AlertDialogCancel>
            {hasBudget ? (
              // Radix closes on action click by default: keep the dialog up while the request runs.
              <AlertDialogAction
                disabled={sync.isSyncing}
                onClick={(event) => {
                  event.preventDefault()
                  void sync.confirm()
                }}
              >
                {sync.isSyncing ? t('workOrders.costs.quoteSync.syncing') : t('workOrders.costs.quoteSync.confirm')}
              </AlertDialogAction>
            ) : null}
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  )
}
