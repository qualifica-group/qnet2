import { useMemo } from 'react'
import { useWatch, type Control, type FieldValues, type Path } from 'react-hook-form'
import {
  resolveManagementMode,
  resolveOfferVisibleCategoryIds,
  resolveSimplifiedOfferLine,
} from '@/features/product-lines/category-tree-scope'
import type { ProductLineRow } from '@/features/product-lines/types'
import type { ProductCategoryTreeNode } from '@/features/product-categories/types'
import { useProductCategoryTree } from '@/features/product-categories/use-product-category-tree'

/** Stable empty tree while the shared query is still loading: no fresh reference per render. */
const EMPTY_TREE: ProductCategoryTreeNode[] = []

export interface RequestOfferScope {
  categoryTree: ProductCategoryTreeNode[]
  /** The covered categories the offer may draw products from (spec 0414: hidden branches left out). */
  categoryIds: number[]
  /** No product line carries a category yet. */
  noCategory: boolean
  /** Spec 0414: every covered category hides the offer in Gestione Richieste. */
  offerHidden: boolean
  /** Spec 0077: one offer row under a `single`-managed category. */
  singleCategoryMode: boolean
  /** Spec 0114: the offer is a plain product choice, the server congeals quantity/price/VAT. */
  simplified: boolean
}

/**
 * The offer rules the classification watched on the form resolves to, off the
 * cached category tree (no extra request). `ignoreHiddenRule` is for surfaces
 * outside Gestione Richieste (the contract detail), where spec 0414 does not apply.
 */
export function useRequestOfferScope<TFieldValues extends FieldValues>(
  control: Control<TFieldValues>,
  ignoreHiddenRule = false,
): RequestOfferScope {
  // The form shape guarantees the key, but TS cannot narrow a literal to
  // `Path<TFieldValues>` through the generic (same idiom as `QuoteWorkflowStatusField`).
  const productLines = useWatch({ control, name: 'product_lines' as Path<TFieldValues> }) as ProductLineRow[] | undefined
  const categoryTree = useProductCategoryTree().data ?? EMPTY_TREE

  const coveredIds = useMemo(
    () => [
      ...new Set(
        (productLines ?? []).map((line) => line.product_category_id).filter((id): id is number => id !== null),
      ),
    ],
    [productLines],
  )
  const categoryIds = useMemo(
    () => (ignoreHiddenRule ? coveredIds : resolveOfferVisibleCategoryIds(categoryTree, coveredIds)),
    [ignoreHiddenRule, categoryTree, coveredIds],
  )

  return {
    categoryTree,
    categoryIds,
    noCategory: coveredIds.length === 0,
    offerHidden: coveredIds.length > 0 && categoryIds.length === 0,
    // Resolved over the categories the offer draws from: a hidden branch no longer governs it.
    singleCategoryMode: resolveManagementMode(categoryTree, categoryIds) === 'single',
    simplified: resolveSimplifiedOfferLine(categoryTree, categoryIds),
  }
}
