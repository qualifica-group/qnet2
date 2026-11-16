import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type { CreateOpportunityPayload } from '@/features/opportunities/types'
import type { CreateQuotePayload, QuoteDetail } from '@/features/quotes/types'

/**
 * Body of `POST /api/quotes/with-opportunity` (spec 0396 D-3/D-4): the two
 * create bodies in one request. The opportunity never carries a `lead_id`; the
 * quote carries neither `opportunity_id` (the server sets the id it just
 * created) nor `promote_managers_to_opportunity` (forced true server-side).
 */
export interface QuoteWithOpportunityPayload {
  opportunity: Omit<CreateOpportunityPayload, 'lead_id'>
  quote: Omit<CreateQuotePayload, 'opportunity_id' | 'promote_managers_to_opportunity'>
}

/** The open opportunity that blocks a new one on the same anagrafica (`POST /opportunities/open-check`). */
export interface BlockingOpportunityRef {
  id: number
  name: string
}

interface OpenCheckResponse {
  blocking_opportunity: BlockingOpportunityRef | null
}

/** Creates the opportunity and its offer in one transaction; answers like `POST /quotes`. */
export async function createQuoteWithOpportunity(payload: QuoteWithOpportunityPayload): Promise<QuoteDetail> {
  const { data } = await apiClient.post<ApiResponse<QuoteDetail>>('/quotes/with-opportunity', payload)
  return data.data
}

export interface OpenOpportunityCheckInput {
  registryId: number
  categoryIds: number[]
}

/**
 * Pre-check of the anti-duplicate guard (spec 0396 D-6a): the opportunity that
 * would refuse a create for this anagrafica and these categories, `null` when
 * none does. The guard inside the create stays the authority at save time.
 */
export async function checkOpenOpportunity({
  registryId,
  categoryIds,
}: OpenOpportunityCheckInput): Promise<BlockingOpportunityRef | null> {
  const { data } = await apiClient.post<ApiResponse<OpenCheckResponse>>('/opportunities/open-check', {
    registry_id: registryId,
    product_lines: categoryIds.map((categoryId) => ({ product_category_id: categoryId })),
  })
  return data.data.blocking_opportunity
}
