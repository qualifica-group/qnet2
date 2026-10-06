import { useCallback, useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { useController, type Control, type Path } from 'react-hook-form'
import { toast } from 'sonner'
import { useResourcePermissions } from '@/features/authorization/permissions'
import type { ForSelectItem } from '@/features/for-select/types'
import { useForSelectLabels } from '@/features/for-select/use-for-select'
import type { ProductLineRow } from '@/features/product-lines/types'
import { PRODUCTS_FOR_SELECT_RESOURCE, productCategoryIdOf } from '@/features/products/for-select-api'
import type { QuoteLineFormValues } from '@/features/quotes/quote-schema'
import { createEmptyLineRow } from '@/features/quotes/use-quote-lines-field'
import type { RequestOfferLinesFormShape } from '@/features/request-management/request-offer-lines-section'

/** Authorization key of the collection this hook writes (the same one `useOfferLinesAutofill` checks). */
const OFFER_LINES_META_KEY = 'offer_lines'

interface ResetOfferRowsResult {
  rows: QuoteLineFormValues[]
  /** Labels of the products taken off the rows, for the notice. */
  droppedLabels: string[]
}

/**
 * Empties, in place, every offer row whose product hangs from a category
 * the classification no longer covers: the row stays where it was, back to
 * an empty picker scoped to the new categories. A product whose category is
 * not resolved (yet) is KEPT, exactly like the products-of-interest pruning:
 * dropping a pick on incomplete information is worse than letting the server
 * have the last word. Returns the SAME array when nothing changed.
 */
export function resetUncoveredOfferRows(
  rows: QuoteLineFormValues[],
  productLines: ProductLineRow[],
  productsById: ReadonlyMap<number, ForSelectItem>,
): ResetOfferRowsResult {
  const covered = new Set(
    productLines.map((line) => line.product_category_id).filter((id): id is number => id !== null),
  )
  const droppedLabels: string[] = []

  const nextRows = rows.map((row) => {
    const product = row.product_id === null ? undefined : productsById.get(row.product_id)
    const categoryId = product === undefined ? null : productCategoryIdOf(product)

    if (product === undefined || categoryId === null || covered.has(categoryId)) {
      return row
    }

    droppedLabels.push(product.label)

    return createEmptyLineRow()
  })

  return { rows: droppedLabels.length > 0 ? nextRows : rows, droppedLabels }
}

/**
 * The offer-rows half of the classification coherence (bug 2026-10-05):
 * replacing a product line used to leave the offer row on a product of the
 * category just removed, while its picker already offered only the new
 * category's products — and the save then put the old category back
 * (`OpportunityProductLineCoverage` widens the classification to cover it).
 *
 * Same shape as `useProductsOfInterestCoherence`: the categories changing is
 * a USER EVENT on the product-lines field, so the correction is a function
 * its `onChange` calls, not a render-time effect (which would also fire on
 * hydration). The product -> category mapping comes from the cached by-ids
 * for-select query (`meta.category_id`), persisted and freshly picked rows
 * alike. A field the actor may not write is not written from here either.
 */
export function useOfferLinesCoherence<TFieldValues extends RequestOfferLinesFormShape>(
  control: Control<TFieldValues>,
): (productLines: ProductLineRow[]) => void {
  const { t } = useTranslation()
  const permission = useResourcePermissions().field(OFFER_LINES_META_KEY)
  const editable = permission.visible && permission.editable && !permission.disabled

  // Same cast as `RequestOfferLinesField`: TS cannot narrow the literal to
  // `Path<TFieldValues>` through the generic.
  const { field } = useController({ control, name: 'offer_lines' as Path<TFieldValues> })
  const rows = field.value as QuoteLineFormValues[]

  const productIds = useMemo(
    () => [...new Set(rows.map((row) => row.product_id).filter((id): id is number => id !== null))],
    [rows],
  )
  const productsById = useForSelectLabels({
    resource: PRODUCTS_FOR_SELECT_RESOURCE,
    ids: productIds,
    enabled: editable && productIds.length > 0,
  })

  return useCallback(
    (productLines: ProductLineRow[]) => {
      if (!editable) {
        return
      }

      const { rows: nextRows, droppedLabels } = resetUncoveredOfferRows(rows, productLines, productsById)

      if (droppedLabels.length === 0) {
        return
      }

      field.onChange(nextRows)
      toast.warning(t('requestManagement.offerLines.prunedNotice', { names: droppedLabels.join(', ') }))
    },
    [editable, field, productsById, rows, t],
  )
}
