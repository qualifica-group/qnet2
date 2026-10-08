import { useMemo } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import {
  buildInvoiceDetailsSchema,
  type InvoiceDetailsFormValues,
} from '@/features/invoices/invoice-schema'
import { useUpdateInvoiceDetails } from '@/features/invoices/use-invoice-queries'
import type { Invoice } from '@/features/invoices/types'

const FIELDS = ['external_number', 'external_date', 'tag', 'deviation', 'internal_note', 'layout_id'] as const

/** Form + submit of the "Dettagli fattura" dialog (PATCH details, always allowed). */
export function useInvoiceDetailsForm(invoice: Invoice, onDone: () => void) {
  const { t } = useTranslation()
  const schema = useMemo(() => buildInvoiceDetailsSchema(t), [t])
  const mutation = useUpdateInvoiceDetails(invoice.id)

  const form = useForm<InvoiceDetailsFormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      external_number: invoice.external_number,
      external_date: invoice.external_date,
      tag: invoice.tag,
      deviation: invoice.deviation === null ? null : Number(invoice.deviation),
      internal_note: invoice.internal_note,
      layout_id: invoice.layout?.id ?? null,
    },
  })

  const submit = form.handleSubmit((values) =>
    mutation.mutate(
      {
        ...values,
        external_number: values.external_number || null,
        external_date: values.external_date || null,
        internal_note: values.internal_note || null,
      },
      {
        onSuccess: () => {
          toast.success(t('invoices.details.saved'))
          onDone()
        },
        onError: (error) => {
          if (!applyServerValidationErrors(error, form.setError, [...FIELDS])) {
            toast.error(t('invoices.details.genericError'))
          }
        },
      },
    ),
  )

  return { form, submit, isPending: mutation.isPending }
}
