import type { ModuleCreateParams } from '@/features/modules/types'

/**
 * The create-params channel of the quotes module (spec 0045): the keys a
 * caller may seed a NEW offer with, and the parsers that read them back.
 *
 * `opportunity_id` is the long-standing one (the opportunity panel's "Crea
 * Offerta"). `product_ids` is added by user directive 2026-08-31: when an
 * opportunity create is refused because the anagrafica already has an open
 * one, the refusal offers to add the products straight onto THAT opportunity
 * — so the offer form opens with its rows already filled in, instead of the
 * operator rebuilding them by hand.
 *
 * Params travel through a query string (`ModuleFormPage`) or straight through
 * the modal opener, so every value arrives as `string | number`: the parsers
 * below are the single place that normalizes both shapes.
 */
export const QUOTE_CREATE_OPPORTUNITY_PARAM = 'opportunity_id'

export const QUOTE_CREATE_PRODUCT_IDS_PARAM = 'product_ids'

/** Multi-valued params travel as one comma-separated string: `URLSearchParams` has no list shape. */
const PRODUCT_IDS_SEPARATOR = ','

/** The deep link that opens the offer form on $opportunityId with $productIds already on its rows. */
export function quoteCreateHref(opportunityId: number, productIds: readonly number[]): string {
  const query = new URLSearchParams({ [QUOTE_CREATE_OPPORTUNITY_PARAM]: String(opportunityId) })

  if (productIds.length > 0) {
    query.set(QUOTE_CREATE_PRODUCT_IDS_PARAM, productIds.join(PRODUCT_IDS_SEPARATOR))
  }

  return `/quotes/new?${query.toString()}`
}

/** The product ids seeded onto the offer rows, empty when the param is absent or carries nothing usable. */
export function parseQuoteCreateProductIds(params?: ModuleCreateParams): number[] {
  const raw = params?.[QUOTE_CREATE_PRODUCT_IDS_PARAM]

  if (raw === undefined || raw === null) {
    return []
  }

  return String(raw)
    .split(PRODUCT_IDS_SEPARATOR)
    .map((value) => Number(value.trim()))
    .filter((id) => Number.isInteger(id) && id > 0)
}
