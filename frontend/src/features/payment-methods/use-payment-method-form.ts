import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { createPaymentMethod, updatePaymentMethod } from '@/features/payment-methods/api'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/payment-methods/payment-method-form-payload'
import {
  buildCreatePaymentMethodSchema,
  buildUpdatePaymentMethodSchema,
  type CreatePaymentMethodFormValues,
  type UpdatePaymentMethodFormValues,
} from '@/features/payment-methods/payment-method-schema'
import type {
  PaymentMethodDetail,
  PaymentMethodFormMode,
} from '@/features/payment-methods/types'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = [
  'name',
  'code',
  'description',
  'payment_instructions',
  'payment_days',
  'is_active',
] as const

export type PaymentMethodFormValues = CreatePaymentMethodFormValues & UpdatePaymentMethodFormValues

interface UsePaymentMethodFormArgs {
  mode: PaymentMethodFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (paymentMethod: PaymentMethodDetail) => void
}

/**
 * Owns every non-render concern of `PaymentMethodForm`: RHF/Zod wiring,
 * default values, server 422 mapping and the create/update submit. The
 * component stays UI-only; this hook is the orchestration point (`onSubmit`).
 */
export function usePaymentMethodForm({ mode, onSuccess }: UsePaymentMethodFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  const schema = useMemo(
    () => (isEdit ? buildUpdatePaymentMethodSchema(t) : buildCreatePaymentMethodSchema(t)),
    [isEdit, t],
  )

  const defaultValues = useMemo<PaymentMethodFormValues>(() => {
    if (mode.type === 'edit') {
      return {
        name: mode.paymentMethod.name,
        code: mode.paymentMethod.code,
        description: mode.paymentMethod.description,
        payment_instructions: mode.paymentMethod.payment_instructions,
        payment_days: mode.paymentMethod.payment_days,
        is_active: mode.paymentMethod.is_active,
      }
    }
    return {
      name: '',
      code: '',
      description: null,
      payment_instructions: null,
      payment_days: null,
      is_active: true,
    }
  }, [mode])

  const form = useForm<PaymentMethodFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
  })

  const onSubmit = async (values: PaymentMethodFormValues) => {
    setServerError(null)
    const errorFields: Path<PaymentMethodFormValues>[] = [...SERVER_ERROR_FIELDS]
    try {
      if (mode.type === 'edit') {
        const saved = await updatePaymentMethod(
          mode.paymentMethod.id,
          buildUpdatePayload(values, mode.paymentMethod),
        )
        queryClient.setQueryData(['payment-methods', 'detail', mode.paymentMethod.id], saved)
        toast.success(t('paymentMethods.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createPaymentMethod(buildCreatePayload(values))
      toast.success(t('paymentMethods.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(t('paymentMethods.form.genericError'))
      }
    }
  }

  return {
    form,
    isEdit,
    serverError,
    onSubmit,
  }
}
