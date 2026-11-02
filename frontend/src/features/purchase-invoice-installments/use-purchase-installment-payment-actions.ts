import { useCallback, useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { getPurchaseInstallment, purchaseInstallmentKeys } from '@/features/purchase-invoice-installments/api'
import { useClearPurchasePayment } from '@/features/purchase-invoice-installments/use-purchase-installment-queries'
import type { PurchaseInstallmentDetail } from '@/features/purchase-invoice-installments/types'

const CONFLICT_STATUS = 409

export interface ClearPaymentTarget {
  id: number
  sequence: number
}

/**
 * "Registra pagamento" / "Annulla pagamento" of one payable installment, shared
 * by every surface that lists installments (the payable installments grid, the
 * expanded row and the record of a passive document). "Record payment" needs
 * the whole document (its other installments decide whether a residual can be
 * spread, its company scopes the account select), so the installment is fetched
 * fresh on demand. "Clear payment" only needs the row's own id and sequence.
 */
export function usePurchaseInstallmentPaymentActions(onChanged: () => void) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [payTarget, setPayTarget] = useState<PurchaseInstallmentDetail | null>(null)
  const [clearTarget, setClearTarget] = useState<ClearPaymentTarget | null>(null)
  const { mutate: clearPayment, isPending: isClearing } = useClearPurchasePayment()

  // Fresh read every time: a payment may have landed since the list loaded.
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
    openPayment,
    payTarget,
    closePay: useCallback(() => setPayTarget(null), []),
    requestClear: setClearTarget,
    clearSequence: clearTarget?.sequence ?? null,
    closeClear: useCallback(() => setClearTarget(null), []),
    confirmClear,
    isClearing,
    busyInstallmentId: isClearing ? (clearTarget?.id ?? null) : null,
  }
}
