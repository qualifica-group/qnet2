import { useMemo } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { todayIso } from '@/features/invoices/invoice-format'
import {
  buildInvoiceCollectionSchema,
  type InvoiceCollectionFormValues,
} from '@/features/invoices/invoice-schema'
import { useRecordCollection } from '@/features/invoices/use-invoice-queries'
import type { InvoiceInstallment } from '@/features/invoices/types'

/** Form + submit of "Registra incasso" for one installment (RHF + Zod, server 422 mapped to fields). */
export function useInvoiceCollectionForm(installment: InvoiceInstallment, onDone: () => void) {
  const { t } = useTranslation()
  const installmentAmount = Number(installment.amount)
  const schema = useMemo(() => buildInvoiceCollectionSchema(t, installmentAmount), [t, installmentAmount])
  const mutation = useRecordCollection()

  const form = useForm<InvoiceCollectionFormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      collected_amount: Number(installment.collected_amount ?? installment.amount),
      collected_at: installment.collected_at ?? todayIso(),
    },
  })

  const submit = form.handleSubmit((values) =>
    mutation.mutate(
      { installmentId: installment.id, body: values },
      {
        onSuccess: () => {
          toast.success(t('invoices.collection.saved'))
          onDone()
        },
        onError: (error) => {
          if (!applyServerValidationErrors(error, form.setError, ['collected_amount', 'collected_at'])) {
            toast.error(t('invoices.collection.genericError'))
          }
        },
      },
    ),
  )

  return { form, submit, isPending: mutation.isPending }
}
