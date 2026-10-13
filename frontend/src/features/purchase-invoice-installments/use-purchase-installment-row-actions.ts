import { useCallback, useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { PURCHASE_INVOICES_DOMAIN } from '@/features/purchase-invoices/api'
import { getPurchaseInstallment, purchaseInstallmentKeys } from '@/features/purchase-invoice-installments/api'
import { useClearPurchasePayment } from '@/features/purchase-invoice-installments/use-purchase-installment-queries'
import type { PurchaseInstallmentDetail } from '@/features/purchase-invoice-installments/types'
import type { RowActionHandler } from '@/features/table/row-actions'

const CONFLICT_STATUS = 409

interface ClearTarget {
  id: number
  sequence: number
}

/**
 * Which dialog each row action opens. "Record payment" needs the whole
 * document (its other installments decide whether a residual can be spread,
 * its company scopes the account select), so the installment is fetched fresh
 * on demand. "Clear payment" only needs the row's own id and sequence.
 * "Open document" goes to the passive invoice the row belongs to (spec 0250 D-10).
 */
export function usePurchaseInstallmentRowActions(onChanged: () => void) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const navigate = useNavigate()
  const [editId, setEditId] = useState<number | null>(null)
  const [payTarget, setPayTarget] = useState<PurchaseInstallmentDetail | null>(null)
  const [clearTarget, setClearTarget] = useState<ClearTarget | null>(null)
  const { mutate: clearPayment, isPending: isClearing } = useClearPurchasePayment()

  // Fresh read every time: a payment may have landed since the grid page loaded.
  const openPayment = useCallback(
    async (installmentId: number) => {
      try {
        const detail = await queryClient.fetchQuery({
          queryKey: purchaseInstallmentKeys.detail(installmentId),
          queryFn: () => getPurchaseInstallment(installmentId),
          staleTime: 0,
        })
        if (detail.amount === undefined) {
          throw new Error('amount not visible')
        }
        setPayTarget(detail)
      } catch {
        toast.error(t('purchaseInvoiceInstallments.lookupError'))
      }
    },
    [queryClient, t],
  )

  const handleAction: RowActionHandler = useCallback(
    (action, row) => {
      const id = Number(row.id)
      switch (action.key) {
        case 'edit':
          setEditId(id)
          break
        case 'record_payment':
          void openPayment(id)
          break
        case 'clear_payment':
          setClearTarget({ id, sequence: Number(row.sequence) })
          break
        case 'open_document':
          void navigate(`/${PURCHASE_INVOICES_DOMAIN}/${Number(row.purchase_invoice_id)}`)
          break
        default:
          break
      }
    },
    [navigate, openPayment],
  )

  const confirmClear = useCallback(() => {
    if (clearTarget === null) {
      return
    }
    clearPayment(clearTarget.id, {
      onSuccess: () => {
        toast.success(t('purchaseInvoiceInstallments.clearPayment.cleared'))
        onChanged()
      },
      onError: (error) =>
        toast.error(
          t(
            error.response?.status === CONFLICT_STATUS
              ? 'purchaseInvoiceInstallments.clearPayment.conflict'
              : 'purchaseInvoiceInstallments.clearPayment.genericError',
          ),
        ),
      onSettled: () => setClearTarget(null),
    })
  }, [clearPayment, clearTarget, onChanged, t])

  return {
    handleAction,
    editId,
    closeEdit: useCallback(() => setEditId(null), []),
    payTarget,
    closePay: useCallback(() => setPayTarget(null), []),
    clearSequence: clearTarget?.sequence ?? null,
    closeClear: useCallback(() => setClearTarget(null), []),
    confirmClear,
    isClearing,
  }
}
