import { useMemo } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { apiErrorMessage } from '@/lib/forbidden-message'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import {
  buildCompensationPayload,
  buildCompensationSchema,
  computeCompensation,
  NO_ID,
  residualErrors,
  type CompensationFormValues,
} from '@/features/compensations/compensation-math'
import type { CompensationTarget } from '@/features/compensations/types'
import { useCompensationCandidates, useCreateCompensation } from '@/features/compensations/use-compensation-queries'
import { addDaysIso, RESIDUAL_INSTALLMENT_DAYS } from '@/features/invoices/invoice-collection-residual'
import { todayIso } from '@/features/invoices/invoice-format'

const FIELDS = [
  'invoice_installment_id',
  'purchase_invoice_installment_id',
  'compensated_at',
  'note',
  'residual_mode',
  'residual_due_date',
] as const

/**
 * Form + submit of "Compensa" (RHF + Zod, server 422 mapped to fields). The candidates follow the chosen company;
 * the amount is the minor of the two chosen residuals and the residual choice appears only when they differ.
 */
export function useCompensationForm(target: CompensationTarget, onDone: () => void) {
  const { t, i18n } = useTranslation()
  const mutation = useCreateCompensation()

  const schema = useMemo(() => buildCompensationSchema(t), [t])
  const form = useForm<CompensationFormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      company_id: target.companyId ?? NO_ID,
      invoice_installment_id: target.receivableId ?? NO_ID,
      purchase_invoice_installment_id: target.payableId ?? NO_ID,
      compensated_at: todayIso(),
      note: '',
      residual_due_date: addDaysIso(todayIso(), RESIDUAL_INSTALLMENT_DAYS),
    },
  })

  const companyId = useWatch({ control: form.control, name: 'company_id' })
  const receivableId = useWatch({ control: form.control, name: 'invoice_installment_id' })
  const payableId = useWatch({ control: form.control, name: 'purchase_invoice_installment_id' })
  const residualMode = useWatch({ control: form.control, name: 'residual_mode' })

  const candidates = useCompensationCandidates(target.registryId, companyId === NO_ID ? null : companyId)
  const receivable = candidates.data?.receivables.find((candidate) => candidate.id === receivableId)
  const payable = candidates.data?.payables.find((candidate) => candidate.id === payableId)
  const { amountCents, residualDiffers } = computeCompensation(receivable?.residual ?? null, payable?.residual ?? null)

  const submit = form.handleSubmit((values) => {
    const errors = residualErrors(t, values, residualDiffers)
    const [field] = Object.keys(errors) as (keyof typeof errors)[]
    if (field) {
      form.setError(field, { message: errors[field] })
      return
    }
    mutation.mutate(buildCompensationPayload(values, residualDiffers), {
      onSuccess: () => {
        toast.success(t('compensations.dialog.saved'))
        onDone()
      },
      onError: (error) => {
        if (!applyServerValidationErrors(error, form.setError, [...FIELDS])) {
          toast.error(apiErrorMessage(error, t('compensations.dialog.genericError'), i18n))
        }
      },
    })
  })

  /** Changing company invalidates the chosen installments (they belong to the previous company). */
  const changeCompany = (value: number) => {
    form.setValue('company_id', value, { shouldValidate: false })
    form.setValue('invoice_installment_id', NO_ID)
    form.setValue('purchase_invoice_installment_id', NO_ID)
  }

  return {
    form,
    submit,
    changeCompany,
    isPending: mutation.isPending,
    candidates,
    companyId,
    amountCents,
    residualDiffers,
    residualMode,
  }
}
