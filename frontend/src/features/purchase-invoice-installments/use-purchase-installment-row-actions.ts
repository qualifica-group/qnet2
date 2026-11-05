import { useCallback, useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { apiErrorMessage } from '@/lib/forbidden-message'
import type { CompensationTarget } from '@/features/compensations/types'
import { useNavigate } from 'react-router-dom'
import { PURCHASE_INVOICES_DOMAIN } from '@/features/purchase-invoices/api'
import { getPurchaseInstallment, purchaseInstallmentKeys } from '@/features/purchase-invoice-installments/api'
import { usePurchaseInstallmentPaymentActions } from '@/features/purchase-invoice-installments/use-purchase-installment-payment-actions'
import type { RowActionHandler } from '@/features/table/row-actions'

/**
 * Which dialog each row action of the payable installments grid opens: the
 * payment actions are the shared ones, "Open document" goes to the passive
 * invoice the row belongs to (spec 0250 D-10).
 */
export function usePurchaseInstallmentRowActions(onChanged: () => void) {
  const navigate = useNavigate()
  const { t, i18n } = useTranslation()
  const queryClient = useQueryClient()
  const [compensationTarget, setCompensationTarget] = useState<CompensationTarget | null>(null)
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
        case 'compensate':
          void queryClient
            .fetchQuery({
              queryKey: purchaseInstallmentKeys.detail(id),
              queryFn: () => getPurchaseInstallment(id),
              staleTime: 0,
            })
            .then(
              (detail) =>
                setCompensationTarget({
                  registryId: detail.purchase_invoice.supplier.id,
                  companyId: detail.purchase_invoice.company.id,
                  payableId: id,
                }),
              (error: unknown) =>
                toast.error(apiErrorMessage(error, t('purchaseInvoiceInstallments.lookupError'), i18n)),
            )
          break
        case 'open_document':
          void navigate(`/${PURCHASE_INVOICES_DOMAIN}/${Number(row.purchase_invoice_id)}`)
          break
        default:
          break
      }
    },
    [navigate, openPayment, queryClient, requestClear, t, i18n],
  )

  return {
    handleAction,
    compensationTarget,
    closeCompensation: useCallback(() => setCompensationTarget(null), []),
    ...payment,
  }
}
