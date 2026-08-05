import { useCallback } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { useForSelectLabels } from '@/features/for-select/use-for-select'
import { PRODUCTS_FOR_SELECT_RESOURCE, productCategoryIdOf } from '@/features/products/for-select-api'
import type { ProductLineRow } from '@/features/product-lines/types'

/**
 * The form half of the coherence rule (spec 0075, D-5; extended to the
 * opportunity form by the user directive 2026-08-05): every product of
 * interest must belong to a product category the record carries. The server
 * refuses the incoherent set on every write channel of both modules
 * (`ProductCategoryCoherence`); what this hook adds is that the operator never
 * reaches that refusal — removing (or re-pointing) a product line drops the
 * products it was covering, with a toast naming them.
 *
 * It is a pruning function, not an effect: the categories changing is a USER
 * EVENT on the product-lines field, so the correction belongs to that event's
 * handler — the same shape `useProductLinesField` already uses when picking a
 * business function clears the row's category.
 *
 * The product -> category mapping comes from the cached by-ids for-select query
 * (`meta.category_id`), the same one the picker's own badges resolve through:
 * no extra endpoint, one request per distinct selection.
 */
export function useProductsOfInterestCoherence(productIds: number[]) {
  const { t } = useTranslation()
  const selectedProducts = useForSelectLabels({
    resource: PRODUCTS_FOR_SELECT_RESOURCE,
    ids: productIds,
    enabled: productIds.length > 0,
  })

  /**
   * The products still covered by $rows. A product whose category is not
   * resolved (yet) is KEPT: dropping a selection on incomplete information
   * would be worse than letting the server have the last word.
   */
  return useCallback(
    (rows: ProductLineRow[]): number[] => {
      const covered = new Set(
        rows.map((row) => row.product_category_id).filter((id): id is number => id !== null),
      )
      const dropped: string[] = []

      const kept = productIds.filter((productId) => {
        const product = selectedProducts.get(productId)

        if (product === undefined) {
          return true
        }

        const categoryId = productCategoryIdOf(product)

        if (categoryId === null || covered.has(categoryId)) {
          return true
        }

        dropped.push(product.label)

        return false
      })

      if (dropped.length > 0) {
        toast.warning(t('products.ofInterest.prunedNotice', { names: dropped.join(', ') }))
      }

      return kept
    },
    [productIds, selectedProducts, t],
  )
}
