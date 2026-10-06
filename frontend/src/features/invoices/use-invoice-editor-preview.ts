import { useMemo } from 'react'
import { useDebouncedValue } from '@/hooks/use-debounced-value'
import { fromCents, type InvoiceTotalsCents } from '@/features/invoices/invoice-amounts'
import { idToSelectValue } from '@/features/invoices/invoice-editor-source'
import { useInstallmentPreview } from '@/features/invoices/use-invoice-queries'
import type { InstallmentPreviewPayload } from '@/features/invoices/types'

export const PREVIEW_DEBOUNCE_MS = 400

interface PreviewInputs {
  documentDate: string
  paymentMethodId: number
  totals: InvoiceTotalsCents
}

/**
 * Installment schedule for the modal, fed by the live totals. Debounced so a
 * burst of edits costs one request; idle until date, payment and a positive
 * total are present (the server rejects anything else with a 422).
 */
export function useInvoiceEditorPreview({ documentDate, paymentMethodId, totals }: PreviewInputs) {
  const payload = useMemo((): InstallmentPreviewPayload | null => {
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
    }
  }, [documentDate, paymentMethodId, totals.net, totals.vat, totals.total])

  const key = useDebouncedValue(JSON.stringify(payload), PREVIEW_DEBOUNCE_MS)
  const debounced = useMemo(() => JSON.parse(key) as InstallmentPreviewPayload | null, [key])

  return useInstallmentPreview(debounced)
}
