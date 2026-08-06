import { useQuery, type UseQueryResult } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { fetchTableRows } from '@/features/table/api'
import { QUOTES_DOMAIN } from '@/features/quotes/api'
import type { TableRow } from '@/features/table/types'

/**
 * How many Offerte the expanded row loads. An Opportunity holds a handful of
 * them in practice; the cap exists so a pathological record can never render
 * an unbounded panel inside a grid row (the panel scrolls internally at that
 * point and the count tells the user what is not shown).
 */
export const OPPORTUNITY_QUOTE_ROWS_LIMIT = 25

/** Query key of one Opportunity's Offerte rows (the master/detail lazy load). */
export function opportunityQuoteRowsQueryKey(opportunityId: number) {
  return ['opportunities', 'quote-rows', opportunityId] as const
}

export interface OpportunityQuoteRows {
  rows: TableRow[]
  /** Total matching Offerte server-side, which may exceed the loaded `rows`. */
  total: number
}

/**
 * Lazy-loads one Opportunity's Offerte for the master/detail expanded row.
 *
 * Deliberately NOT a new endpoint: it posts to the SAME
 * `POST /tables/quotes/rows` the Offerte grid uses, with the `opportunityId`
 * row-scope (spec 0067 D-1, `OpportunityScopedTableDefinition`) — so the panel
 * shows literally the values of the Offerte table, mapped by the same
 * server-side `mapRow`, authorized by the same policy, with the same per-row
 * `actions` catalog.
 *
 * `staleTime: Infinity` mirrors `useReferentRewards`: AG Grid unmounts the
 * detail renderer on collapse, so the default `staleTime: 0` would refetch on
 * every re-expand. Mutations invalidate the key explicitly instead.
 */
export function useOpportunityQuoteRows(
  opportunityId: number,
  options: { enabled?: boolean } = {},
): UseQueryResult<OpportunityQuoteRows, AxiosError> {
  return useQuery({
    queryKey: opportunityQuoteRowsQueryKey(opportunityId),
    queryFn: async (): Promise<OpportunityQuoteRows> => {
      const response = await fetchTableRows(QUOTES_DOMAIN, {
        startRow: 0,
        endRow: OPPORTUNITY_QUOTE_ROWS_LIMIT,
        sortModel: [],
        filterModel: {},
        opportunityId,
      })
      return { rows: response.items, total: response.pagination.total }
    },
    staleTime: Infinity,
    enabled: options.enabled ?? true,
  })
}
