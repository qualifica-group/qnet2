import type {
  CreateInvoiceTagPayload,
  InvoiceTagDetail,
  UpdateInvoiceTagPayload,
} from '@/features/invoice-tags/types'
import type { InvoiceTagFormValues } from '@/features/invoice-tags/use-invoice-tag-form'

/** Builds the create payload: every field the form owns (never `sort_order`). */
export function buildCreatePayload(values: InvoiceTagFormValues): CreateInvoiceTagPayload {
  return {
    name: values.name,
    color: values.color,
    description: values.description,
    is_active: values.is_active,
  }
}

/**
 * Builds a partial PATCH payload carrying only the fields that actually
 * changed from the original invoice tag. `sort_order` is NEVER
 * included: the backend 422s on its mere presence.
 */
export function buildUpdatePayload(
  values: InvoiceTagFormValues,
  original: InvoiceTagDetail,
): UpdateInvoiceTagPayload {
  const payload: UpdateInvoiceTagPayload = {}

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

  return payload
}
