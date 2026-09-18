import { useMemo } from 'react'
import { useWatch, type Control } from 'react-hook-form'
import { indexCategoryTree } from '@/features/product-categories/business-function-inheritance'
import {
  resolveInheritedReportable,
  type InheritedReportable,
} from '@/features/product-categories/reportable-inheritance'
import { useProductCategoryTree } from '@/features/product-categories/use-product-category-tree'
import type { ProductCategoryFormMode } from '@/features/product-categories/types'
import type { ProductCategoryFormValues } from '@/features/product-categories/use-product-category-form'

export interface ReportableInheritanceState {
  /** The form's own override: null = inheriting. */
  override: boolean | null
  /** What the category would inherit under the live `parent_id`; null on a root. */
  inherited: InheritedReportable | null
  /** The value the reports will actually apply: override, else inherited. */
  effective: boolean
}

/**
 * Live report-flag inheritance for the category form, off the cached tree the
 * parent picker already loads (no extra request). Reacts to the WATCHED
 * `parent_id`, so a reparent previews its effect before saving.
 *
 * Until the tree resolves, an edit whose parent is still the saved one falls
 * back to the detail's own resolved values rather than flash a wrong state.
 */
export function useReportableInheritance(
  control: Control<ProductCategoryFormValues>,
  mode: ProductCategoryFormMode,
): ReportableInheritanceState {
  const parentId = useWatch({ control, name: 'parent_id' })
  const override = useWatch({ control, name: 'is_reportable' })
  const treeQuery = useProductCategoryTree()

  const nodesById = useMemo(() => indexCategoryTree(treeQuery.data ?? []), [treeQuery.data])

  const inherited = useMemo<InheritedReportable | null>(() => {
    if (treeQuery.data) {
      return resolveInheritedReportable(nodesById, parentId)
    }
    if (mode.type === 'edit' && parentId !== null && parentId === mode.category.parent_id) {
      const { category } = mode
      // Inheriting: the detail's effective value IS the parent's.
      return category.is_reportable === null
        ? { value: category.effective_is_reportable, sourceCategory: category.is_reportable_source_category }
        : null
    }
    return null
  }, [treeQuery.data, nodesById, parentId, mode])

  return { override, inherited, effective: override ?? inherited?.value ?? false }
}
