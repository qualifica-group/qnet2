import { useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { fetchForSelect } from '@/features/for-select/api'
import { BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE } from '@/features/business-functions/for-select-api'
import type { CategoryManagementMode } from '@/features/product-categories/types'
import {
  resolveRowSetManagementMode,
  type CategoryManagementMeta,
  type CategoryMetaById,
} from '@/features/product-lines/management-mode'
import type { ProductLine, ProductLineRow } from '@/features/product-lines/types'

type LabelMap = Record<number, string>

interface UseProductLinesFieldArgs {
  /** The `product_lines` field's current value (RHF or plain state, mirrors `ManagerSlotsField`). */
  value: ProductLineRow[]
  onChange: (next: ProductLineRow[]) => void
  /** Rows whose labels are already known without a fetch: edit load, from-lead prefill, in-form pickers. */
  knownLines: ProductLine[]
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
 * is edited IN PLACE — picking a business function resets that row's
 * category (still scoped by it, AC-104) — and a row is removed outright.
 * Business-function labels come from two sources, merged: `knownLines`
 * (already hydrated, computed fresh every render — cheap, no fetch) and a
 * locally-fetched cache for whatever the user picks in a row (a single
 * one-shot lookup by id, run as a direct consequence of the user's
 * `onChange`, never a render-time effect). CATEGORY labels need neither: that
 * picker reads the whole category tree (user directive 2026-08-03) and
 * already holds every name it can show.
 */
export function useProductLinesField({ value, onChange, knownLines }: UseProductLinesFieldArgs) {
  const queryClient = useQueryClient()
  const [fetchedBusinessFunctionLabels, setFetchedBusinessFunctionLabels] = useState<LabelMap>({})
  // Meta of whichever picked categories are known this session (spec 0077):
  // keyed by category id, handed over by the row's picker on pick
  // (resolved from the category tree it renders), never fetched separately.
  const [categoryMetaById, setCategoryMetaById] = useState<CategoryMetaById>({})

  const knownBusinessFunctionLabels = indexKnownLabels(knownLines)
  const resolvedManagementMode = resolveRowSetManagementMode(value, categoryMetaById)
  const managementMode: CategoryManagementMode | null = resolvedManagementMode?.managementMode ?? null
  const managementModeRootCategoryId: number | null = resolvedManagementMode?.rootCategoryId ?? null
  // INV-2, gated to the resolved-multiple case only (point 4: unknown mode
  // stays unconstrained, current behaviour, D-5-friendly for legacy rows).
  const lockedBusinessFunctionId: number | null =
    managementMode === 'multiple' ? (value[0]?.business_function_id ?? null) : null
  // AC-041: a single-mode card has exactly one row (INV-3); the "Add" action
  // stops being available the moment that mode resolves.
  const canAddRow = managementMode !== 'single'

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
    // AC-042: from the second row on, the function is bound to the first
    // row's (INV-2) — prefilled here rather than left for the operator to
    // repick it.
    onChange([...value, { business_function_id: lockedBusinessFunctionId, product_category_id: null }])
  }

  const removeRow = (index: number) => {
    onChange(value.filter((_, rowIndex) => rowIndex !== index))
  }

  const setRowBusinessFunction = (index: number, businessFunctionId: number | null) => {
    // INV-2 cascade: once multiple rows share a resolved `multiple` mode, the
    // first row's function change is mirrored onto every other row (whose
    // control is itself locked/disabled — see `ProductLinesField`), each
    // losing its category (still scoped by the now-different function).
    const cascades = index === 0 && managementMode === 'multiple' && value.length > 1
    const next = value.map((row, rowIndex) => {
      if (rowIndex === index || cascades) {
        return { business_function_id: businessFunctionId, product_category_id: null }
      }
      return row
    })
    onChange(next)
    if (businessFunctionId !== null && businessFunctionLabel(businessFunctionId) === undefined) {
      void resolveLabel(BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE, businessFunctionId, setFetchedBusinessFunctionLabels)
    }
  }

  /**
   * Spec 0077: `meta` (branch root + management mode) now comes from the
   * category TREE the row's picker reads, resolved by the picker itself —
   * still no extra request, and still captured only for the categories picked
   * in this session (D-5 grandfathering unchanged).
   */
  const setRowProductCategory = (
    index: number,
    productCategoryId: number | null,
    meta: CategoryManagementMeta | null = null,
  ) => {
    const next = value.map((row, rowIndex) =>
      rowIndex === index ? { ...row, product_category_id: productCategoryId } : row,
    )
    onChange(next)
    if (productCategoryId !== null && meta !== null) {
      setCategoryMetaById((previous) => ({ ...previous, [productCategoryId]: meta }))
    }
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
    /** The resolved mode's root category id, `null` while indeterminate. Feeds `root_category_id` on rows after the first (INV-1, AC-042). */
    managementModeRootCategoryId,
    /** The shared business-function id every row after the first is bound to (INV-2), `null` outside the resolved-multiple case. */
    lockedBusinessFunctionId,
  }
}
