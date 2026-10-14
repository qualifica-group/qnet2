import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import type { InlineEdit } from '@/components/record-form/record-inline-field'
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
const EXTERNAL_KEYS: readonly string[] = ['external_number', 'external_date']

type RecordKey = (typeof DETAIL_KEYS)[number] | (typeof HEADER_KEYS)[number]

export function toRecordValues(invoice: InvoiceWithPermissions): InvoiceRecordFormValues {
  return {
    ...sourceFromInvoice(invoice).initialValues,
    external_number: invoice.external_number,
    external_date: invoice.external_date,
    deviation: invoice.deviation === null ? null : Number(invoice.deviation),
  }
}

/** A blank text is "no value", as the server stores it. */
function normalized(value: unknown): unknown {
  return typeof value === 'string' && value.trim() === '' ? null : value
}

function changedKeys(keys: readonly RecordKey[], values: InvoiceRecordFormValues, persisted: InvoiceRecordFormValues): RecordKey[] {
  return keys.filter((key) => normalized(values[key]) !== normalized(persisted[key]))
}

function pick(keys: readonly RecordKey[], values: InvoiceRecordFormValues): Record<string, unknown> {
  return Object.fromEntries(keys.map((key) => [key, normalized(values[key])]))
}

/**
 * Drives the invoice record's in-place editing (spec 0261): ONE row open at a
 * time; Confirm validates and saves only what that row changed, through the
 * details endpoint for the registration fields (external reference, tag,
 * deviation, layout, internal note) or the partial invoice PATCH for the
 * proforma header. A success resets the form on the saved document.
 */
export function useInvoiceInlineEdit(invoice: InvoiceWithPermissions, onChanged?: () => void) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const schema = useMemo(() => buildInvoiceRecordSchema(t), [t])
  const persisted = useMemo(() => toRecordValues(invoice), [invoice])
  const form = useForm<InvoiceRecordFormValues>({ resolver: zodResolver(schema), defaultValues: persisted })
  const [editingField, setEditingField] = useState<string | null>(null)
  const [serverError, setServerError] = useState<string | null>(null)

  const mutation = useMutation<InvoiceWithPermissions, unknown, { details: boolean; payload: Record<string, unknown> }>({
    mutationFn: ({ details, payload }) => (details ? updateInvoiceDetails(invoice.id, payload) : patchInvoice(invoice.id, payload)),
    onSuccess: (saved) => {
      queryClient.setQueryData(invoiceKeys.detail(saved.id), saved)
      void queryClient.invalidateQueries({ queryKey: invoiceKeys.all })
      form.reset(toRecordValues(saved))
      setEditingField(null)
      toast.success(t('invoices.record.saved'))
      onChanged?.()
    },
    onError: (error) => {
      if (!applyServerValidationErrors(error, form.setError, [...DETAIL_KEYS, ...HEADER_KEYS])) {
        setServerError(serverMessage(error) ?? t('invoices.record.saveError'))
      }
    },
  })

  const discard = () => {
    form.reset(persisted)
    setServerError(null)
  }

  const start = (field: string) => {
    discard()
    setEditingField(field)
  }

  const cancel = () => {
    discard()
    setEditingField(null)
  }

  const save = () => {
    void form.handleSubmit(
      (values) => {
        setServerError(null)
        const details = changedKeys(DETAIL_KEYS, values, persisted)
        const header = changedKeys(HEADER_KEYS, values, persisted)
        if (details.length > 0) {
          const keys = details.some((key) => EXTERNAL_KEYS.includes(key)) ? [...new Set([...details, 'external_number', 'external_date'] as RecordKey[])] : details
          mutation.mutate({ details: true, payload: pick(keys, values) })
        } else if (header.length > 0) {
          mutation.mutate({ details: false, payload: pick(header, values) })
        } else {
          cancel()
        }
      },
      () => toast.error(t('invoices.record.invalid')),
    )()
  }

  const inline: InlineEdit = {
    editingField,
    start,
    cancel,
    save,
    dismiss: cancel,
    isSaving: mutation.isPending,
    error: serverError,
    confirmLabel: t('common.inlineEdit.save'),
    cancelLabel: t('common.inlineEdit.cancel'),
  }

  return { form, inline }
}

/** What the record's editable sections read: the form and the inline state. */
export type InvoiceRecordEditor = ReturnType<typeof useInvoiceInlineEdit>
