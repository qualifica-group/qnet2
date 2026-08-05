import { useMemo } from 'react'
import { useQuery } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import {
  fetchOpportunityFormContext,
  opportunityFormContextQueryKey,
} from '@/features/opportunities/api'
import type { OpportunityFormContext } from '@/features/opportunities/types'
import type { ProductLineRow } from '@/features/product-lines/types'

/**
 * Stable references for the empty case: a fresh `[]`/`null` on every render
 * would re-run every downstream `useMemo` (and rebuild the form's Zod schema)
 * for nothing.
 */
const NO_ATTRIBUTES: OpportunityFormContext['applicable_attributes'] = []

const EMPTY_CONTEXT: OpportunityFormContext = {
  applicable_attributes: NO_ATTRIBUTES,
  attribute_layout: null,
}

/**
 * Only COMPLETE rows scope anything: a funzione picked without its categoria
 * resolves no attribute, and sending it would make the query key churn while
 * a row is still half-filled.
 */
function toCompleteLines(rows: ProductLineRow[]) {
  return rows
    .filter((row) => row.business_function_id !== null && row.product_category_id !== null)
    .map((row) => ({
      business_function_id: row.business_function_id as number,
      product_category_id: row.product_category_id as number,
    }))
}

/**
 * The CREATE form's live equivalent of what the edit form receives already
 * resolved on `OpportunityDetail` (user directive 2026-08-05): which dynamic
 * fields the chosen categories carry and how they are laid out.
 *
 * Resolution is SERVER-side (`POST /opportunities/form-context`), never
 * re-derived here — the union-by-code of several categories' effective
 * attributes is a backend rule, and a second implementation in the frontend
 * would be free to disagree with the POST that follows. Twin of
 * `useRequestFormContext`, pointed at this module's own gate
 * (`opportunities.create`).
 *
 * Query key = the criteria themselves, so switching category and back re-reads
 * the cache. Gated on there being at least one complete product line: with
 * none the set is empty by definition and the round trip would tell us nothing.
 */
export function useOpportunityFormContext(sourceId: number | null, productLines: ProductLineRow[]) {
  const completeLines = useMemo(() => toCompleteLines(productLines), [productLines])
  const criteriaKey = useMemo(
    () => completeLines.map((line) => `${line.business_function_id}:${line.product_category_id}`).join('|'),
    [completeLines],
  )

  const query = useQuery<OpportunityFormContext, AxiosError>({
    queryKey: opportunityFormContextQueryKey(sourceId, criteriaKey),
    queryFn: () => fetchOpportunityFormContext({ source_id: sourceId, product_lines: completeLines }),
    enabled: completeLines.length > 0,
  })

  return {
    context: query.data ?? EMPTY_CONTEXT,
    /** True while the FIRST resolution for the current criteria is in flight. */
    isLoading: query.isLoading && completeLines.length > 0,
    /** Whether any criterion is complete enough to resolve a context at all. */
    hasCriteria: completeLines.length > 0,
  }
}
