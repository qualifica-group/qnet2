import { useProductCategoryTree } from '@/features/product-categories/use-product-category-tree'
import type { CategoryManagementMode, ProductCategoryTreeNode } from '@/features/product-categories/types'
import { resolveRowSetManagementMode, rootCategoryIdFor } from '@/features/product-lines/category-tree-scope'
import { emptyProductLineRow, type ProductLineRow } from '@/features/product-lines/types'

/** Stable empty tree while the shared query is still loading: no fresh reference per render. */
const EMPTY_TREE: ProductCategoryTreeNode[] = []

interface UseProductLinesFieldArgs {
  /** The `product_lines` field's current value (RHF or plain state, mirrors `ManagerSlotsField`). */
  value: ProductLineRow[]
  onChange: (next: ProductLineRow[]) => void
}

/**
 * Owns the CARD row editor (spec 0132): "Add" appends an EMPTY row, each row
 * is edited IN PLACE and INDEPENDENTLY of the others (spec 0077 rev.2) —
 * picking a root resets that row's category (D-9) — and a row is removed
 * outright. The competence editor is a separate component/hook
 * (`useCompetenceLinesField`): the two contracts no longer share this one
 * (spec 0132 constraint).
 */
export function useProductLinesField({ value, onChange }: UseProductLinesFieldArgs) {
  // Spec 0077: the card's policy is resolved against the SAME cached category
  // tree the row pickers render — no extra request — so it is known for the
  // rows loaded on edit too, not only for those picked in this session.
  const categoryTree = useProductCategoryTree().data ?? EMPTY_TREE

  const managementMode: CategoryManagementMode | null = resolveRowSetManagementMode(value, categoryTree)
  // AC-041: a single-mode card has exactly one row (INV-3); the "Add" action
  // stops being available the moment that mode resolves.
  const canAddRow = managementMode !== 'single'

  /**
   * The root category a row's FIRST select shows (spec 0132 AC-017): the
   * row's own explicit pick if it has one, otherwise resolved by walking the
   * tree up from the persisted category — an edit-loaded row carries only
   * `product_category_id`, never a root of its own. Pure derivation off the
   * cached tree, no state, no fetch.
   */
  const rootCategoryFor = (row: ProductLineRow): number | null =>
    row.root_category_id ?? (row.product_category_id !== null ? rootCategoryIdFor(categoryTree, row.product_category_id) : null)

  const addRow = () => {
    // Defense in depth: the caller already hides/disables "Add" once
    // `canAddRow` is false (AC-041), this guards a direct call too.
    if (!canAddRow) {
      return
    }
    onChange([...value, emptyProductLineRow()])
  }

  const removeRow = (index: number) => {
    onChange(value.filter((_, rowIndex) => rowIndex !== index))
  }

  /**
   * AC-016: only the edited row's category is reset — it was scoped by the
   * previous root — every other row is left alone (spec 0077 rev.2 rows are
   * independent).
   */
  const setRowRootCategory = (index: number, rootCategoryId: number | null) => {
    const next = value.map((row, rowIndex) =>
      rowIndex === index ? { root_category_id: rootCategoryId, product_category_id: null } : row,
    )
    onChange(next)
  }

  const setRowProductCategory = (index: number, productCategoryId: number | null) => {
    const next = value.map((row, rowIndex) =>
      rowIndex === index ? { ...row, product_category_id: productCategoryId } : row,
    )
    onChange(next)
  }

  return {
    addRow,
    removeRow,
    setRowRootCategory,
    setRowProductCategory,
    rootCategoryFor,
    /** `false` once the resolved mode is `single` (AC-041); consumed to hide/disable "Add". */
    canAddRow,
    /** The resolved mode for this row set, `null` while indeterminate (spec 0077, point 4). */
    managementMode,
  }
}
