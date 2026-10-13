import { useState } from 'react'
import { collectSubtreeIds } from '@/features/product-categories/flatten-tree'
import { useProductCategoryTree } from '@/features/product-categories/use-product-category-tree'
import type { CategoryManagementMode, ProductCategoryTreeNode } from '@/features/product-categories/types'
import {
  filterCategoriesUnderRoot,
  resolveRowSetManagementMode,
  rootCategoryIdFor,
} from '@/features/product-lines/category-tree-scope'
import { emptyProductLineRow, type ProductLineRow } from '@/features/product-lines/types'

/** Stable empty tree while the shared query is still loading: no fresh reference per render. */
const EMPTY_TREE: ProductCategoryTreeNode[] = []

/**
 * A row's optional intermediate filter (user directive 2026-10-08), added by
 * the operator. UI-only state, kept OUTSIDE the form value so it never
 * reaches a schema or the wire: bound to the root it was added under, so a
 * root change or an external reset of the rows silently drops it.
 */
interface RowFilter {
  rootCategoryId: number
  /** `null` while the filter is shown but nothing is picked in it yet. */
  filterCategoryId: number | null
}

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
 * (spec 0132 constraint). A row may also carry an optional intermediate
 * filter between root and category, see {@link RowFilter}.
 */
export function useProductLinesField({ value, onChange }: UseProductLinesFieldArgs) {
  // Spec 0077: the card's policy is resolved against the SAME cached category
  // tree the row pickers render — no extra request — so it is known for the
  // rows loaded on edit too, not only for those picked in this session.
  const categoryTree = useProductCategoryTree().data ?? EMPTY_TREE
  const [rowFilters, setRowFilters] = useState<(RowFilter | undefined)[]>([])

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

  /**
   * Spec 0077 AC-047: whether any row OTHER than `index` already has a root
   * — then a `single` root is not offered to `index`, it would make the card
   * `single` with more than one row (INV-3).
   */
  const otherRowsFilled = (index: number): boolean =>
    value.some((row, rowIndex) => rowIndex !== index && rootCategoryFor(row) !== null)
  const singleRootsBlocked = value.some((_, index) => otherRowsFilled(index))

  /** The row's intermediate filter, or `null` when absent or added under a root the row no longer has. */
  const filterFor = (index: number): RowFilter | null => {
    const filter = rowFilters[index]
    const row = value[index]
    return filter !== undefined && row !== undefined && filter.rootCategoryId === rootCategoryFor(row) ? filter : null
  }

  /** Whether the row's root has any grouping child to filter by: drives the "Filtro" action. */
  const canFilterRow = (index: number): boolean => {
    const rootCategoryId = value[index] === undefined ? null : rootCategoryFor(value[index])
    return rootCategoryId !== null && filterCategoriesUnderRoot(categoryTree, rootCategoryId).length > 0
  }

  /**
   * What scopes the row's category select: the picked intermediate filter
   * when there is one, the root otherwise — the last select then lists only
   * that filter's subtree.
   */
  const categoryScopeFor = (index: number): number | null =>
    filterFor(index)?.filterCategoryId ?? (value[index] === undefined ? null : rootCategoryFor(value[index]))

  const writeRowFilter = (index: number, filter: RowFilter | undefined) => {
    setRowFilters((current) => {
      const next = [...current]
      next[index] = filter
      return next
    })
  }

  const addRow = () => {
    // Defense in depth: the caller already hides/disables "Add" once
    // `canAddRow` is false (AC-041), this guards a direct call too.
    if (!canAddRow) {
      return
    }
    onChange([...value, emptyProductLineRow()])
  }

  const removeRow = (index: number) => {
    // The filters are positional like the rows: drop the removed row's too,
    // so the following ones stay on their own row.
    setRowFilters((current) => current.filter((_, rowIndex) => rowIndex !== index))
    onChange(value.filter((_, rowIndex) => rowIndex !== index))
  }

  const addRowFilter = (index: number) => {
    const rootCategoryId = value[index] === undefined ? null : rootCategoryFor(value[index])
    if (rootCategoryId !== null) {
      writeRowFilter(index, { rootCategoryId, filterCategoryId: null })
    }
  }

  /**
   * Picks the intermediate filter. A category already chosen outside the
   * filter's subtree is reset — it would no longer be listed. The root is
   * written explicitly: an edit-loaded row only derives it from its category.
   */
  const setRowFilterCategory = (index: number, filterCategoryId: number) => {
    const row = value[index]
    const rootCategoryId = row === undefined ? null : rootCategoryFor(row)
    if (row === undefined || rootCategoryId === null) {
      return
    }
    writeRowFilter(index, { rootCategoryId, filterCategoryId })
    const categoryId = row.product_category_id
    if (categoryId !== null && !collectSubtreeIds(categoryTree, filterCategoryId).has(categoryId)) {
      onChange(
        value.map((current, rowIndex) =>
          rowIndex === index ? { root_category_id: rootCategoryId, product_category_id: null } : current,
        ),
      )
    }
  }

  /** Removing the filter keeps the category: it still hangs from the row's root. */
  const removeRowFilter = (index: number) => {
    writeRowFilter(index, undefined)
  }

  /**
   * AC-016: only the edited row's category is reset — it was scoped by the
   * previous root — every other row is left alone (spec 0077 rev.2 rows are
   * independent).
   */
  const setRowRootCategory = (index: number, rootCategoryId: number | null) => {
    writeRowFilter(index, undefined)
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
    filterFor,
    canFilterRow,
    categoryScopeFor,
    addRowFilter,
    setRowFilterCategory,
    removeRowFilter,
    otherRowsFilled,
    /** Some row is offered no `single` root (AC-047): drives the explanatory note. */
    singleRootsBlocked,
    /** `false` once the resolved mode is `single` (AC-041); consumed to hide/disable "Add". */
    canAddRow,
    /** The resolved mode for this row set, `null` while indeterminate (spec 0077, point 4). */
    managementMode,
  }
}
