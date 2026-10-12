import { useMemo } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import axios from 'axios'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { useDraftInlineEdit } from '@/components/record-form/use-draft-inline-edit'
import { buildWritePayload } from '@/features/invoices/invoice-editor-lines'
import type { EditorSource } from '@/features/invoices/invoice-editor-source'
import { applyFieldErrors, serverMessage } from '@/features/invoices/invoice-form-errors'
import { buildInvoiceFormSchema, type InvoiceFormValues } from '@/features/invoices/invoice-schema'
import { useInvoiceEditorPreview } from '@/features/invoices/use-invoice-editor-preview'
import { useInvoiceLines } from '@/features/invoices/use-invoice-lines'
import { useCreateInvoice } from '@/features/invoices/use-invoice-queries'
import type { Invoice } from '@/features/invoices/types'

const CONFLICT_STATUS = 409

interface UseInvoiceIssueFormArgs {
  source: EditorSource
  proformaRequestId: number
  onSaved: (invoice: Invoice) => void
}

/**
 * The issue draft (spec 0224 D-5): the detail's rows start closed and keep
 * their value on "Fatto" (`useDraftInlineEdit`), the lines grid stays open,
 * totals and due dates follow the draft live, and Emetti validates and
 * issues everything at once.
 */
export function useInvoiceIssueForm({ source, proformaRequestId, onSaved }: UseInvoiceIssueFormArgs) {
  const { t } = useTranslation()
  const schema = useMemo(() => buildInvoiceFormSchema(t), [t])
  const form = useForm<InvoiceFormValues>({
    resolver: zodResolver(schema),
    defaultValues: source.initialValues,
  })
  const { control, setError, handleSubmit } = form
  const lines = useInvoiceLines(form, source.vatRefs, source.availableLines)
  const inline = useDraftInlineEdit(form)

  const documentDate = useWatch({ control, name: 'document_date' })
  const paymentMethodId = useWatch({ control, name: 'payment_method_id' })
  const preview = useInvoiceEditorPreview({ documentDate, paymentMethodId, totals: lines.totals, invoiceId: null })

  const create = useCreateInvoice(proformaRequestId)

  const submit = handleSubmit((values) => {
    create.mutate(buildWritePayload(values), {
      onSuccess: (invoice) => {
        toast.success(t('invoiceEditor.messages.issued', { number: invoice.number_label }))
        onSaved(invoice)
      },
      onError: (error) => {
        if (applyFieldErrors(error, setError)) {
          return
        }
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        const fallback =
          status === CONFLICT_STATUS ? t('invoiceEditor.messages.conflictInvoiced') : t('invoiceEditor.messages.genericError')
        toast.error(serverMessage(error) ?? fallback)
      },
    })
  })

  return { form, lines, inline, preview, submit, isSaving: create.isPending }
}

export type InvoiceIssueForm = ReturnType<typeof useInvoiceIssueForm>
