import { useMemo } from 'react'
import { useWatch, type Control } from 'react-hook-form'
import { indexCategoryTree } from '@/features/product-categories/business-function-inheritance'
import {
  resolveInheritedSimplifiedOfferLine,
  type InheritedSimplifiedOfferLine,
} from '@/features/product-categories/simplified-offer-line-inheritance'
import type { ProductCategoryFormMode } from '@/features/product-categories/types'
import { useProductCategoryTree } from '@/features/product-categories/use-product-category-tree'
import type { ProductCategoryFormValues } from '@/features/product-categories/use-product-category-form'

export interface SimplifiedOfferLineInheritanceState {
  /** The form's own override: null = inheriting. */
  override: boolean | null
  /** What the category would inherit under the live `parent_id`; null on a root or while unresolved. */
  inherited: InheritedSimplifiedOfferLine | null
  /** The value Gestione Richieste will actually apply: override, else inherited. */
  effective: boolean
}

/**
 * Live simplified-offer-line inheritance for a CHILD category form (spec 0188),
 * off the cached tree the parent picker already loads (no extra request).
 * Until the tree resolves, an edit whose parent is still the saved one and
 * which inherits falls back to the detail's own resolved values.
 */
export function useSimplifiedOfferLineInheritance(
  control: Control<ProductCategoryFormValues>,
  mode: ProductCategoryFormMode,
): SimplifiedOfferLineInheritanceState {
  const parentId = useWatch({ control, name: 'parent_id' })
  const override = useWatch({ control, name: 'simplified_offer_line_override' })
  const ownValue = useWatch({ control, name: 'simplified_offer_line' })
  const treeQuery = useProductCategoryTree()

  const nodesById = useMemo(() => indexCategoryTree(treeQuery.data ?? []), [treeQuery.data])

  const inherited = useMemo<InheritedSimplifiedOfferLine | null>(() => {
    if (treeQuery.data) {
      return resolveInheritedSimplifiedOfferLine(nodesById, parentId)
    }
    if (mode.type === 'edit' && parentId !== null && parentId === mode.category.parent_id) {
      const { category } = mode
      return category.simplified_offer_line_override === null && category.simplified_offer_line_source_category
        ? { value: category.simplified_offer_line, sourceCategory: category.simplified_offer_line_source_category }
        : null
    }
    return null
  }, [treeQuery.data, nodesById, parentId, mode])

  return { override, inherited, effective: override ?? inherited?.value ?? ownValue }
}
