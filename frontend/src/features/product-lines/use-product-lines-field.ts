import { useState } from 'react'
import { collectSubtreeIds } from '@/features/product-categories/flatten-tree'
import { useProductCategoryTree } from '@/features/product-categories/use-product-category-tree'
import type { CategoryManagementMode, ProductCategoryTreeNode } from '@/features/product-categories/types'
import {
  filterCategoriesUnderRoot,
  resolveManagementMode,
  resolveRowSetManagementMode,
  rootCategoryIdFor,
} from '@/features/product-lines/category-tree-scope'
import {
  flattenProductLineGroups,
  groupProductLineRows,
  type ProductLineGroup,
} from '@/features/product-lines/product-line-groups'
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
 * Owns the CARD row editor (spec 0132). Since spec 0416 an editor row is a
 * GROUP — one parent (root) category with every category picked under it —
 * folded from and flattened back to the flat `value` (one row per category,
 * the unchanged wire shape), see `product-line-groups.ts`. "Add" appends an
 * EMPTY group, picking a root resets that group's categories (D-9), and a
 * group is removed outright. A root already held by another group is not
 * offered again: its categories go in that group. A `multiple` root takes
 * several categories, a `single` one exactly one. The competence editor is a
 * separate component/hook (`useCompetenceLinesField`). A group may also carry
 * an optional intermediate filter between root and category, see {@link RowFilter}.
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
   * The root category of a flat row (spec 0132 AC-017): its own explicit pick
   * if it has one, otherwise resolved by walking the tree up from the
   * persisted category — an edit-loaded row carries only
   * `product_category_id`. Pure derivation off the cached tree.
   */
  const rowRootCategory = (row: ProductLineRow): number | null =>
    row.root_category_id ?? (row.product_category_id !== null ? rootCategoryIdFor(categoryTree, row.product_category_id) : null)

  const groups = groupProductLineRows(value, rowRootCategory)

  const writeGroups = (next: ProductLineGroup[]) => onChange(flattenProductLineGroups(next))

  const updateGroup = (index: number, group: ProductLineGroup) =>
    writeGroups(groups.map((current, groupIndex) => (groupIndex === index ? group : current)))

  /** The root a group's FIRST select shows. */
  const rootCategoryFor = (index: number): number | null => groups[index]?.rootCategoryId ?? null

  /**
   * Whether the group's root takes several categories: a root known to be
   * `multiple`. An unknown mode (no root yet, tree not loaded) keeps the
   * single pick, the conservative choice.
   */
  const isMultipleRow = (index: number): boolean => {
    const rootCategoryId = rootCategoryFor(index)
    return rootCategoryId !== null && resolveManagementMode(categoryTree, [rootCategoryId]) === 'multiple'
  }

  /**
   * Spec 0077 AC-047: whether any group OTHER than `index` already has a root
   * — then a `single` root is not offered to `index`, it would make the card
   * `single` with more than one row (INV-3).
   */
  const otherRowsFilled = (index: number): boolean =>
    groups.some((group, groupIndex) => groupIndex !== index && group.rootCategoryId !== null)
  const singleRootsBlocked = groups.some((_, index) => otherRowsFilled(index))

  /** The roots other groups already hold: listed disabled, their categories belong in that group. */
  const takenRootIds = (index: number): number[] =>
    groups.flatMap((group, groupIndex) =>
      groupIndex !== index && group.rootCategoryId !== null ? [group.rootCategoryId] : [],
    )

  /** The group's intermediate filter, or `null` when absent or added under a root the group no longer has. */
  const filterFor = (index: number): RowFilter | null => {
    const filter = rowFilters[index]
    return filter !== undefined && filter.rootCategoryId === rootCategoryFor(index) ? filter : null
  }

  /** Whether the group's root has any grouping child to filter by: drives the "Filtro" action. */
  const canFilterRow = (index: number): boolean => {
    const rootCategoryId = rootCategoryFor(index)
    return rootCategoryId !== null && filterCategoriesUnderRoot(categoryTree, rootCategoryId).length > 0
  }

  /** What scopes the group's category select: the picked intermediate filter, the root otherwise. */
  const categoryScopeFor = (index: number): number | null => filterFor(index)?.filterCategoryId ?? rootCategoryFor(index)

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
    // The filters are positional like the groups: drop the removed one's too.
    setRowFilters((current) => current.filter((_, groupIndex) => groupIndex !== index))
    writeGroups(groups.filter((_, groupIndex) => groupIndex !== index))
  }

  const addRowFilter = (index: number) => {
    const rootCategoryId = rootCategoryFor(index)
    if (rootCategoryId !== null) {
      writeRowFilter(index, { rootCategoryId, filterCategoryId: null })
    }
  }

  /**
   * Picks the intermediate filter. On a single-category group, a category
   * outside the filter's subtree is reset — it would no longer be listed. A
   * multi-category group keeps its picks: they stay listed (and removable)
   * under the root.
   */
  const setRowFilterCategory = (index: number, filterCategoryId: number) => {
    const rootCategoryId = rootCategoryFor(index)
    if (rootCategoryId === null) {
      return
    }
    writeRowFilter(index, { rootCategoryId, filterCategoryId })
    if (isMultipleRow(index)) {
      return
    }
    const subtree = collectSubtreeIds(categoryTree, filterCategoryId)
    const categoryIds = groups[index].categoryIds
    if (categoryIds.some((categoryId) => !subtree.has(categoryId))) {
      updateGroup(index, { rootCategoryId, categoryIds: [] })
    }
  }

  /** Removing the filter keeps the categories: they still hang from the group's root. */
  const removeRowFilter = (index: number) => {
    writeRowFilter(index, undefined)
  }

  /** AC-016: only the edited group's categories are reset — they were scoped by the previous root. */
  const setRowRootCategory = (index: number, rootCategoryId: number | null) => {
    writeRowFilter(index, undefined)
    updateGroup(index, { rootCategoryId, categoryIds: [] })
  }

  /** The single-category pick (a `single` root). */
  const setRowProductCategory = (index: number, productCategoryId: number | null) => {
    updateGroup(index, {
      rootCategoryId: rootCategoryFor(index),
      categoryIds: productCategoryId === null ? [] : [productCategoryId],
    })
  }

  /** The multi-category pick (a `multiple` root), replacing the group's categories. */
  const setRowProductCategories = (index: number, productCategoryIds: number[]) => {
    updateGroup(index, { rootCategoryId: rootCategoryFor(index), categoryIds: productCategoryIds })
  }

  /** Adds one category to the group (the quick-create of a new category). */
  const addRowProductCategory = (index: number, productCategoryId: number) => {
    const categoryIds = groups[index]?.categoryIds ?? []
    if (!isMultipleRow(index)) {
      setRowProductCategory(index, productCategoryId)
    } else if (!categoryIds.includes(productCategoryId)) {
      setRowProductCategories(index, [...categoryIds, productCategoryId])
    }
  }

  return {
    /** The editor rows: one per root, see {@link ProductLineGroup}. */
    groups,
    addRow,
    removeRow,
    setRowRootCategory,
    setRowProductCategory,
    setRowProductCategories,
    addRowProductCategory,
    rootCategoryFor,
    isMultipleRow,
    takenRootIds,
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
