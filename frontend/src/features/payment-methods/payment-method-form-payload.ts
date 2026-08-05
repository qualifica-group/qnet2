import type {
  CreatePaymentMethodPayload,
  PaymentMethodDetail,
  UpdatePaymentMethodPayload,
} from '@/features/payment-methods/types'
import type { PaymentMethodFormValues } from '@/features/payment-methods/use-payment-method-form'

/**
 * Builds the create payload: every field but `sort_order`, which is
 * server-managed (D-1) and never a form field.
 */
export function buildCreatePayload(values: PaymentMethodFormValues): CreatePaymentMethodPayload {
  return {
    name: values.name,
    code: values.code,
    payment_method_code: values.payment_method_code,
    description: values.description,
    payment_instructions: values.payment_instructions,
    payment_days: values.payment_days,
    is_active: values.is_active,
  }
}

/**
 * Builds a partial PATCH payload carrying only the fields that actually
 * changed from the original payment method. `code` is NEVER included (D-3):
 * it is immutable after create for every role, and the backend 422s on its
 * mere presence, even when the value is unchanged.
 */
export function buildUpdatePayload(
  values: PaymentMethodFormValues,
  original: PaymentMethodDetail,
): UpdatePaymentMethodPayload {
  const payload: UpdatePaymentMethodPayload = {}

  if (values.name !== original.name) {
    payload.name = values.name
  }
  if (values.payment_method_code !== original.payment_method_code) {
    payload.payment_method_code = values.payment_method_code
  }
  if (values.description !== original.description) {
    payload.description = values.description
  }
  if (values.payment_instructions !== original.payment_instructions) {
    payload.payment_instructions = values.payment_instructions
  }
  if (values.payment_days !== original.payment_days) {
    payload.payment_days = values.payment_days
  }
  if (values.is_active !== original.is_active) {
    payload.is_active = values.is_active
  }

  return payload
}
