import { useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { fetchForSelect } from '@/features/for-select/api'
import { BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE } from '@/features/business-functions/for-select-api'
import { emptyCompetenceLineRow, type CompetenceLineRow, type KnownProductLine } from '@/features/product-lines/types'

type LabelMap = Record<number, string>

interface UseCompetenceLinesFieldArgs {
  /** The `employment.product_lines` field's current value (RHF or plain state, mirrors `ManagerSlotsField`). */
  value: CompetenceLineRow[]
  onChange: (next: CompetenceLineRow[]) => void
  /** Rows whose labels are already known without a fetch: edit load, in-form pickers. */
  knownLines: KnownProductLine[]
}

/** Builds the `{id: name}` lookup out of a set of already-labeled lines. The CATEGORY labels need no map: the row's picker reads them off the tree it already renders. */
function indexKnownLabels(lines: KnownProductLine[]): LabelMap {
  const businessFunction: LabelMap = {}
  for (const line of lines) {
    businessFunction[line.business_function.id] = line.business_function.name
  }
  return businessFunction
}

/**
 * Owns the COMPETENCE row editor (spec 0111 D-5, spec 0129 D-1..D-7, spec
 * 0132 D-4 — the contract stays funzione-aziendale + categoria, unaffected by
 * the card's move to root-category): "Add" appends an EMPTY row, each row is
 * edited IN PLACE and INDEPENDENTLY of the others, and NO `single`-mode row
 * cap applies — a person's competence legitimately covers several
 * single-mode categories (unlike `useProductLinesField`'s `canAddRow`, this
 * editor never disables "Add").
 * Business-function labels come from two sources, merged: `knownLines`
 * (already hydrated, computed fresh every render — cheap, no fetch) and a
 * locally-fetched cache for whatever the user picks in a row (a single
 * one-shot lookup by id, run as a direct consequence of the user's
 * `onChange`, never a render-time effect). CATEGORY labels need neither:
 * that picker reads the whole category tree (user directive 2026-08-03) and
 * already holds every name it can show.
 */
export function useCompetenceLinesField({ value, onChange, knownLines }: UseCompetenceLinesFieldArgs) {
  const queryClient = useQueryClient()
  const [fetchedBusinessFunctionLabels, setFetchedBusinessFunctionLabels] = useState<LabelMap>({})
  const knownBusinessFunctionLabels = indexKnownLabels(knownLines)

  const businessFunctionLabel = (id: number | null): string | undefined =>
    id === null ? undefined : (knownBusinessFunctionLabels[id] ?? fetchedBusinessFunctionLabels[id])

  /** Resolves an id's label; returns it directly so a caller can use it immediately, before the next render. */
  const resolveLabel = async (id: number): Promise<string | undefined> => {
    const page = await queryClient.fetchQuery({
      queryKey: ['product-lines', 'label', BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE, id],
      queryFn: () => fetchForSelect(BUSINESS_FUNCTIONS_FOR_SELECT_RESOURCE, { ids: [id] }),
    })
    const label = page.items.find((item) => item.id === id)?.label
    if (label !== undefined) {
      setFetchedBusinessFunctionLabels((previous) => ({ ...previous, [id]: label }))
    }
    return label
  }

  const addRow = () => {
    onChange([...value, emptyCompetenceLineRow()])
  }

  const removeRow = (index: number) => {
    onChange(value.filter((_, rowIndex) => rowIndex !== index))
  }

  const setRowBusinessFunction = (index: number, businessFunctionId: number | null) => {
    // Only the edited row changes: its category is reset (it was scoped by
    // the previous function), every other row is left alone. A stale "all
    // categories" pick (spec 0129) is dropped too: it was scoped by the
    // previous function.
    const next = value.map((row, rowIndex) =>
      rowIndex === index
        ? { business_function_id: businessFunctionId, product_category_id: null, all_categories: false }
        : row,
    )
    onChange(next)
    if (businessFunctionId !== null && businessFunctionLabel(businessFunctionId) === undefined) {
      void resolveLabel(businessFunctionId)
    }
  }

  const setRowProductCategory = (index: number, productCategoryId: number | null) => {
    const next = value.map((row, rowIndex) =>
      rowIndex === index ? { ...row, product_category_id: productCategoryId, all_categories: false } : row,
    )
    onChange(next)
  }

  /**
   * Spec 0129 D-5: the "all categories of the function" checkbox. Checking it
   * clears the category (mutually exclusive with a specific pick); unchecking
   * leaves the category unset, exactly like a freshly-added row.
   */
  const setRowAllCategories = (index: number, checked: boolean) => {
    const next = value.map((row, rowIndex) =>
      rowIndex === index ? { ...row, product_category_id: null, all_categories: checked } : row,
    )
    onChange(next)
  }

  return {
    addRow,
    removeRow,
    setRowBusinessFunction,
    setRowProductCategory,
    setRowAllCategories,
    businessFunctionLabel,
  }
}
