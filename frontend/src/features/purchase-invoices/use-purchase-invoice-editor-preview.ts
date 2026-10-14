import { useMemo } from 'react'
import { useDebouncedValue } from '@/hooks/use-debounced-value'
import { fromCents, type AmountsCents } from '@/lib/amounts/line-amounts'
import { idToSelectValue } from '@/features/purchase-invoices/purchase-invoice-editor-lines'
import { usePurchaseInstallmentPreview } from '@/features/purchase-invoices/use-purchase-invoice-editor-queries'
import type { PurchaseInstallmentPreviewPayload } from '@/features/purchase-invoices/types'

export const PREVIEW_DEBOUNCE_MS = 400

interface PreviewInputs {
  documentDate: string
  paymentMethodId: number
  totals: AmountsCents
  /** Edited document: lets the server answer with the rebalanced plan when it has payments. */
  invoiceId: number | null
}

/**
 * Installment schedule for the editor, fed by the live totals. Debounced so a
 * burst of edits costs one request; idle until date, payment method and a
 * positive total are present (the server rejects anything else with a 422).
 */
export function usePurchaseInvoiceEditorPreview({ documentDate, paymentMethodId, totals, invoiceId }: PreviewInputs) {
  const payload = useMemo((): PurchaseInstallmentPreviewPayload | null => {
    const paymentMethod = idToSelectValue(paymentMethodId)
    if (documentDate === '' || paymentMethod === null || totals.total <= 0) {
      return null
    }
    return {
      document_date: documentDate,
      payment_method_id: paymentMethod,
      net_amount: fromCents(totals.net),
      vat_amount: fromCents(totals.vat),
      total_amount: fromCents(totals.total),
      ...(invoiceId === null ? {} : { purchase_invoice_id: invoiceId }),
    }
  }, [documentDate, paymentMethodId, totals.net, totals.vat, totals.total, invoiceId])

  const key = useDebouncedValue(JSON.stringify(payload), PREVIEW_DEBOUNCE_MS)
  const debounced = useMemo(() => JSON.parse(key) as PurchaseInstallmentPreviewPayload | null, [key])

  return usePurchaseInstallmentPreview(debounced)
}
