import { useQuery } from '@tanstack/react-query'
import { fetchEffectiveManagerLabels } from '@/features/product-categories/api'
import { productCategoryKeys } from '@/features/product-categories/query-keys'

/**
 * Loads a category's effective manager labels (own + inherited), gated on a
 * selected category id. Mirrors `useEffectiveAttributes`: the category form
 * passes the selected PARENT's id to preview what the child would inherit
 * (spec 0080).
 */
export function useEffectiveManagerLabels(categoryId: number | null) {
  return useQuery({
    queryKey: productCategoryKeys.effectiveManagerLabels(categoryId ?? 0),
    queryFn: () => fetchEffectiveManagerLabels(categoryId as number),
    enabled: categoryId !== null,
  })
}
