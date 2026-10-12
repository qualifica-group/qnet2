import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import {
  ROOT_PARENT_VALUE,
  collectSubtreeIds,
  flattenCategoryTree,
} from '@/features/product-categories/flatten-tree'
import { useProductCategoryTree } from '@/features/product-categories/use-product-category-tree'

const NO_EXCLUDED_IDS = new Set<number>()

/**
 * Everything the parent picker needs to render, off the cached category tree.
 * A category may never be reparented under its own subtree: the picker drops
 * `categoryId`'s subtree client-side as an affordance, the server enforces it.
 */
export function useProductCategoryParentPicker(categoryId?: number) {
  const { t } = useTranslation()
  const treeQuery = useProductCategoryTree()

  const options = useMemo(() => {
    const nodes = treeQuery.data ?? []
    const excluded = categoryId === undefined ? NO_EXCLUDED_IDS : collectSubtreeIds(nodes, categoryId)
    return [
      { id: ROOT_PARENT_VALUE, name: t('productCategories.form.noParent') },
      ...flattenCategoryTree(nodes).filter((option) => !excluded.has(option.id)),
    ]
  }, [treeQuery.data, categoryId, t])

  return {
    options,
    isPending: treeQuery.isPending,
    isError: treeQuery.isError,
    onRetry: () => void treeQuery.refetch(),
  }
}

export type ProductCategoryParentPicker = ReturnType<typeof useProductCategoryParentPicker>
