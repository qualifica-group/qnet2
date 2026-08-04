import type { LayoutFormScope } from '@/features/attributes/attribute-layout-types'
import type { AttributeContext } from '@/features/product-categories/types'

/** Centralized TanStack Query keys for the product-categories domain. */
export const productCategoryKeys = {
  tree: ['product-categories', 'tree'] as const,
  detail: (id: number) => ['product-categories', 'detail', id] as const,
  effectiveAttributes: (categoryId: number, context: AttributeContext) =>
    ['product-categories', categoryId, 'effective-attributes', context] as const,
  effectiveManagerLabels: (categoryId: number) =>
    ['product-categories', categoryId, 'effective-manager-labels'] as const,
  attributeLayout: (categoryId: number, context: AttributeContext, scope: LayoutFormScope) =>
    ['product-categories', categoryId, 'attribute-layout', context, scope] as const,
}
