import { useCallback } from 'react'
import { useTranslation } from 'react-i18next'
import { PurchaseClearPaymentDialog } from '@/features/purchase-invoice-installments/purchase-clear-payment-dialog'
import { PurchasePaymentDialog } from '@/features/purchase-invoice-installments/purchase-payment-dialog'
import { usePurchaseInstallmentPaymentActions } from '@/features/purchase-invoice-installments/use-purchase-installment-payment-actions'
import { PurchaseInvoiceDetailInstallments } from '@/features/purchase-invoices/purchase-invoice-detail-installments'
import type { PurchaseInvoice, PurchaseInvoiceInstallment } from '@/features/purchase-invoices/types'

interface PurchaseInvoiceInstallmentsSectionProps {
  invoice: PurchaseInvoice
  /** Called after a payment is recorded or cleared (the host refreshes what lists the document). */
  onChanged?: () => void
}

/**
 * "Scadenze" of a passive document with the payment actions gated by the show
 * permissions, shared by the expanded grid row and the record. The mutations
 * already reload the document query: `onChanged` only refreshes the host's list.
 */
export function PurchaseInvoiceInstallmentsSection({ invoice, onChanged }: PurchaseInvoiceInstallmentsSectionProps) {
  const { t } = useTranslation()
  const handleChanged = useCallback(() => onChanged?.(), [onChanged])
  const actions = usePurchaseInstallmentPaymentActions(handleChanged)
  const { openPayment, requestClear } = actions

  const onRecord = useCallback((installment: PurchaseInvoiceInstallment) => void openPayment(installment.id), [openPayment])
  const onClear = useCallback(
    (installment: PurchaseInvoiceInstallment) => requestClear({ id: installment.id, sequence: installment.sequence }),
    [requestClear],
  )

  return (
    <section className="flex flex-col gap-1.5">
      <h3 className="text-xs font-semibold text-foreground">{t('purchaseInvoiceViews.detail.installments.title')}</h3>
      <PurchaseInvoiceDetailInstallments
        installments={invoice.installments}
        canPay={invoice.permissions.actions.pay === true}
        busyInstallmentId={actions.busyInstallmentId}
        onRecord={onRecord}
        onClear={onClear}
      />
      <PurchasePaymentDialog detail={actions.payTarget} onClose={actions.closePay} onSaved={handleChanged} />
      <PurchaseClearPaymentDialog
        sequence={actions.clearSequence}
        isPending={actions.isClearing}
        onClose={actions.closeClear}
        onConfirm={actions.confirmClear}
      />
    </section>
  )
}
