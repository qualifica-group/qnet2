import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import type { PurchaseOrder } from '@/features/purchase-orders/types'

interface PurchaseOrderFormActionsProps {
  /** The saved ODA the actions operate on; read it from here when adding an action. */
  order: PurchaseOrder
  children?: ReactNode
}

/**
 * EXTENSION POINT of the ODA form header (rendered only for a saved ODA, next
 * to the status badge). The record-level actions that are not a plain
 * save/close/delete live here: change status, merge, activity history, PDF
 * download and send to the supplier (MT-11). Each one gates itself on
 * `order.abilities` (`transitions`, `merge`, `send`, `view_activity`). MT-11
 * renders those buttons in this component's body, so the form itself never
 * changes.
 */
export function PurchaseOrderFormActions({ children }: PurchaseOrderFormActionsProps) {
  const { t } = useTranslation()
  return (
    <div role="group" aria-label={t('purchaseOrderForm.actionsLabel')} className="flex flex-wrap items-center gap-2 empty:hidden">
      {children}
    </div>
  )
}
