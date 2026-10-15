import { useMemo } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { useRoutedRecordInlineEdit, type InlineSaveRoute } from '@/components/record-form/use-routed-record-inline-edit'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { invoiceKeys, patchInvoice, updateInvoiceDetails } from '@/features/invoices/api'
import { sourceFromInvoice } from '@/features/invoices/invoice-editor-source'
import { buildInvoiceRecordSchema, type InvoiceRecordFormValues } from '@/features/invoices/invoice-schema'
import type { InvoiceWithPermissions } from '@/features/invoices/types'
import { serverMessage } from '@/features/purchase-requests/purchase-request-server-errors'

/** Saved through PATCH /invoices/{id}/details: allowed on registered invoices too. */
const DETAIL_KEYS = ['external_number', 'external_date', 'invoice_tag_id', 'deviation', 'internal_note', 'layout_id'] as const

/** Saved through PATCH /invoices/{id}: the proforma header (schedule recomputed server-side). */
const HEADER_KEYS = ['document_date', 'customer_registry_id', 'payment_method_id', 'financial_account_id', 'notes'] as const

/** The external reference is one row: number and date always travel together. */
const EXTERNAL_KEYS = ['external_number', 'external_date'] as const

export function toRecordValues(invoice: InvoiceWithPermissions): InvoiceRecordFormValues {
  return {
    ...sourceFromInvoice(invoice).initialValues,
    external_number: invoice.external_number,
    external_date: invoice.external_date,
    deviation: invoice.deviation === null ? null : Number(invoice.deviation),
  }
}

/**
 * Drives the invoice record's in-place editing (spec 0261): the registration
 * fields save through the details endpoint, the proforma header through the
 * partial invoice PATCH (shared `useRoutedRecordInlineEdit`).
 */
export function useInvoiceInlineEdit(invoice: InvoiceWithPermissions, onChanged?: () => void) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const schema = useMemo(() => buildInvoiceRecordSchema(t), [t])
  const persisted = useMemo(() => toRecordValues(invoice), [invoice])
  const form = useForm<InvoiceRecordFormValues>({ resolver: zodResolver(schema), defaultValues: persisted })

  const routes = useMemo<InlineSaveRoute<InvoiceRecordFormValues, InvoiceWithPermissions>[]>(
    () => [
      { keys: DETAIL_KEYS, together: EXTERNAL_KEYS, save: (payload) => updateInvoiceDetails(invoice.id, payload) },
      { keys: HEADER_KEYS, save: (payload) => patchInvoice(invoice.id, payload) },
    ],
    [invoice.id],
  )

  const { inline } = useRoutedRecordInlineEdit({
    form,
    persisted,
    routes,
    onSaved: (saved) => {
      queryClient.setQueryData(invoiceKeys.detail(saved.id), saved)
      void queryClient.invalidateQueries({ queryKey: invoiceKeys.all })
      toast.success(t('invoices.record.saved'))
      onChanged?.()
      return toRecordValues(saved)
    },
    applyServerErrors: (error, setError) => applyServerValidationErrors(error, setError, [...DETAIL_KEYS, ...HEADER_KEYS]),
    errorMessage: (error) => serverMessage(error) ?? t('invoices.record.saveError'),
    invalidMessage: t('invoices.record.invalid'),
  })

  return { form, inline }
}

/** What the record's editable sections read: the form and the inline state. */
export type InvoiceRecordEditor = ReturnType<typeof useInvoiceInlineEdit>
