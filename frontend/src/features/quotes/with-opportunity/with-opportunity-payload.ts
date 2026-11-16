import { buildCreatePayload as buildOpportunityPayload } from '@/features/opportunities/opportunity-form-payload'
import { buildCreatePayload as buildQuotePayload } from '@/features/quotes/quote-form-payload'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'
import type { QuoteFormValues } from '@/features/quotes/quote-schema'
import type { QuoteWithOpportunityPayload } from '@/features/quotes/with-opportunity/with-opportunity-api'

/**
 * The single request of the new opportunity + offer procedure (spec 0396): the
 * two existing create builders, so every rule of their own payload (omitted
 * blank title, `code` only when typed, G.A. slots, rows in full) stays
 * identical. Only what the server owns is dropped from the quote block: the
 * opportunity id and the G.A. promotion.
 */
export function buildWithOpportunityPayload(
  opportunityValues: OpportunityFormValues,
  quoteValues: QuoteFormValues,
  suggestedCode?: string,
): QuoteWithOpportunityPayload {
  // `lead_id` only ever comes from `fromLead`, which this procedure never passes.
  const opportunity = buildOpportunityPayload(opportunityValues)
  const { opportunity_id: ignoredOpportunityId, ...quote } = buildQuotePayload(quoteValues, suggestedCode)
  void ignoredOpportunityId

  return { opportunity, quote }
}
