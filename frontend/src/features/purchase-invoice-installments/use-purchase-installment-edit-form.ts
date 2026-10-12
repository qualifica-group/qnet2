import { useMemo } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import axios from 'axios'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { resolvePurchaseFieldAccess } from '@/features/purchase-invoice-installments/purchase-installment-field-access'
import {
  buildPurchaseInstallmentEditSchema,
  type PurchaseInstallmentEditFormValues,
} from '@/features/purchase-invoice-installments/purchase-installment-schema'
import { useUpdatePurchaseInstallment } from '@/features/purchase-invoice-installments/use-purchase-installment-queries'
import type {
  PurchaseInstallmentDetail,
  PurchaseInstallmentUpdatePayload,
} from '@/features/purchase-invoice-installments/types'

const CONFLICT_STATUS = 409
const FIELDS = ['due_date', 'payment_method_code'] as const

// Server error surfaced on the form root (not tied to a field), e.g. the 409 for a paid installment.
const ROOT_SERVER_ERROR = 'root.server'

/** Sends only the fields the actor may edit; an emptied method code clears it. */
function buildPayload(
  detail: PurchaseInstallmentDetail,
  values: PurchaseInstallmentEditFormValues,
): PurchaseInstallmentUpdatePayload {
  const payload: PurchaseInstallmentUpdatePayload = {}
  if (resolvePurchaseFieldAccess(detail, 'due_date').editable) {
    payload.due_date = values.due_date
  }
  if (resolvePurchaseFieldAccess(detail, 'payment_method_code').editable) {
    payload.payment_method_code = values.payment_method_code === '' ? null : values.payment_method_code
  }
  return payload
}

/** Form + submit of the "Modifica scadenza" dialog (PATCH due date / method code). */
export function usePurchaseInstallmentEditForm(detail: PurchaseInstallmentDetail, onSaved: () => void) {
  const { t } = useTranslation()
  const schema = useMemo(() => buildPurchaseInstallmentEditSchema(t), [t])
  const mutation = useUpdatePurchaseInstallment(detail.id)

  const form = useForm<PurchaseInstallmentEditFormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      due_date: detail.due_date,
      payment_method_code: detail.payment_method_code ?? '',
    },
  })

  const submit = form.handleSubmit((values) =>
    mutation.mutate(buildPayload(detail, values), {
      onSuccess: () => {
        toast.success(t('purchaseInvoiceInstallments.edit.saved'))
        onSaved()
      },
      onError: (error) => {
        if (axios.isAxiosError(error) && error.response?.status === CONFLICT_STATUS) {
          form.setError(ROOT_SERVER_ERROR, { message: t('purchaseInvoiceInstallments.edit.paidConflict') })
        } else if (!applyServerValidationErrors(error, form.setError, [...FIELDS])) {
          toast.error(t('purchaseInvoiceInstallments.edit.genericError'))
        }
      },
    }),
  )

  return { form, submit, isPending: mutation.isPending }
}
