import { useMemo } from 'react'
import { useQueries, useQuery } from '@tanstack/react-query'
import {
  categoryManagerLabelsQueryKey,
  fetchCategoryManagerLabels,
  fetchOpportunity,
  opportunityDetailQueryKey,
} from '@/features/opportunities/api'
import { useForSelectLabels } from '@/features/for-select/use-for-select'
import { PRODUCTS_FOR_SELECT_RESOURCE, productCategoryIdOf } from '@/features/products/for-select-api'
import type { ForSelectItem } from '@/features/for-select/types'
import type { QuoteLineFormValues } from '@/features/quotes/quote-schema'
import { resolveManagerLabels } from '@/lib/utils'

/** Distinct, non-null `product_id`s of the offer's current REVENUE rows. */
function distinctProductIds(offerLines: QuoteLineFormValues[]): number[] {
  const ids = offerLines.map((line) => line.product_id).filter((id): id is number => id !== null)
  return Array.from(new Set(ids))
}

/**
 * Resolves the REVENUE rows' own product categories (D-8, step 1):
 * `quote_lines` carries no `product_category_id` (spec 0065 D-7) and
 * `quote-schema.ts` deliberately does NOT widen `QuoteLineFormValues` with
 * one (it would leak into the payload via `toLineInputs`/`linesChanged`), so
 * the mapping is read live off the products for-select cache instead —
 * `category_id` in its `meta` (spec 0075 AC-012), via the shared
 * `productCategoryIdOf` reader.
 */
function useRevenueCategoryIds(offerLines: QuoteLineFormValues[]): { categoryIds: number[]; isLoading: boolean } {
  const productIds = useMemo(() => distinctProductIds(offerLines), [offerLines])
  const products = useForSelectLabels({
    resource: PRODUCTS_FOR_SELECT_RESOURCE,
    ids: productIds,
    enabled: productIds.length > 0,
  })

  return useMemo(() => {
    if (productIds.length === 0) {
      return { categoryIds: [], isLoading: false }
    }
    if (products.size < productIds.length) {
      // Not every picked product has resolved yet: stay silent rather than
      // resolve against a partial set (mirrors the settled-gate below).
      return { categoryIds: [], isLoading: true }
    }
    const categoryIds = productIds
      .map((id) => products.get(id))
      .filter((item): item is ForSelectItem => item !== undefined)
      .map((item) => productCategoryIdOf(item))
      .filter((id): id is number => id !== null)
    return { categoryIds: Array.from(new Set(categoryIds)), isLoading: false }
  }, [productIds, products])
}

/**
 * Resolves the picked Opportunity's own product-line categories (D-8, step 2
 * fallback): the SAME query `quote-offer-tab.tsx` already runs
 * (`opportunityDetailQueryKey`) — React Query dedupes the two call sites onto
 * one cached fetch, so mounting this hook never doubles the request.
 */
function useOpportunityCategoryIds(opportunityId: number | null): { categoryIds: number[]; isLoading: boolean } {
  const query = useQuery({
    queryKey: opportunityId !== null ? opportunityDetailQueryKey(opportunityId) : ['opportunities', 'detail', null],
    queryFn: () => fetchOpportunity(opportunityId as number),
    enabled: opportunityId !== null,
    staleTime: 5 * 60 * 1000,
  })

  return useMemo(() => {
    if (opportunityId === null) {
      return { categoryIds: [], isLoading: false }
    }
    if (!query.data) {
      return { categoryIds: [], isLoading: true }
    }
    const categoryIds = query.data.product_lines.map((line) => line.product_category.id)
    return { categoryIds: Array.from(new Set(categoryIds)), isLoading: false }
  }, [opportunityId, query.data])
}

/**
 * Resolves the G.A. labels for the quote form's CURRENT REVENUE rows (spec
 * 0087 D-8/D-11): the offer's own product categories win; while it has no
 * REVENUE rows yet, the picked Opportunity's product-line categories stand
 * in (`quote-offer-tab.tsx`'s own `scopedCategoryIds` fallback source) — so
 * the section starts on the Opportunity's own labels, already meaningful
 * right after the G.A. prefill (D-5), and refines the moment the first
 * product is picked. `{}` while any in-flight resolution (product ->
 * category, or category -> labels) hasn't settled yet, so a slot never
 * flashes a stale/default label.
 */
export function useQuoteManagerLabels(
  offerLines: QuoteLineFormValues[],
  opportunityId: number | null,
): Record<string, string> {
  const revenue = useRevenueCategoryIds(offerLines)
  const fallback = useOpportunityCategoryIds(opportunityId)

  const usingFallback = revenue.categoryIds.length === 0
  const categoryIds = usingFallback ? fallback.categoryIds : revenue.categoryIds
  const isLoading = revenue.isLoading || (usingFallback && fallback.isLoading)

  const results = useQueries({
    queries: categoryIds.map((categoryId) => ({
      queryKey: categoryManagerLabelsQueryKey(categoryId),
      queryFn: () => fetchCategoryManagerLabels(categoryId),
      staleTime: 5 * 60 * 1000,
      retry: false,
    })),
  })

  if (isLoading || results.length === 0 || results.some((result) => !result.data)) {
    return {}
  }
  return resolveManagerLabels(results.map((result) => result.data as Record<string, string>))
}
