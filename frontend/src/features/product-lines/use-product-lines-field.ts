import { useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { fetchForSelect } from '@/features/for-select/api'
import { BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE } from '@/features/business-functions/for-select-api'
import { useProductCategoryTree } from '@/features/product-categories/use-product-category-tree'
import type { CategoryManagementMode, ProductCategoryTreeNode } from '@/features/product-categories/types'
import { resolveRowSetManagementMode } from '@/features/product-lines/category-tree-scope'
import { emptyProductLineRow, type ProductLine, type ProductLineRow } from '@/features/product-lines/types'

type LabelMap = Record<number, string>

/** Stable empty tree while the shared query is still loading: no fresh reference per render. */
const EMPTY_TREE: ProductCategoryTreeNode[] = []

interface UseProductLinesFieldArgs {
  /** The `product_lines` field's current value (RHF or plain state, mirrors `ManagerSlotsField`). */
  value: ProductLineRow[]
  onChange: (next: ProductLineRow[]) => void
  /** Rows whose labels are already known without a fetch: edit load, from-lead prefill, in-form pickers. */
  knownLines: ProductLine[]
  /** Spec 0111 D-5: `false` only where the row set is NOT a commercial card (see `canAddRow`). */
  enforceManagementModeCap?: boolean
}

/** Builds the `{id: name}` lookup out of a set of already-labeled lines. The CATEGORY labels need no map: the row's picker reads them off the tree it already renders. */
function indexKnownLabels(lines: ProductLine[]): LabelMap {
  const businessFunction: LabelMap = {}
  for (const line of lines) {
    businessFunction[line.business_function.id] = line.business_function.name
  }
  return businessFunction
}

/**
 * Owns the inline product-lines row editor (spec 0040 amendment rev.3
 * AC-106, generalized in spec 0057 for reuse outside the opportunity form):
 * "Add" appends an EMPTY row (mirrors `manager_slots`' "Add slot"), each row
 * is edited IN PLACE and INDEPENDENTLY of the others (spec 0077 rev.2, user
 * directive 2026-08-31: every row picks its own business function) — picking
 * one resets that row's category, still scoped by it (AC-104) — and a row is
 * removed outright.
 * Business-function labels come from two sources, merged: `knownLines`
 * (already hydrated, computed fresh every render — cheap, no fetch) and a
 * locally-fetched cache for whatever the user picks in a row (a single
 * one-shot lookup by id, run as a direct consequence of the user's
 * `onChange`, never a render-time effect). CATEGORY labels need neither: that
 * picker reads the whole category tree (user directive 2026-08-03) and
 * already holds every name it can show.
 */
export function useProductLinesField({
  value,
  onChange,
  knownLines,
  enforceManagementModeCap = true,
}: UseProductLinesFieldArgs) {
  const queryClient = useQueryClient()
  const [fetchedBusinessFunctionLabels, setFetchedBusinessFunctionLabels] = useState<LabelMap>({})
  // Spec 0077: the card's policy is resolved against the SAME cached category
  // tree the row pickers render — no extra request — so it is known for the
  // rows loaded on edit too, not only for those picked in this session.
  const categoryTree = useProductCategoryTree().data ?? EMPTY_TREE

  const knownBusinessFunctionLabels = indexKnownLabels(knownLines)
  const managementMode: CategoryManagementMode | null = resolveRowSetManagementMode(value, categoryTree)
  // AC-041: a single-mode card has exactly one row (INV-3); the "Add" action
  // stops being available the moment that mode resolves. The cap is an
  // invariant of a COMMERCIAL card (one deal, one single-mode line), not of a
  // row set as such: a person's competence (spec 0111 D-5) legitimately covers
  // several single-mode categories, so it opts out. Only the cap is dropped —
  // `managementMode` is still resolved, other consumers read it.
  const canAddRow = !enforceManagementModeCap || managementMode !== 'single'

  const businessFunctionLabel = (id: number | null): string | undefined =>
    id === null ? undefined : (knownBusinessFunctionLabels[id] ?? fetchedBusinessFunctionLabels[id])

  /** Resolves an id's label; returns it directly so a caller can use it immediately, before the next render. */
  const resolveLabel = async (
    resource: string,
    id: number,
    setLabels: (updater: (previous: LabelMap) => LabelMap) => void,
  ): Promise<string | undefined> => {
    const page = await queryClient.fetchQuery({
      queryKey: ['product-lines', 'label', resource, id],
      queryFn: () => fetchForSelect(resource, { ids: [id] }),
    })
    const label = page.items.find((item) => item.id === id)?.label
    if (label !== undefined) {
      setLabels((previous) => ({ ...previous, [id]: label }))
    }
    return label
  }

  const addRow = () => {
    // Defense in depth: the caller already hides/disables "Add" once
    // `canAddRow` is false (AC-041), this guards a direct call too.
    if (!canAddRow) {
      return
    }
    // AC-042 rev.2: an EMPTY row. It used to be prefilled with the first
    // row's function and locked (INV-2); rows are independent now, so the
    // operator picks the function of each one.
    onChange([...value, emptyProductLineRow()])
  }

  const removeRow = (index: number) => {
    onChange(value.filter((_, rowIndex) => rowIndex !== index))
  }

  const setRowBusinessFunction = (index: number, businessFunctionId: number | null) => {
    // Only the edited row changes: its category is reset (it was scoped by
    // the previous function), every other row is left alone — the INV-2
    // cascade onto the whole set is gone with the invariant (rev.2).
    const next = value.map((row, rowIndex) =>
      rowIndex === index ? { business_function_id: businessFunctionId, product_category_id: null } : row,
    )
    onChange(next)
    if (businessFunctionId !== null && businessFunctionLabel(businessFunctionId) === undefined) {
      void resolveLabel(BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE, businessFunctionId, setFetchedBusinessFunctionLabels)
    }
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
    setRowBusinessFunction,
    setRowProductCategory,
    businessFunctionLabel,
    /** `false` once the resolved mode is `single` (AC-041); consumed to hide/disable "Add". */
    canAddRow,
    /** The resolved mode for this row set, `null` while indeterminate (spec 0077, point 4). */
    managementMode,
  }
}
