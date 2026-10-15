import { useMemo } from 'react'
import { useFieldArray, useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { useRoutedRecordInlineEdit, type InlineSaveRoute } from '@/components/record-form/use-routed-record-inline-edit'
import { useVatRefs } from '@/features/invoices/use-vat-refs'
import {
  patchPurchaseInvoice,
  purchaseInvoiceKeys,
  updatePurchaseInvoiceDetails,
} from '@/features/purchase-invoices/api'
import {
  amountsOf,
  applyPurchaseInvoiceServerErrors,
  newLine,
  toLineWrite,
  vatRefsOf,
} from '@/features/purchase-invoices/purchase-invoice-editor-lines'
import { valuesFromInvoice } from '@/features/purchase-invoices/purchase-invoice-editor-source'
import type { NewLineFields } from '@/features/purchase-invoices/purchase-invoice-editor-add-line'
import {
  buildPurchaseInvoiceWriteSchema,
  type PurchaseInvoiceWriteFormValues,
} from '@/features/purchase-invoices/purchase-invoice-schema'
import type { PurchaseInvoice } from '@/features/purchase-invoices/types'
import { serverMessage } from '@/features/purchase-requests/purchase-request-server-errors'

/** Saved through PATCH /purchase-invoices/{id}/details: allowed on registered invoices too. */
const DETAIL_KEYS = ['document_number', 'notes'] as const

/** Saved through PATCH /purchase-invoices/{id}: the proforma header (schedule replanned server-side). */
const HEADER_KEYS = [
  'document_date',
  'payment_method_id',
  'financial_account_id',
  'company_site_id',
  'operational_site_id',
  'cost_type_id',
  'work_order_id',
] as const

/**
 * Drives the passive invoice record's in-place editing: supplier number and
 * notes save through the details endpoint, the proforma header through the
 * partial PATCH (spec 0262), each line through the same PATCH with the whole
 * `lines` set (spec 0263), on the shared `useRoutedRecordInlineEdit`.
 */
export function usePurchaseInvoiceInlineEdit(invoice: PurchaseInvoice, onChanged?: () => void) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const schema = useMemo(() => buildPurchaseInvoiceWriteSchema(t), [t])
  const persisted = useMemo(() => valuesFromInvoice(invoice), [invoice])
  // `values`: a change made elsewhere (a payment) re-syncs the form on the new document.
  const form = useForm<PurchaseInvoiceWriteFormValues>({ resolver: zodResolver(schema), values: persisted })
  const lineArray = useFieldArray({ control: form.control, name: 'lines' })
  const { vatRefs, rememberVat } = useVatRefs(vatRefsOf(invoice.lines))
  const lines = useWatch({ control: form.control, name: 'lines' })
  const lineAmounts = useMemo(() => amountsOf(lines, vatRefs).lines, [lines, vatRefs])

  const routes = useMemo<InlineSaveRoute<PurchaseInvoiceWriteFormValues, PurchaseInvoice>[]>(
    () => [
      { keys: DETAIL_KEYS, save: (payload) => updatePurchaseInvoiceDetails(invoice.id, payload) },
      { keys: HEADER_KEYS, save: (payload) => patchPurchaseInvoice(invoice.id, payload) },
    ],
    [invoice.id],
  )

  const { inline, startNewLine, removeLine } = useRoutedRecordInlineEdit({
    form,
    persisted,
    routes,
    lines: { save: (values) => patchPurchaseInvoice(invoice.id, { lines: values.map(toLineWrite) }) },
    onSaved: (saved) => {
      queryClient.setQueryData(purchaseInvoiceKeys.detail(saved.id), saved)
      void queryClient.invalidateQueries({ queryKey: purchaseInvoiceKeys.all })
      toast.success(t('purchaseInvoiceViews.record.saved'))
      onChanged?.()
      return valuesFromInvoice(saved)
    },
    applyServerErrors: applyPurchaseInvoiceServerErrors,
    errorMessage: (error) => serverMessage(error) ?? t('purchaseInvoiceViews.record.saveError'),
    invalidMessage: t('purchaseInvoiceViews.record.invalid'),
  })

  /** Opens the chosen line in edit mode; the header cost type and work order are only proposed. */
  const addLine = (fields: NewLineFields) =>
    startNewLine(() =>
      lineArray.append(
        newLine(fields, { cost_type_id: form.getValues('cost_type_id'), work_order_id: form.getValues('work_order_id') }),
      ),
    )

  return { form, inline, lineArray, lineAmounts, rememberVat, addLine, removeLine }
}

/** What the record's editable sections read: the form, the inline state and the line actions. */
export type PurchaseInvoiceRecordEditor = ReturnType<typeof usePurchaseInvoiceInlineEdit>
