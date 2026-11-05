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
  isPartialCollection,
  residualAmountOf,
  RESIDUAL_INSTALLMENT_DAYS,
} from '@/features/invoices/invoice-collection-residual'
import { todayIso } from '@/features/invoices/invoice-format'
import { useWriteOffThreshold } from '@/features/invoices/use-write-off-threshold'
import { DEFAULT_WRITE_OFF_REASON, effectiveResidualMode, isZeroAmount } from '@/features/invoices/write-off'
import {
  buildPurchasePaymentPayload,
  buildPurchasePaymentSchema,
  hasLaterUnpaidInstallments,
  type PurchasePaymentFormValues,
} from '@/features/purchase-invoice-installments/purchase-installment-payment'
import { useRecordPurchasePayment } from '@/features/purchase-invoice-installments/use-purchase-installment-queries'
import type { PurchaseInstallmentDetail } from '@/features/purchase-invoice-installments/types'

const CONFLICT_STATUS = 409
const FIELDS = [
  'paid_amount',
  'paid_at',
  'financial_account_id',
  'residual_mode',
  'residual_due_date',
  'write_off_reason',
  'write_off_note',
] as const

/** The English server message of a 422 on `financial_account_id` is replaced by its localized twin. */
function hasAccountError(error: unknown): boolean {
  if (!axios.isAxiosError(error)) {
    return false
  }
  const errors = error.response?.data?.errors as Record<string, string[]> | undefined
  return Boolean(errors?.financial_account_id)
}

/**
 * Form + submit of "Registra pagamento" for one installment (RHF + Zod, server 422 mapped to fields).
 * The document's installments tell whether "spread" is possible; its account is the proposed default.
 */
export function usePurchasePaymentForm(detail: PurchaseInstallmentDetail, onDone: () => void) {
  const { t, i18n } = useTranslation()
  const installmentAmount = Number(detail.amount)
  const canSpread = hasLaterUnpaidInstallments(detail.sequence, detail.purchase_invoice.installments)
  const writeOffThreshold = useWriteOffThreshold()
  const schema = useMemo(
    () => buildPurchasePaymentSchema(t, { installmentAmount, canSpread, writeOffThreshold }),
    [t, installmentAmount, canSpread, writeOffThreshold],
  )
  const mutation = useRecordPurchasePayment(detail.id)

  const form = useForm<PurchasePaymentFormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      paid_amount: installmentAmount,
      paid_at: todayIso(),
      financial_account_id: detail.purchase_invoice.financial_account?.id ?? null,
      write_off_reason: DEFAULT_WRITE_OFF_REASON,
      write_off_note: '',
      residual_due_date: addDaysIso(detail.due_date, RESIDUAL_INSTALLMENT_DAYS),
    },
  })

  const paidAmount = useWatch({ control: form.control, name: 'paid_amount' })
  const chosenMode = useWatch({ control: form.control, name: 'residual_mode' })
  const residualMode = effectiveResidualMode(chosenMode, paidAmount, installmentAmount, writeOffThreshold)
  const isPartial = isPartialCollection(paidAmount, installmentAmount)

  const submit = form.handleSubmit((values) =>
    mutation.mutate(buildPurchasePaymentPayload(values, installmentAmount, writeOffThreshold), {
      onSuccess: () => {
        toast.success(t('purchaseInvoiceInstallments.payment.saved'))
        onDone()
      },
      onError: (error) => {
        if (axios.isAxiosError(error) && error.response?.status === CONFLICT_STATUS) {
          toast.error(t('purchaseInvoiceInstallments.payment.alreadyPaid'))
          return
        }
        if (!applyServerValidationErrors(error, form.setError, [...FIELDS])) {
          toast.error(apiErrorMessage(error, t('purchaseInvoiceInstallments.payment.genericError'), i18n))
        } else if (hasAccountError(error)) {
          form.setError('financial_account_id', { message: t('purchaseInvoiceInstallments.payment.errors.accountCompany') })
        }
      },
    }),
  )

  return {
    form,
    submit,
    isPending: mutation.isPending,
    isPartial,
    isZeroAmount: isZeroAmount(paidAmount),
    canSpread,
    residualMode,
    residualAmount: residualAmountOf(paidAmount, installmentAmount),
  }
}
