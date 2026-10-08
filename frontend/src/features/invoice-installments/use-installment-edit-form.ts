import { useMemo } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import axios from 'axios'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { resolveFieldAccess } from '@/features/invoice-installments/installment-field-access'
import {
  buildInstallmentEditSchema,
  type InstallmentEditFormValues,
} from '@/features/invoice-installments/installment-schema'
import { useUpdateInstallment } from '@/features/invoice-installments/use-installment-queries'
import type { InstallmentDetail, InstallmentUpdatePayload } from '@/features/invoice-installments/types'

const CONFLICT_STATUS = 409
const FIELDS = ['due_date', 'payment_method_code'] as const

// Server error surfaced on the form root (not tied to a field), e.g. the 409 for a collected installment.
const ROOT_SERVER_ERROR = 'root.server'

/** Sends only the fields the actor may edit; an emptied method code clears it. */
function buildPayload(detail: InstallmentDetail, values: InstallmentEditFormValues): InstallmentUpdatePayload {
  const payload: InstallmentUpdatePayload = {}
  if (resolveFieldAccess(detail, 'due_date').editable) {
    payload.due_date = values.due_date
  }
  if (resolveFieldAccess(detail, 'payment_method_code').editable) {
    payload.payment_method_code = values.payment_method_code === '' ? null : values.payment_method_code
  }
  return payload
}

/** Form + submit of the "Modifica scadenza" dialog (PATCH due date / method code). */
export function useInstallmentEditForm(detail: InstallmentDetail, onSaved: () => void) {
  const { t } = useTranslation()
  const schema = useMemo(() => buildInstallmentEditSchema(t), [t])
  const mutation = useUpdateInstallment(detail.id)

  const form = useForm<InstallmentEditFormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      due_date: detail.due_date,
      payment_method_code: detail.payment_method_code ?? '',
    },
  })

  const submit = form.handleSubmit((values) =>
    mutation.mutate(buildPayload(detail, values), {
      onSuccess: () => {
        toast.success(t('invoiceInstallments.edit.saved'))
        onSaved()
      },
      onError: (error) => {
        if (axios.isAxiosError(error) && error.response?.status === CONFLICT_STATUS) {
          form.setError(ROOT_SERVER_ERROR, { message: t('invoiceInstallments.edit.collectedConflict') })
        } else if (!applyServerValidationErrors(error, form.setError, [...FIELDS])) {
          toast.error(t('invoiceInstallments.edit.genericError'))
        }
      },
    }),
  )

  return { form, submit, isPending: mutation.isPending }
}
