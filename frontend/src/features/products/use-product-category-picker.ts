import { useMemo } from 'react'
import { useProductCategoryTree } from '@/features/product-categories/use-product-category-tree'
import { activeCategoryTree } from '@/features/product-categories/active-tree'
import {
  collectSelectableIds,
  flattenCategoryTree,
  type FlatCategoryOption,
} from '@/features/product-categories/flatten-tree'
import type { ProductCategoryTreeNode } from '@/features/product-categories/types'

/** Hoisted so the memo keeps a stable reference across renders. */
const NO_CATEGORY_IDS: readonly number[] = []
const EMPTY_TREE: ProductCategoryTreeNode[] = []

/** Everything the category picker needs, resolved once per record (the rows, the header and the editor read it). */
export interface ProductCategoryPicker {
  options: FlatCategoryOption[]
  isPending: boolean
  isError: boolean
  onRetry: () => void
}

/**
 * The product category picker's options (spec 0074): a DESTINATION served by
 * the structural tree cache, filtered client-side. An unselectable category is
 * listed, disabled (user directive 2026-08-03); an inactive one drops out with
 * its branch (spec 0208) — except the path to the product's saved category,
 * kept even when unselectable (D-3b) so an untouched record never blanks out.
 */
export function useProductCategoryPicker(savedCategoryId: number | null): ProductCategoryPicker {
  const treeQuery = useProductCategoryTree()
  const keepIds = useMemo(
    () => (savedCategoryId !== null ? [savedCategoryId] : NO_CATEGORY_IDS),
    [savedCategoryId],
  )
  const options = useMemo(() => {
    const tree = activeCategoryTree(treeQuery.data ?? EMPTY_TREE, keepIds)
    return flattenCategoryTree(tree, { pickableIds: collectSelectableIds(tree), keepIds })
  }, [treeQuery.data, keepIds])

  return {
    options,
    isPending: treeQuery.isPending,
    isError: treeQuery.isError,
    onRetry: () => void treeQuery.refetch(),
  }
}
