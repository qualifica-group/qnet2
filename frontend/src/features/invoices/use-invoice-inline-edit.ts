import { useMemo, useState } from 'react'
import { get, useForm, useWatch, type FieldErrors, type FieldPath } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { firstErrorMessage } from '@/components/record-form/first-error-message'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
import { blankToNull, buildWritePayload } from '@/features/invoices/invoice-editor-lines'
import { invoiceFormValues, vatRefsOf } from '@/features/invoices/invoice-editor-source'
import { applyFieldErrors, serverMessage } from '@/features/invoices/invoice-form-errors'
import { buildInvoiceFormSchema, type InvoiceFormValues } from '@/features/invoices/invoice-schema'
import { useInvoiceEditorPreview } from '@/features/invoices/use-invoice-editor-preview'
import { useInvoiceLines } from '@/features/invoices/use-invoice-lines'
import { useUpdateInvoice, useUpdateInvoiceDetails } from '@/features/invoices/use-invoice-queries'
import type { InvoiceDetailsPayload, InvoiceWithPermissions } from '@/features/invoices/types'

/**
 * Rows saved through PATCH details (spec 0224 D-2): they leave the schedule
 * alone, so they never go through the PUT that rewrites lines and due dates.
 * Each row sends its own keys only (the external reference: number + date).
 */
const DETAILS_ROW_KEYS: Record<string, (keyof InvoiceDetailsPayload & FieldPath<InvoiceFormValues>)[]> = {
  external_number: ['external_number', 'external_date'],
  deviation: ['deviation'],
  tag: ['tag'],
  layout_id: ['layout_id'],
  financial_account_id: ['financial_account_id'],
  notes: ['notes'],
  internal_note: ['internal_note'],
}

/** Rows whose change moves the due dates: their editor shows the schedule the save would produce. */
const SCHEDULE_FIELDS = new Set(['document_date', 'payment_method_id', 'lines'])

/** Text keys that travel as null when blank. */
const TEXT_KEYS = new Set<keyof InvoiceDetailsPayload>(['external_number', 'external_date', 'notes', 'internal_note'])

function detailsPayload(values: InvoiceFormValues, keys: (keyof InvoiceDetailsPayload & keyof InvoiceFormValues)[]): InvoiceDetailsPayload {
  return Object.fromEntries(
    keys.map((key) => {
      const value = values[key]
      return [key, TEXT_KEYS.has(key) ? blankToNull(value as string | null) : value]
    }),
  )
}

/**
 * Drives the invoice detail's in-place editing (spec 0224, the Offerte model
 * of spec 0197): ONE row open at a time on a form seeded from the document.
 * Confirm saves that row alone: PATCH details for the rows that leave the
 * schedule alone, the full PUT (the document as loaded plus the edit) for
 * date, customer, payment and lines. A successful save closes the row; the
 * refreshed document re-seeds the form.
 */
export function useInvoiceInlineEdit(invoice: InvoiceWithPermissions, onChanged?: () => void) {
  const { t } = useTranslation()
  const schema = useMemo(() => buildInvoiceFormSchema(t), [t])
  const values = useMemo(() => invoiceFormValues(invoice), [invoice])
  const form = useForm<InvoiceFormValues>({ resolver: zodResolver(schema), values })
  const lines = useInvoiceLines(form, vatRefsOf(invoice.lines))
  const [editingField, setEditingField] = useState<string | null>(null)
  const [serverError, setServerError] = useState<string | null>(null)
  const update = useUpdateInvoice(invoice.id)
  const details = useUpdateInvoiceDetails(invoice.id)

  // Idle unless an editor that moves the schedule is open: an empty date keeps the preview request off.
  const showsSchedule = editingField !== null && SCHEDULE_FIELDS.has(editingField)
  const documentDate = useWatch({ control: form.control, name: 'document_date' })
  const paymentMethodId = useWatch({ control: form.control, name: 'payment_method_id' })
  const preview = useInvoiceEditorPreview({
    documentDate: showsSchedule ? documentDate : '',
    paymentMethodId,
    totals: lines.totals,
    invoiceId: invoice.has_collections ? invoice.id : null,
  })

  const close = () => {
    form.reset(values)
    setServerError(null)
    setEditingField(null)
  }

  const start = (field: string) => {
    close()
    setEditingField(field)
  }

  const handlers = {
    onSuccess: () => {
      setEditingField(null)
      setServerError(null)
      onChanged?.()
    },
    onError: (error: unknown) => {
      if (!applyFieldErrors(error, form.setError)) {
        setServerError(serverMessage(error) ?? t('invoiceEditor.messages.genericError'))
      }
    },
  }

  // A rule failing on a field the open editor does not show has no message on screen: say it.
  const handleInvalid = (errors: FieldErrors<InvoiceFormValues>) => {
    if (editingField === null || get(errors, editingField) === undefined) {
      toast.error(firstErrorMessage(errors) ?? t('invoiceEditor.messages.genericError'))
    }
  }

  const save = () => {
    if (editingField === null) {
      return
    }
    const detailsKeys = DETAILS_ROW_KEYS[editingField]
    if (detailsKeys) {
      void form.trigger(detailsKeys).then((valid) => {
        if (valid) {
          details.mutate(detailsPayload(form.getValues(), detailsKeys), handlers)
        }
      })
      return
    }
    void form.handleSubmit((submitted) => update.mutate(buildWritePayload(submitted), handlers), handleInvalid)()
  }

  const inline: InlineEdit = {
    editingField,
    start,
    cancel: close,
    save,
    dismiss: close,
    isSaving: update.isPending || details.isPending,
    error: serverError,
    confirmLabel: t('common.inlineEdit.save'),
    cancelLabel: t('common.inlineEdit.cancel'),
  }

  return { form, lines, preview, inline }
}

export type InvoiceDetailEditor = ReturnType<typeof useInvoiceInlineEdit>
