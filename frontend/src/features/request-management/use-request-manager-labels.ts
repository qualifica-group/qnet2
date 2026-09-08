import { useMemo } from 'react'
import { useCategoryManagerLabels, useRevenueCategoryIds } from '@/features/quotes/use-quote-manager-labels'
import type { QuoteLineFormValues } from '@/features/quotes/quote-schema'
import type { ProductLineRow } from '@/features/product-lines/types'
import type { ManagerLabels } from '@/features/request-management/types'

/** Hoisted: the empty set handed to the resolver while the revenue rows are still resolving. */
const NO_CATEGORY_IDS: number[] = []

/** Distinct, non-null product categories of the form's own classification rows. */
function distinctCategoryIds(productLines: ProductLineRow[]): number[] {
  const ids = productLines
    .map((line) => line.product_category_id)
    .filter((id): id is number => id !== null)
  return Array.from(new Set(ids))
}

/**
 * Names the "Gestori Account" slots of THIS module's two forms (work panel
 * and create) from what the form CURRENTLY carries, not from what the server
 * resolved when the record was loaded (user directive 2026-09-08: changing
 * the categoria prodotto relabelled nothing, because `manager_labels` is
 * computed once, server-side, off the persisted record).
 *
 * The precedence is the server's own, unchanged (`QuoteManagerLabelResolver`,
 * spec 0087 D-8): the categories of the products on the offer's REVENUE rows
 * win, and only an offer with NO revenue row falls back on the request's own
 * "categoria prodotto" rows. Same rule as the Offerte form
 * (`useQuoteManagerLabels`) — the only difference is WHERE the fallback is
 * read from: the `product_lines` FIELD of this form, editable right above the
 * team section, rather than the persisted Opportunity.
 *
 * `null` = "this form has nothing to say yet" — no resolvable category, or a
 * resolution (product -> category, category -> labels) still in flight. The
 * caller keeps showing its own provisional value in that case (the panel's
 * server-resolved `manager_labels`, the create form's active category tab)
 * instead of flashing the default "G.A. n" denominations.
 */
export function useRequestManagerLabels(
  offerLines: QuoteLineFormValues[],
  productLines: ProductLineRow[],
): ManagerLabels | null {
  const revenue = useRevenueCategoryIds(offerLines)
  const fallbackCategoryIds = useMemo(() => distinctCategoryIds(productLines), [productLines])

  // The fallback is only asked for once the revenue rows have ANSWERED: while
  // their products are still resolving they may yet win, and fetching the
  // categoria prodotto's labels meanwhile would be a request whose result can
  // never be shown (`isResolved` is false throughout).
  const categoryIds = revenue.isLoading
    ? NO_CATEGORY_IDS
    : revenue.categoryIds.length > 0
      ? revenue.categoryIds
      : fallbackCategoryIds
  const { labels, isResolved } = useCategoryManagerLabels(categoryIds, revenue.isLoading)

  return isResolved && categoryIds.length > 0 ? labels : null
}
