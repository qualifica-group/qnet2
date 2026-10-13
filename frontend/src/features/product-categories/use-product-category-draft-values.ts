import { useMemo } from 'react'
import { useWatch, type Control } from 'react-hook-form'
import { BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE } from '@/features/business-functions/for-select-api'
import { useForSelectLabels } from '@/features/for-select/use-for-select'
import {
  buildDraftRecordValues,
  draftBusinessFunctionId,
  type ProductCategoryRecordValues,
} from '@/features/product-categories/product-category-record'
import type { ProductCategoryTreeNode } from '@/features/product-categories/types'
import { useProductCategoryTree } from '@/features/product-categories/use-product-category-tree'
import type { ProductCategoryFormValues } from '@/features/product-categories/use-product-category-form'

/** Stable empty defaults: a fresh value per render would break the label hook's memo. */
const NO_IDS: number[] = []
const NO_TREE: ProductCategoryTreeNode[] = []

/** Shown while a label resolves: never the bare id. */
const PENDING_LABEL = '…'

/**
 * The create draft as the record's closed rows read it (spec 0229): the form
 * holds ids only, so the parent and the inherited rules resolve off the
 * cached category tree (the one the parent picker loads) and the business
 * function through `useForSelectLabels`, the cache its picker fills.
 */
export function useProductCategoryDraftValues(control: Control<ProductCategoryFormValues>): ProductCategoryRecordValues {
  const draft = useWatch({ control })
  const tree = useProductCategoryTree().data ?? NO_TREE
  const businessFunctionId = draftBusinessFunctionId(draft, tree)
  const ids = useMemo(() => (businessFunctionId === null ? NO_IDS : [businessFunctionId]), [businessFunctionId])
  const labels = useForSelectLabels({ resource: BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE, ids })

  return useMemo(
    () => buildDraftRecordValues(draft, tree, (id) => labels.get(id)?.label ?? PENDING_LABEL),
    [draft, tree, labels],
  )
}
