import { useMemo } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { useRoutedRecordInlineEdit, type InlineSaveRoute } from '@/components/record-form/use-routed-record-inline-edit'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import {
  patchPurchaseInvoice,
  purchaseInvoiceKeys,
  updatePurchaseInvoiceDetails,
} from '@/features/purchase-invoices/api'
import { sourceFromInvoice } from '@/features/purchase-invoices/purchase-invoice-editor-source'
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
 * Drives the passive invoice record's in-place editing (spec 0262): supplier
 * number and notes save through the details endpoint, the proforma header
 * through the partial PATCH (shared `useRoutedRecordInlineEdit`).
 */
export function usePurchaseInvoiceInlineEdit(invoice: PurchaseInvoice, onChanged?: () => void) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const schema = useMemo(() => buildPurchaseInvoiceWriteSchema(t), [t])
  const persisted = useMemo(() => sourceFromInvoice(invoice).initialValues, [invoice])
  // `values`: a change made elsewhere (the lines editor, a payment) re-syncs the form on the new document.
  const form = useForm<PurchaseInvoiceWriteFormValues>({ resolver: zodResolver(schema), values: persisted })

  const routes = useMemo<InlineSaveRoute<PurchaseInvoiceWriteFormValues, PurchaseInvoice>[]>(
    () => [
      { keys: DETAIL_KEYS, save: (payload) => updatePurchaseInvoiceDetails(invoice.id, payload) },
      { keys: HEADER_KEYS, save: (payload) => patchPurchaseInvoice(invoice.id, payload) },
    ],
    [invoice.id],
  )

  const { inline } = useRoutedRecordInlineEdit({
    form,
    persisted,
    routes,
    onSaved: (saved) => {
      queryClient.setQueryData(purchaseInvoiceKeys.detail(saved.id), saved)
      void queryClient.invalidateQueries({ queryKey: purchaseInvoiceKeys.all })
      toast.success(t('purchaseInvoiceViews.record.saved'))
      onChanged?.()
      return sourceFromInvoice(saved).initialValues
    },
    applyServerErrors: (error, setError) => applyServerValidationErrors(error, setError, [...DETAIL_KEYS, ...HEADER_KEYS]),
    errorMessage: (error) => serverMessage(error) ?? t('purchaseInvoiceViews.record.saveError'),
    invalidMessage: t('purchaseInvoiceViews.record.invalid'),
  })

  return { form, inline }
}

/** What the record's editable sections read: the form and the inline state. */
export type PurchaseInvoiceRecordEditor = ReturnType<typeof usePurchaseInvoiceInlineEdit>
