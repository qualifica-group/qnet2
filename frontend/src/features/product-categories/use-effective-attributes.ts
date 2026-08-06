import { useQuery } from '@tanstack/react-query'
import { fetchEffectiveAttributes } from '@/features/product-categories/api'
import { productCategoryKeys } from '@/features/product-categories/query-keys'
import type { AttributeContext } from '@/features/product-categories/types'

/**
 * Loads a category's effective attributes (own + inherited) for a single
 * usage context, gated on a selected category id. Used by the category form's
 * per-context inherited list and by the product form to generate its dynamic
 * attribute fields (spec 0061): the query key includes both the category id
 * and the context, so switching either re-fetches and the caller regenerates
 * the fields.
 */
export function useEffectiveAttributes(categoryId: number | null, context: AttributeContext) {
  return useQuery({
    queryKey: productCategoryKeys.effectiveAttributes(categoryId ?? 0, context),
    queryFn: () => fetchEffectiveAttributes(categoryId as number, context),
    enabled: categoryId !== null,
  })
}
