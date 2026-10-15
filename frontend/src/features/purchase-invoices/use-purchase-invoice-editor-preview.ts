import { useMemo } from 'react'
import { useDebouncedValue } from '@/hooks/use-debounced-value'
import { fromCents, type AmountsCents } from '@/lib/amounts/line-amounts'
import { idToSelectValue } from '@/features/purchase-invoices/purchase-invoice-editor-lines'
import { usePurchaseInstallmentPreview } from '@/features/purchase-invoices/use-purchase-invoice-editor-queries'
import { withholdingsCents, withholdingsWithinTotal } from '@/features/purchase-invoices/purchase-invoice-withholdings'
import type { PurchaseInstallmentPreviewPayload } from '@/features/purchase-invoices/types'

export const PREVIEW_DEBOUNCE_MS = 400

interface PreviewInputs {
  documentDate: string
  paymentMethodId: number
  totals: AmountsCents
  withholdingAmount: number | null
  enasarcoAmount: number | null
}

/**
 * Installment schedule for the editor, fed by the live totals. Debounced so a
 * burst of edits costs one request; idle until date, payment method and a
 * positive total are present (the server rejects anything else with a 422).
 */
export function usePurchaseInvoiceEditorPreview({
  documentDate,
  paymentMethodId,
  totals,
  withholdingAmount,
  enasarcoAmount,
}: PreviewInputs) {
  const payload = useMemo((): PurchaseInstallmentPreviewPayload | null => {
    const paymentMethod = idToSelectValue(paymentMethodId)
    const deductions = withholdingsCents(withholdingAmount, enasarcoAmount)
    const validDeductions = (withholdingAmount ?? 0) >= 0 && (enasarcoAmount ?? 0) >= 0
    if (
      documentDate === '' ||
      paymentMethod === null ||
      totals.total <= 0 ||
      !validDeductions ||
      !withholdingsWithinTotal(deductions, totals.total)
    ) {
      return null
    }
    return {
      document_date: documentDate,
      payment_method_id: paymentMethod,
      net_amount: fromCents(totals.net),
      vat_amount: fromCents(totals.vat),
      total_amount: fromCents(totals.total),
      ...(withholdingAmount === null ? {} : { withholding_amount: withholdingAmount }),
      ...(enasarcoAmount === null ? {} : { enasarco_amount: enasarcoAmount }),
    }
  }, [documentDate, paymentMethodId, totals.net, totals.vat, totals.total, withholdingAmount, enasarcoAmount])

  const key = useDebouncedValue(JSON.stringify(payload), PREVIEW_DEBOUNCE_MS)
  const debounced = useMemo(() => JSON.parse(key) as PurchaseInstallmentPreviewPayload | null, [key])

  return usePurchaseInstallmentPreview(debounced)
}
