import type { AttributeContext } from '@/features/product-categories/types'

/** Centralized TanStack Query keys for the product-categories domain. */
export const productCategoryKeys = {
  tree: ['product-categories', 'tree'] as const,
  detail: (id: number) => ['product-categories', 'detail', id] as const,
  effectiveAttributes: (categoryId: number, context: AttributeContext) =>
    ['product-categories', categoryId, 'effective-attributes', context] as const,
}
