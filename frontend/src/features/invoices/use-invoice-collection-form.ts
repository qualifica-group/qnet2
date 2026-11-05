import { useMemo } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import axios from 'axios'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { apiErrorMessage } from '@/lib/forbidden-message'
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
import { useWriteOffThreshold } from '@/features/invoices/use-write-off-threshold'
import { DEFAULT_WRITE_OFF_REASON, effectiveResidualMode, isZeroAmount } from '@/features/invoices/write-off'
import {
  buildInvoiceCollectionSchema,
  type InvoiceCollectionFormValues,
} from '@/features/invoices/invoice-schema'
import { useRecordCollection } from '@/features/invoices/use-invoice-queries'
import type { InvoiceInstallment, NamedRef } from '@/features/invoices/types'

const CONFLICT_STATUS = 409

/**
 * Form + submit of "Registra incasso" for one installment (RHF + Zod, server 422 mapped to fields).
 * `installments` are all the installments of the document: they tell whether "spalma" is possible.
 */
export function useInvoiceCollectionForm(
  installment: InvoiceInstallment,
  installments: readonly InvoiceInstallment[],
  onDone: () => void,
  /** Account of the invoice, proposed when the installment has none of its own (spec 0360). */
  defaultAccount: NamedRef | null = null,
) {
  const { t, i18n } = useTranslation()
  const installmentAmount = Number(installment.amount)
  const canSpread = hasLaterOpenInstallments(installment, installments)
  const writeOffThreshold = useWriteOffThreshold()
  const schema = useMemo(
    () => buildInvoiceCollectionSchema(t, { installmentAmount, canSpread, writeOffThreshold }),
    [t, installmentAmount, canSpread, writeOffThreshold],
  )
  const mutation = useRecordCollection()

  const form = useForm<InvoiceCollectionFormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      collected_amount: installmentAmount,
      collected_at: todayIso(),
      financial_account_id: (installment.financial_account ?? defaultAccount)?.id ?? null,
      write_off_reason: DEFAULT_WRITE_OFF_REASON,
      write_off_note: '',
      residual_due_date: addDaysIso(installment.due_date, RESIDUAL_INSTALLMENT_DAYS),
    },
  })

  const collectedAmount = useWatch({ control: form.control, name: 'collected_amount' })
  const chosenMode = useWatch({ control: form.control, name: 'residual_mode' })
  const residualMode = effectiveResidualMode(chosenMode, collectedAmount, installmentAmount, writeOffThreshold)
  const isPartial = isPartialCollection(collectedAmount, installmentAmount)

  const submit = form.handleSubmit((values) =>
    mutation.mutate(
      { installmentId: installment.id, body: buildCollectionPayload(values, installmentAmount, writeOffThreshold) },
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
            'financial_account_id',
            'residual_mode',
            'residual_due_date',
            'write_off_reason',
            'write_off_note',
          ])
          if (!mapped) {
            toast.error(apiErrorMessage(error, t('invoices.collection.genericError'), i18n))
          }
        },
      },
    ),
  )

  return {
    form,
    submit,
    proposedAccount: installment.financial_account ?? defaultAccount,
    isPending: mutation.isPending,
    isPartial,
    isZeroAmount: isZeroAmount(collectedAmount),
    canSpread,
    residualMode,
    residualAmount: residualAmountOf(collectedAmount, installmentAmount),
  }
}
