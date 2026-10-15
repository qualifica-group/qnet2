import { useMemo } from 'react'
import { useFieldArray, useForm, useWatch, type FieldPath, type UseFormSetError } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useQueryClient } from '@tanstack/react-query'
import axios from 'axios'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { useRoutedRecordInlineEdit, type InlineSaveRoute } from '@/components/record-form/use-routed-record-inline-edit'
import { invoiceKeys, patchInvoice, updateInvoiceDetails } from '@/features/invoices/api'
import { amountsOf, isFormFieldPath, newLine, type NewLineFields } from '@/features/invoices/invoice-editor-lines'
import { valuesFromInvoice, vatRefsOf } from '@/features/invoices/invoice-editor-source'
import { buildInvoiceRecordSchema, type InvoiceRecordFormValues } from '@/features/invoices/invoice-schema'
import type { InvoiceWithPermissions } from '@/features/invoices/types'
import { useVatRefs } from '@/features/invoices/use-vat-refs'
import { serverMessage } from '@/features/purchase-requests/purchase-request-server-errors'

/** Saved through PATCH /invoices/{id}/details: allowed on registered invoices too. */
const DETAIL_KEYS = ['external_number', 'external_date', 'invoice_tag_id', 'deviation', 'internal_note', 'layout_id'] as const

/** Saved through PATCH /invoices/{id}: the proforma header (schedule recomputed server-side). */
const HEADER_KEYS = ['document_date', 'customer_registry_id', 'payment_method_id', 'financial_account_id', 'notes'] as const

/** The external reference is one row: number and date always travel together. */
const EXTERNAL_KEYS = ['external_number', 'external_date'] as const

const RECORD_KEYS: ReadonlySet<string> = new Set([...DETAIL_KEYS, ...HEADER_KEYS])
const UNPROCESSABLE_STATUS = 422

export function toRecordValues(invoice: InvoiceWithPermissions): InvoiceRecordFormValues {
  return {
    ...valuesFromInvoice(invoice),
    external_number: invoice.external_number,
    external_date: invoice.external_date,
    deviation: invoice.deviation === null ? null : Number(invoice.deviation),
  }
}

/**
 * Puts a 422's messages on the record's fields and line inputs; the ones no
 * input carries (e.g. the whole `lines` set) come back, null when not a 422.
 */
function applyRecordServerErrors(error: unknown, setError: UseFormSetError<InvoiceRecordFormValues>): string[] | null {
  if (!axios.isAxiosError(error) || error.response?.status !== UNPROCESSABLE_STATUS) {
    return null
  }
  const errors = (error.response.data as { errors?: Record<string, string[]> } | undefined)?.errors ?? {}
  const unmapped: string[] = []
  for (const [key, messages] of Object.entries(errors)) {
    const message = messages[0]
    if (!message) continue
    if (RECORD_KEYS.has(key) || (key !== 'lines' && isFormFieldPath(key))) {
      setError(key as FieldPath<InvoiceRecordFormValues>, { message })
    } else {
      unmapped.push(message)
    }
  }
  return unmapped
}

/**
 * Drives the invoice record's in-place editing: the registration fields save
 * through the details endpoint, the proforma header through the partial
 * invoice PATCH (spec 0261), each line through the same PATCH with the whole
 * `lines` set (spec 0263), on the shared `useRoutedRecordInlineEdit`.
 */
export function useInvoiceInlineEdit(invoice: InvoiceWithPermissions, onChanged?: () => void) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const schema = useMemo(() => buildInvoiceRecordSchema(t), [t])
  const persisted = useMemo(() => toRecordValues(invoice), [invoice])
  const form = useForm<InvoiceRecordFormValues>({ resolver: zodResolver(schema), defaultValues: persisted })
  const lineArray = useFieldArray({ control: form.control, name: 'lines' })
  const { vatRefs, rememberVat } = useVatRefs(vatRefsOf(invoice.lines))
  const lines = useWatch({ control: form.control, name: 'lines' })
  const lineAmounts = useMemo(() => amountsOf(lines, vatRefs).lines, [lines, vatRefs])

  const routes = useMemo<InlineSaveRoute<InvoiceRecordFormValues, InvoiceWithPermissions>[]>(
    () => [
      { keys: DETAIL_KEYS, together: EXTERNAL_KEYS, save: (payload) => updateInvoiceDetails(invoice.id, payload) },
      { keys: HEADER_KEYS, save: (payload) => patchInvoice(invoice.id, payload) },
    ],
    [invoice.id],
  )

  const { inline, startNewLine, removeLine } = useRoutedRecordInlineEdit({
    form,
    persisted,
    routes,
    lines: { save: (values) => patchInvoice(invoice.id, { lines: values }) },
    onSaved: (saved) => {
      queryClient.setQueryData(invoiceKeys.detail(saved.id), saved)
      void queryClient.invalidateQueries({ queryKey: invoiceKeys.all })
      toast.success(t('invoices.record.saved'))
      onChanged?.()
      return toRecordValues(saved)
    },
    applyServerErrors: applyRecordServerErrors,
    errorMessage: (error) => serverMessage(error) ?? t('invoices.record.saveError'),
    invalidMessage: t('invoices.record.invalid'),
  })

  /** Opens the chosen line in edit mode; a free line inherits the VAT of the last line, as in the issue editor. */
  const addLine = (fields: NewLineFields) => {
    const inheritedVat = persisted.lines.at(-1)?.vat_rate_id
    startNewLine(() => lineArray.append(newLine(inheritedVat === undefined ? fields : { vat_rate_id: inheritedVat, ...fields })))
  }

  return { form, inline, lines, lineAmounts, vatRefs, rememberVat, addLine, removeLine }
}

/** What the record's editable sections read: the form, the inline state and the line actions. */
export type InvoiceRecordEditor = ReturnType<typeof useInvoiceInlineEdit>
