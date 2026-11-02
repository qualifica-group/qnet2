import { useCallback, useState } from 'react'
import { useMutation } from '@tanstack/react-query'
import type { UseFieldArrayReturn, UseFormReturn } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { apiErrorMessage } from '@/lib/forbidden-message'
import type { VatRateRef } from '@/features/invoices/types'
import { fetchPurchaseInvoiceDraft } from '@/features/purchase-invoices/api'
import { lineFromDraft } from '@/features/purchase-invoices/purchase-invoice-editor-lines'
import type { PurchaseInvoiceWriteFormValues } from '@/features/purchase-invoices/purchase-invoice-schema'
import type { PurchaseInvoiceDraft } from '@/features/purchase-invoices/types'

/** Inline notice under the orders list: nothing left to bill on the order. */
export type OrdersNotice = 'empty' | null

interface AddOrdersArgs {
  form: UseFormReturn<PurchaseInvoiceWriteFormValues>
  lineArray: UseFieldArrayReturn<PurchaseInvoiceWriteFormValues, 'lines'>
  rememberVat: (ref: VatRateRef | null) => void
}

/**
 * Adds an invoiceable ODA to the document being created: loads its draft rows
 * (rows already invoiced are excluded by the server), appends the ones not
 * on the form yet and links the order. Empty header fields take the draft's.
 */
export function useAddOrderToInvoice({ form, lineArray, rememberVat }: AddOrdersArgs) {
  const { t, i18n } = useTranslation()
  const { getValues, setValue } = form
  const { append } = lineArray
  const [notice, setNotice] = useState<OrdersNotice>(null)

  const { mutate, isPending } = useMutation({
    mutationFn: (orderId: number) => fetchPurchaseInvoiceDraft([orderId]),
  })

  const apply = useCallback(
    (draft: PurchaseInvoiceDraft) => {
      if (draft.lines.length === 0) {
        setNotice('empty')
        return
      }
      setNotice(null)
      const present = new Set(getValues('lines').map((line) => line.purchase_order_line_id))
      const fresh = draft.lines.filter(
        (line) => line.purchase_order_line_id === null || !present.has(line.purchase_order_line_id),
      )
      fresh.forEach((line) => rememberVat(line.vat_rate))
      append(fresh.map(lineFromDraft))

      const orderIds = new Set([...getValues('purchase_order_ids'), ...draft.purchase_order_ids])
      setValue('purchase_order_ids', [...orderIds], { shouldDirty: true })

      const { defaults } = draft
      if (defaults.payment_method && Number.isNaN(getValues('payment_method_id'))) {
        setValue('payment_method_id', defaults.payment_method.id, { shouldDirty: true })
      }
      if (getValues('company_site_id') === null && defaults.company_site) {
        setValue('company_site_id', defaults.company_site.id, { shouldDirty: true })
      }
      if (getValues('operational_site_id') === null && defaults.operational_site) {
        setValue('operational_site_id', defaults.operational_site.id, { shouldDirty: true })
      }
    },
    [append, getValues, rememberVat, setValue],
  )

  const addOrder = useCallback(
    (orderId: number) =>
      mutate(orderId, {
        onSuccess: apply,
        onError: (error) => toast.error(apiErrorMessage(error, t('purchaseInvoices.editor.orders.loadError'), i18n)),
      }),
    [apply, mutate, t, i18n],
  )

  return { addOrder, isAdding: isPending, notice }
}
