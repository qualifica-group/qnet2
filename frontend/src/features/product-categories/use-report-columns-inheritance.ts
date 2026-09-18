import { useWatch, type Control } from 'react-hook-form'
import type { ProductCategoryFormMode } from '@/features/product-categories/types'
import type { ProductCategoryFormValues } from '@/features/product-categories/use-product-category-form'

/** The columns a category would inherit, plus the ancestor carrying them (null: nothing configured). */
export interface InheritedReportColumns {
  keys: string[]
  sourceCategory: { id: number; name: string } | null
}

export interface ReportColumnsInheritanceState {
  /** The form's own override: null = inheriting. */
  override: string[] | null
  /** What the category would inherit — see the data-availability note below. */
  inherited: InheritedReportColumns
  /**
   * Whether `inherited` reflects the server's own resolution (edit, parent
   * unchanged) rather than the "nothing known" fallback — the field uses this
   * to tell "no ancestor configures anything" apart from "cannot tell yet".
   */
  inheritedDataAvailable: boolean
  /** The keys the picker actually shows as checked: override, else inherited. */
  effective: string[]
}

/** No ancestor data available: the fallback both a reparented edit and a create fall back to. */
const NO_INHERITANCE: InheritedReportColumns = { keys: [], sourceCategory: null }

/**
 * Live report-columns inheritance for the category form (spec 0141 AC-009,
 * rev-1 fix: the "back to inherited" action must never show for a category
 * that IS the configuration's origin — see `reportColumnsResetVisible`).
 *
 * Unlike `useReportableInheritance`, this does NOT walk the cached category
 * tree: `ProductCategoryTreeNode` carries no `report_columns` (the contract
 * never added it there — it is a config-sized catalogue selection, not a
 * per-node boolean), so a live reparent preview would need a request the
 * spec does not provision. Instead it reads `inherited_report_columns` /
 * `inherited_report_columns_source_category` straight off the loaded
 * category detail — resolved server-side from the ANCESTRY ALONE,
 * independent of the category's own value (spec 0141 rev-1) — while the
 * watched `parent_id` still matches the saved one; a reparent in the open
 * form, or a brand-new category, falls back to "nothing known" rather than
 * show a stale or invented ancestor.
 */
export function useReportColumnsInheritance(
  control: Control<ProductCategoryFormValues>,
  mode: ProductCategoryFormMode,
): ReportColumnsInheritanceState {
  const parentId = useWatch({ control, name: 'parent_id' })
  const override = useWatch({ control, name: 'report_columns' })

  const inheritedDataAvailable = mode.type === 'edit' && parentId === mode.category.parent_id
  const inherited: InheritedReportColumns = inheritedDataAvailable
    ? {
        keys: mode.category.inherited_report_columns,
        sourceCategory: mode.category.inherited_report_columns_source_category,
      }
    : NO_INHERITANCE

  return {
    override,
    inherited,
    inheritedDataAvailable,
    effective: override ?? inherited.keys,
  }
}

/**
 * Whether the "back to inherited" action makes sense (spec 0141 AC-009,
 * rev-1): only while the category has its OWN columns AND an ancestor
 * actually configures some to fall back to. A category that is the
 * configuration's ORIGIN (no ancestor configured) has nothing to revert to —
 * offering the action there reads as a no-op bug, not a reset.
 */
export function reportColumnsResetVisible(state: ReportColumnsInheritanceState): boolean {
  return state.override !== null && state.inherited.keys.length > 0
}
