import { useMemo } from 'react'
import { useQueries } from '@tanstack/react-query'
import { categoryManagerLabelsQueryKey, fetchCategoryManagerLabels } from '@/features/opportunities/api'
import type { ProductLineRow } from '@/features/product-lines/types'
import { resolveManagerLabels } from '@/lib/utils'

/**
 * Re-exported from its shared home so this hook stays the single import
 * surface for the Opportunita' label path — the rule itself is shared with
 * the Offerta hook (spec 0087 D-8) and must not be duplicated.
 */
export { resolveManagerLabels }

/** Distinct, non-null `product_category_id`s of the form's current product lines — the resolver's own input set. */
function distinctCategoryIds(productLines: ProductLineRow[]): number[] {
  const ids = productLines
    .map((line) => line.product_category_id)
    .filter((id): id is number => id != null)
  return Array.from(new Set(ids))
}

/**
 * Resolves the G.A. labels for the opportunity form's CURRENT product lines
 * (spec 0080): fetches every distinct category's effective labels and
 * applies `resolveManagerLabels`. Works identically in create and edit — the
 * source is always the live `product_lines` field, never the persisted
 * `OpportunityDetail.manager_labels` (which only reflects what was true at
 * load time). `{}` while any of the in-flight fetches hasn't settled yet, so
 * a slot never flashes a stale label from a category the user has since
 * changed.
 */
export function useOpportunityManagerLabels(productLines: ProductLineRow[]): Record<string, string> {
  const categoryIds = useMemo(() => distinctCategoryIds(productLines), [productLines])

  const results = useQueries({
    queries: categoryIds.map((categoryId) => ({
      queryKey: categoryManagerLabelsQueryKey(categoryId),
      queryFn: () => fetchCategoryManagerLabels(categoryId),
      staleTime: 5 * 60 * 1000,
      retry: false,
    })),
  })

  if (results.length === 0 || results.some((result) => !result.data)) {
    return {}
  }
  return resolveManagerLabels(results.map((result) => result.data as Record<string, string>))
}
