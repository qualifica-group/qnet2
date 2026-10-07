import { useMemo } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import axios from 'axios'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import {
  addDaysIso,
  buildCollectionPayload,
  hasLaterOpenInstallments,
  isPartialCollection,
  residualAmountOf,
  RESIDUAL_INSTALLMENT_DAYS,
} from '@/features/invoices/invoice-collection-residual'
import { todayIso } from '@/features/invoices/invoice-format'
import {
  buildInvoiceCollectionSchema,
  type InvoiceCollectionFormValues,
} from '@/features/invoices/invoice-schema'
import { useRecordCollection } from '@/features/invoices/use-invoice-queries'
import type { InvoiceInstallment } from '@/features/invoices/types'

const CONFLICT_STATUS = 409

/**
 * Form + submit of "Registra incasso" for one installment (RHF + Zod, server 422 mapped to fields).
 * `installments` are all the installments of the document: they tell whether "spalma" is possible.
 */
export function useInvoiceCollectionForm(
  installment: InvoiceInstallment,
  installments: readonly InvoiceInstallment[],
  onDone: () => void,
) {
  const { t } = useTranslation()
  const installmentAmount = Number(installment.amount)
  const canSpread = hasLaterOpenInstallments(installment, installments)
  const schema = useMemo(
    () => buildInvoiceCollectionSchema(t, { installmentAmount, canSpread }),
    [t, installmentAmount, canSpread],
  )
  const mutation = useRecordCollection()

  const form = useForm<InvoiceCollectionFormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      collected_amount: installmentAmount,
      collected_at: todayIso(),
      residual_mode: canSpread ? 'spread' : 'new_installment',
      residual_due_date: addDaysIso(installment.due_date, RESIDUAL_INSTALLMENT_DAYS),
    },
  })

  const collectedAmount = useWatch({ control: form.control, name: 'collected_amount' })
  const residualMode = useWatch({ control: form.control, name: 'residual_mode' })
  const isPartial = isPartialCollection(collectedAmount, installmentAmount)

  const submit = form.handleSubmit((values) =>
    mutation.mutate(
      { installmentId: installment.id, body: buildCollectionPayload(values, installmentAmount) },
      {
        onSuccess: () => {
          toast.success(t('invoices.collection.saved'))
          onDone()
        },
        onError: (error) => {
          if (axios.isAxiosError(error) && error.response?.status === CONFLICT_STATUS) {
            toast.error(t('invoices.collection.alreadyCollected'))
            return
          }
          const mapped = applyServerValidationErrors(error, form.setError, [
            'collected_amount',
            'collected_at',
            'residual_mode',
            'residual_due_date',
          ])
          if (!mapped) {
            toast.error(t('invoices.collection.genericError'))
          }
        },
      },
    ),
  )

  return {
    form,
    submit,
    isPending: mutation.isPending,
    isPartial,
    canSpread,
    residualMode,
    residualAmount: residualAmountOf(collectedAmount, installmentAmount),
  }
}
