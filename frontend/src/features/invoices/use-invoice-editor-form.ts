import { useCallback, useMemo } from 'react'
import { useFieldArray, useForm, useWatch, type UseFormSetError } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import axios from 'axios'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import {
  buildWritePayload,
  isFormFieldPath,
  lineFromAvailable,
  newLine,
  type NewLineFields,
  amountsOf,
} from '@/features/invoices/invoice-editor-lines'
import type { EditorSource } from '@/features/invoices/invoice-editor-source'
import { buildInvoiceWriteSchema, type InvoiceWriteFormValues } from '@/features/invoices/invoice-schema'
import { useInvoiceEditorPreview } from '@/features/invoices/use-invoice-editor-preview'
import { useCreateInvoice } from '@/features/invoices/use-invoice-queries'
import { useVatRefs } from '@/features/invoices/use-vat-refs'
import type { Invoice } from '@/features/invoices/types'

interface UseInvoiceEditorFormArgs {
  source: EditorSource
  proformaRequestId: number
  onSaved: (invoice: Invoice) => void
}

const CONFLICT_STATUS = 409

/** The envelope's own message of a rejected save, when it carries one. */
function serverMessage(error: unknown): string | null {
  const message = axios.isAxiosError(error)
    ? (error.response?.data as { message?: unknown } | undefined)?.message
    : undefined
  return typeof message === 'string' && message !== '' ? message : null
}

/** Puts each 422 field error on its input; true when at least one landed on a field. */
function applyFieldErrors(error: unknown, setError: UseFormSetError<InvoiceWriteFormValues>): boolean {
  if (!axios.isAxiosError(error) || error.response?.status !== 422) {
    return false
  }
  const errors = (error.response.data as { errors?: Record<string, string[]> } | undefined)?.errors ?? {}
  let mapped = false
  for (const [key, messages] of Object.entries(errors)) {
    if (isFormFieldPath(key) && messages[0]) {
      setError(key, { message: messages[0] })
      mapped = true
    }
  }
  return mapped
}

/**
 * State and actions of the issue modal: RHF + Zod form, the document lines
 * (field array), the live totals/installment preview and the save. An issued
 * document is edited in place on its record (spec 0263).
 */
export function useInvoiceEditorForm({ source, proformaRequestId, onSaved }: UseInvoiceEditorFormArgs) {
  const { t } = useTranslation()
  const schema = useMemo(() => buildInvoiceWriteSchema(t), [t])
  const form = useForm<InvoiceWriteFormValues>({
    resolver: zodResolver(schema),
    defaultValues: source.initialValues,
  })
  const { control, setError, handleSubmit, getValues } = form
  const lineArray = useFieldArray({ control, name: 'lines' })
  const { append } = lineArray

  const { vatRefs, rememberVat } = useVatRefs(source.vatRefs)

  const lines = useWatch({ control, name: 'lines' })
  const documentDate = useWatch({ control, name: 'document_date' })
  const paymentMethodId = useWatch({ control, name: 'payment_method_id' })
  const companyId = useWatch({ control, name: 'company_id' })
  const amounts = useMemo(() => amountsOf(lines, vatRefs), [lines, vatRefs])
  const totals = amounts.totals
  const preview = useInvoiceEditorPreview({ documentDate, paymentMethodId, totals })

  const addedQuoteLineIds = useMemo(
    () => new Set(lines.flatMap((line) => (line.quote_line_id === null ? [] : [line.quote_line_id]))),
    [lines],
  )

  const addAvailableLine = useCallback(
    (quoteLineId: number) => {
      const line = source.availableLines.find((candidate) => candidate.quote_line_id === quoteLineId)
      if (line) {
        rememberVat(line.vat_rate)
        append(lineFromAvailable(line))
      }
    },
    [append, rememberVat, source.availableLines],
  )

  const addAllAvailableLines = useCallback(() => {
    const added = new Set(getValues('lines').map((line) => line.quote_line_id))
    const missing = source.availableLines.filter((line) => !added.has(line.quote_line_id))
    missing.forEach((line) => rememberVat(line.vat_rate))
    append(missing.map(lineFromAvailable))
  }, [append, getValues, rememberVat, source.availableLines])

  /** Appends a catalog or free row; a free row inherits the VAT of the last row when there is one. */
  const addLine = useCallback(
    (fields: NewLineFields) => {
      const inheritedVat = getValues('lines').at(-1)?.vat_rate_id
      append(newLine(inheritedVat === undefined ? fields : { vat_rate_id: inheritedVat, ...fields }))
    },
    [append, getValues],
  )

  const mutation = useCreateInvoice(proformaRequestId)

  const submit = handleSubmit((values) => {
    mutation.mutate(buildWritePayload(values), {
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
          status === CONFLICT_STATUS
            ? t('invoiceEditor.messages.conflictInvoiced')
            : t('invoiceEditor.messages.genericError')
        toast.error(serverMessage(error) ?? fallback)
      },
    })
  })

  return {
    form,
    lineArray,
    vatRefs,
    rememberVat,
    lines,
    companyId,
    totals,
    lineAmounts: amounts.lines,
    preview,
    addedQuoteLineIds,
    addAvailableLine,
    addAllAvailableLines,
    addLine,
    submit,
    isSaving: mutation.isPending,
  }
}
