import type {
  CreateQuoteSystemStatusPayload,
  QuoteSystemStatusDetail,
  UpdateQuoteSystemStatusPayload,
} from '@/features/quote-system-statuses/types'
import type { QuoteSystemStatusFormValues } from '@/features/quote-system-statuses/quote-system-status-schema'

export function buildCreatePayload(values: QuoteSystemStatusFormValues): CreateQuoteSystemStatusPayload {
  return { name: values.name, color: values.color, behavior: values.behavior }
}

/** Partial PATCH carrying only what changed; the behavior of a system row never travels (fixed server-side). */
export function buildUpdatePayload(
  values: QuoteSystemStatusFormValues,
  original: QuoteSystemStatusDetail,
): UpdateQuoteSystemStatusPayload {
  const payload: UpdateQuoteSystemStatusPayload = {}

  if (values.name !== original.name) {
    payload.name = values.name
  }
  if (values.color !== (original.color ?? '')) {
    payload.color = values.color
  }
  if (values.behavior !== original.behavior) {
    payload.behavior = values.behavior
  }

  return payload
}
