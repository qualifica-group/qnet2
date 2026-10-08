import { useMemo } from 'react'
import { useWatch, type Control } from 'react-hook-form'
import { findInactiveAncestor } from '@/features/product-categories/active-inheritance'
import { indexCategoryTree } from '@/features/product-categories/business-function-inheritance'
import { useProductCategoryTree } from '@/features/product-categories/use-product-category-tree'
import type { ProductCategoryFormValues } from '@/features/product-categories/use-product-category-form'

/**
 * The inactive ancestor of the category being edited, off the cached tree and
 * the WATCHED `parent_id` (a reparent previews the effect before saving).
 * `null` while the tree loads or when nothing above is inactive.
 */
export function useInactiveAncestor(control: Control<ProductCategoryFormValues>) {
  const parentId = useWatch({ control, name: 'parent_id' })
  const treeQuery = useProductCategoryTree()

  return useMemo(
    () => findInactiveAncestor(indexCategoryTree(treeQuery.data ?? []), parentId),
    [treeQuery.data, parentId],
  )
}
