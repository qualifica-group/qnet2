import type {
  CreateWorkOrderPaymentStatusPayload,
  UpdateWorkOrderPaymentStatusPayload,
  WorkOrderPaymentStatusDetail,
} from '@/features/work-order-payment-statuses/types'
import type { WorkOrderPaymentStatusFormValues } from '@/features/work-order-payment-statuses/use-work-order-payment-status-form'

/** Builds the create payload (`sort_order` is server-managed). */
export function buildCreatePayload(
  values: WorkOrderPaymentStatusFormValues,
): CreateWorkOrderPaymentStatusPayload {
  return {
    name: values.name,
    description: values.description,
    color: values.color,
    is_active: values.is_active,
    allows_delivery: values.allows_delivery,
  }
}

/** Builds a partial PATCH payload carrying only the fields that changed. */
export function buildUpdatePayload(
  values: WorkOrderPaymentStatusFormValues,
  original: WorkOrderPaymentStatusDetail,
): UpdateWorkOrderPaymentStatusPayload {
  const payload: UpdateWorkOrderPaymentStatusPayload = {}

  if (values.name !== original.name) {
    payload.name = values.name
  }
  if (values.description !== original.description) {
    payload.description = values.description
  }
  if (values.color !== original.color) {
    payload.color = values.color
  }
  if (values.is_active !== original.is_active) {
    payload.is_active = values.is_active
  }
  if (values.allows_delivery !== original.allows_delivery) {
    payload.allows_delivery = values.allows_delivery
  }

  return payload
}
