import { useMemo } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import axios, { type AxiosError } from 'axios'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { apiErrorMessage } from '@/lib/forbidden-message'
import { bulkPayInstallments, purchaseInstallmentKeys } from '@/features/purchase-invoice-installments/api'
import {
  buildBulkPaymentPayload,
  buildBulkPaymentSchema,
  paidTotal,
  selectedTotal,
  type BulkPaymentFormValues,
  type BulkPaymentTarget,
} from '@/features/purchase-invoice-installments/purchase-installment-bulk-payment'
import type { BulkPaymentPayload, BulkPaymentResult } from '@/features/purchase-invoice-installments/types'
import { formatEuro, todayIso } from '@/features/invoices/invoice-format'

const VALIDATION_STATUS = 422
const ROW_ERROR = /^items\.(\d+)\.(installment_id|paid_amount)$/
const I18N = 'purchaseInvoiceInstallments.bulkPayment'

/**
 * Form + submit of the bulk payment (spec 0211 D-8): one payment date, one
 * optional account, one optional amount per selected installment, the running
 * total for the footer. A server 422 is mapped back onto the dialog row it belongs to.
 */
export function usePurchaseInstallmentBulkPaymentForm(targets: readonly BulkPaymentTarget[], onDone: () => void) {
  const { t, i18n } = useTranslation()
  const queryClient = useQueryClient()
  const schema = useMemo(() => buildBulkPaymentSchema(t, targets), [t, targets])

  const form = useForm<BulkPaymentFormValues>({
    resolver: zodResolver(schema),
    mode: 'onChange',
    defaultValues: { paid_at: todayIso(), financial_account_id: null, amounts: targets.map(() => '') },
  })

  const amounts = useWatch({ control: form.control, name: 'amounts' })
  const totalPaid = paidTotal(amounts)

  const mutation = useMutation<BulkPaymentResult, AxiosError, BulkPaymentPayload>({
    mutationFn: bulkPayInstallments,
    onSuccess: () => void queryClient.invalidateQueries({ queryKey: purchaseInstallmentKeys.all }),
  })

  // Every server message is replaced by its localized twin: the API answers in English.
  function mapServerErrors(error: unknown, rowIndexes: number[]): boolean {
    if (!axios.isAxiosError(error) || error.response?.status !== VALIDATION_STATUS) {
      return false
    }
    const errors = (error.response.data?.errors ?? {}) as Record<string, string[]>
    let mapped = false
    for (const key of Object.keys(errors)) {
      const match = ROW_ERROR.exec(key)
      const rowIndex = match ? rowIndexes[Number(match[1])] : undefined
      if (match && rowIndex !== undefined) {
        const target = targets[rowIndex]
        form.setError(`amounts.${rowIndex}`, {
          message:
            match[2] === 'installment_id'
              ? t(`${I18N}.errors.alreadyPaid`)
              : t(`${I18N}.errors.amountExceeds`, { amount: formatEuro(target?.amount) }),
        })
        mapped = true
      } else if (key === 'items') {
        toast.error(t(`${I18N}.errors.mixedSuppliers`))
        mapped = true
      } else if (key === 'paid_at') {
        form.setError('paid_at', { message: t(`${I18N}.errors.dateRequired`) })
        mapped = true
      } else if (key === 'financial_account_id') {
        form.setError('financial_account_id', { message: t(`${I18N}.errors.accountCompany`) })
        mapped = true
      }
    }
    return mapped
  }

  const submit = form.handleSubmit((values) => {
    const { payload, rowIndexes } = buildBulkPaymentPayload(values, targets)
    if (payload.items.length === 0) {
      return
    }
    mutation.mutate(payload, {
      onSuccess: (result) => {
        toast.success(t(`${I18N}.saved`, { count: result.paid_count, residuals: result.residual_count }))
        onDone()
      },
      onError: (error) => {
        if (!mapServerErrors(error, rowIndexes)) {
          toast.error(apiErrorMessage(error, t(`${I18N}.genericError`), i18n))
        }
      },
    })
  })

  const hasErrors = Object.keys(form.formState.errors).length > 0

  return {
    form,
    submit,
    isPending: mutation.isPending,
    totalPaid,
    totalSelected: selectedTotal(targets),
    canSave: totalPaid > 0 && !hasErrors && !mutation.isPending,
  }
}
