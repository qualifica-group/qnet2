import { useCallback } from 'react'
import { useNavigate } from 'react-router-dom'
import { PURCHASE_INVOICES_DOMAIN } from '@/features/purchase-invoices/api'
import { usePurchaseInstallmentPaymentActions } from '@/features/purchase-invoice-installments/use-purchase-installment-payment-actions'
import type { RowActionHandler } from '@/features/table/row-actions'

/**
 * Which dialog each row action of the payable installments grid opens: the
 * payment actions are the shared ones, "Open document" goes to the passive
 * invoice the row belongs to (spec 0250 D-10).
 */
export function usePurchaseInstallmentRowActions(onChanged: () => void) {
  const navigate = useNavigate()
  const { openPayment, requestClear, ...payment } = usePurchaseInstallmentPaymentActions(onChanged)

  const handleAction: RowActionHandler = useCallback(
    (action, row) => {
      const id = Number(row.id)
      switch (action.key) {
        case 'record_payment':
          void openPayment(id)
          break
        case 'clear_payment':
          requestClear({ id, sequence: Number(row.sequence) })
          break
        case 'open_document':
          void navigate(`/${PURCHASE_INVOICES_DOMAIN}/${Number(row.purchase_invoice_id)}`)
          break
        default:
          break
      }
    },
    [navigate, openPayment, requestClear],
  )

  return { handleAction, ...payment }
}
