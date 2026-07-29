import type {
  CreateQuoteStatusPayload,
  QuoteStatusDetail,
  UpdateQuoteStatusPayload,
} from '@/features/quote-statuses/types'
import type { QuoteStatusFormValues } from '@/features/quote-statuses/use-quote-status-form'

/** Maps the form's `color` (empty string = unset) to the backend's nullable value. */
function colorValue(color: string): string | null {
  return color === '' ? null : color
}

/** Builds the create payload: `name`, `color` and `group` (`sort_order` is server-managed). */
export function buildCreatePayload(
  values: QuoteStatusFormValues,
): CreateQuoteStatusPayload {
  return {
    name: values.name,
    color: colorValue(values.color),
    group: values.group,
  }
}

/**
 * Builds a partial PATCH payload carrying only the fields that actually
 * changed from the original quote status.
 */
export function buildUpdatePayload(
  values: QuoteStatusFormValues,
  original: QuoteStatusDetail,
): UpdateQuoteStatusPayload {
  const payload: UpdateQuoteStatusPayload = {}

  if (values.name !== original.name) {
    payload.name = values.name
  }
  if (colorValue(values.color) !== original.color) {
    payload.color = colorValue(values.color)
  }
  if (values.group !== original.group) {
    payload.group = values.group
  }

  return payload
}
