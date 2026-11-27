import { useCallback, useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { ListChecks } from 'lucide-react'
import { useAbilities } from '@/features/auth/use-abilities'
import { PurchaseOrderBulkStatusDialog } from '@/features/purchase-orders/purchase-order-bulk-status-dialog'
import { BULK_STATUS_MAX_IDS } from '@/features/purchase-orders/purchase-order-bulk-status'
import type { BulkAction, TableSelection } from '@/features/table/use-bulk-actions-slot'

interface UsePurchaseOrderBulkStatusArgs {
  /** Purges and reloads the grid. */
  refreshGrid: () => void
  /** Empties the grid selection once the change went through. */
  clearSelection: () => void
}

/**
 * "Change status" entry of the ODA bulk-actions menu (spec 0433 D-4): offered
 * only with `changeStatus` or `approve` and a non-empty selection; the
 * per-order permission is decided server-side.
 */
export function usePurchaseOrderBulkStatus({ refreshGrid, clearSelection }: UsePurchaseOrderBulkStatusArgs) {
  const { t } = useTranslation()
  const { can } = useAbilities()
  const [ids, setIds] = useState<number[] | null>(null)
  const allowed = can('purchase-orders.changeStatus') || can('purchase-orders.approve')

  const getBulkActions = useCallback(
    (selection: TableSelection): BulkAction[] => {
      const selected = selection.ids.map(Number)
      if (selected.length === 0) return []
      const tooMany = selected.length > BULK_STATUS_MAX_IDS
      return [
        {
          key: 'change-status',
          label: t('purchaseOrderActions.buttons.status'),
          icon: ListChecks,
          disabled: tooMany,
          disabledReason: tooMany ? t('purchaseOrderActions.bulkStatus.tooMany', { max: BULK_STATUS_MAX_IDS }) : undefined,
          onSelect: () => setIds(selected),
        },
      ]
    },
    [t],
  )

  const handleApplied = useCallback(() => {
    refreshGrid()
    clearSelection()
  }, [refreshGrid, clearSelection])

  // Mounted only while open so each opening starts without a choice
  const dialogSlot: ReactNode =
    ids === null ? null : (
      <PurchaseOrderBulkStatusDialog
        ids={ids}
        onApplied={handleApplied}
        onOpenChange={(open) => {
          if (!open) setIds(null)
        }}
      />
    )

  // Undefined without the permission, so the grid does not even enable the selection column for it
  return { getBulkActions: allowed ? getBulkActions : undefined, dialogSlot }
}
