import { useMemo } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import axios from 'axios'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import {
  buildPurchaseInvoiceDetailsSchema,
  type PurchaseInvoiceDetailsFormValues,
} from '@/features/purchase-invoices/purchase-invoice-schema'
import type { PurchaseInvoice } from '@/features/purchase-invoices/types'
import { useUpdatePurchaseInvoiceDetails } from '@/features/purchase-invoices/use-purchase-invoice-editor-queries'

const FIELDS = ['document_number', 'notes'] as const

/** The envelope's own message of a rejected save (e.g. the downgrade 403), when it carries one. */
function serverMessage(error: unknown): string | null {
  const message = axios.isAxiosError(error)
    ? (error.response?.data as { message?: unknown } | undefined)?.message
    : undefined
  return typeof message === 'string' && message !== '' ? message : null
}

/** Form + submit of the supplier-details dialog (PATCH details): an empty number makes the document a proforma. */
export function usePurchaseInvoiceDetailsForm(invoice: PurchaseInvoice, onDone: () => void) {
  const { t } = useTranslation()
  const schema = useMemo(() => buildPurchaseInvoiceDetailsSchema(t), [t])
  const mutation = useUpdatePurchaseInvoiceDetails(invoice.id)

  const form = useForm<PurchaseInvoiceDetailsFormValues>({
    resolver: zodResolver(schema),
    defaultValues: { document_number: invoice.document_number, notes: invoice.notes },
  })

  const submit = form.handleSubmit((values) =>
    mutation.mutate(
      { document_number: values.document_number?.trim() || null, notes: values.notes?.trim() || null },
      {
        onSuccess: () => {
          toast.success(t('purchaseInvoices.details.saved'))
          onDone()
        },
        onError: (error) => {
          if (!applyServerValidationErrors(error, form.setError, [...FIELDS])) {
            toast.error(serverMessage(error) ?? t('purchaseInvoices.details.genericError'))
          }
        },
      },
    ),
  )

  return { form, submit, isPending: mutation.isPending }
}
