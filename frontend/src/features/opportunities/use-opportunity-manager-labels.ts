import { useMemo } from 'react'
import { useQueries } from '@tanstack/react-query'
import { categoryManagerLabelsQueryKey, fetchCategoryManagerLabels } from '@/features/opportunities/api'
import type { ProductLineRow } from '@/features/product-lines/types'

/** Two resolved label maps are the SAME set when every position resolves to the identical string (order-independent). */
function sameManagerLabels(a: Record<string, string>, b: Record<string, string>): boolean {
  const aKeys = Object.keys(a)
  const bKeys = Object.keys(b)
  return aKeys.length === bKeys.length && aKeys.every((key) => a[key] === b[key])
}

/**
 * The `OpportunityManagerLabelResolver` univocity rule (spec 0080, decision
 * 1), mirrored client-side so the form's labels react live to the product
 * lines being edited instead of only to what the server resolved at load:
 * zero categories -> `{}`; one -> its own effective labels; several -> the
 * shared result ONLY when every one of them resolves to the IDENTICAL set
 * (compared on the resolved labels, not on the category id — two different
 * categories defining the same labels is not a conflict); otherwise `{}`.
 */
export function resolveManagerLabels(perCategory: Record<string, string>[]): Record<string, string> {
  if (perCategory.length === 0) {
    return {}
  }
  const [first, ...rest] = perCategory
  return rest.every((labels) => sameManagerLabels(labels, first)) ? first : {}
}

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
