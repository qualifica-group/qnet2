import { useMemo } from 'react'
import { useQuery } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { fetchRequestFormContext } from '@/features/request-management/api'
import { requestManagementKeys } from '@/features/request-management/query-keys'
import type { ProductLineRow } from '@/features/product-lines/types'
import type { RequestFormContext, RequestProductLinePayload } from '@/features/request-management/types'

/**
 * Stable references for the empty case: a fresh `[]`/`null` on every render
 * would re-run every downstream `useMemo` (and rebuild the create form's Zod
 * schema) for nothing.
 */
const NO_ATTRIBUTES: RequestFormContext['applicable_attributes'] = []

const EMPTY_CONTEXT: RequestFormContext = {
  applicable_attributes: NO_ATTRIBUTES,
  attribute_layout: null,
}

/**
 * Only COMPLETE rows scope anything: a funzione picked without its categoria
 * resolves no attribute and no workflow criterion, and sending it would make
 * the query key churn on every keystroke of a half-filled row.
 */
function toCompleteLines(rows: ProductLineRow[]): RequestProductLinePayload[] {
  return rows
    .filter((row) => row.business_function_id !== null && row.product_category_id !== null)
    .map((row) => ({
      business_function_id: row.business_function_id as number,
      product_category_id: row.product_category_id as number,
    }))
}

/**
 * The create form's live equivalent of what the work panel receives already
 * resolved: which dynamic fields the chosen categories carry and how they
 * are laid out (user directive 2026-07-31).
 *
 * Resolution is SERVER-side (`POST /request-management/form-context`), never
 * re-derived here: the union-by-code of several categories' effective
 * attributes is a backend rule, and a second implementation in the frontend
 * would be free to disagree with the POST that follows.
 *
 * Query key = the criteria themselves, so switching category and back re-reads
 * the cache. Fetching is gated on there being at least one complete product
 * line: with none, both blocks are empty by definition and the round trip
 * would tell us nothing.
 */
export function useRequestFormContext(sourceId: number | null, productLines: ProductLineRow[]) {
  const completeLines = useMemo(() => toCompleteLines(productLines), [productLines])
  const criteriaKey = useMemo(
    () => completeLines.map((line) => `${line.business_function_id}:${line.product_category_id}`).join('|'),
    [completeLines],
  )

  const query = useQuery<RequestFormContext, AxiosError>({
    queryKey: requestManagementKeys.formContext(sourceId, criteriaKey),
    queryFn: () => fetchRequestFormContext({ source_id: sourceId, product_lines: completeLines }),
    enabled: completeLines.length > 0,
  })

  return {
    context: query.data ?? EMPTY_CONTEXT,
    /** True while the FIRST resolution for the current criteria is in flight. */
    isLoading: query.isLoading && completeLines.length > 0,
    isError: query.isError,
    /** Whether any criterion is complete enough to resolve a context at all. */
    hasCriteria: completeLines.length > 0,
  }
}
