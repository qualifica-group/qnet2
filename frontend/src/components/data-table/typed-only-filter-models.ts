import type { TableColumn } from '@/features/table/types'

/** `buildColumnFilter` puts the typed condition second in every Multi Filter. */
const MULTI_FILTER_TYPED_INDEX = 1

/**
 * Rewrites a saved filterModel (persisted state, saved view) for the columns
 * that now get the condition filter alone (`tooManyFilterValues`): a model
 * saved by their old checklist + condition filter keeps its condition and
 * drops the checklist part, which the condition filter cannot hold. Without
 * this the grid would discard the whole entry. Other entries pass unchanged.
 */
export function toTypedOnlyFilterModels(
  model: Record<string, unknown>,
  columns: readonly TableColumn[],
): Record<string, unknown> {
  const typedOnlyIds = new Set(columns.filter((column) => column.tooManyFilterValues === true).map((column) => column.id))
  if (typedOnlyIds.size === 0) {
    return model
  }

  const entries = Object.entries(model).flatMap(([columnId, entry]): Array<[string, unknown]> => {
    if (!typedOnlyIds.has(columnId) || !isMultiModel(entry)) {
      return [[columnId, entry]]
    }
    const typed = entry.filterModels[MULTI_FILTER_TYPED_INDEX]

    return typed ? [[columnId, typed]] : []
  })

  return Object.fromEntries(entries)
}

function isMultiModel(entry: unknown): entry is { filterType: 'multi'; filterModels: unknown[] } {
  return (
    typeof entry === 'object' &&
    entry !== null &&
    (entry as { filterType?: unknown }).filterType === 'multi' &&
    Array.isArray((entry as { filterModels?: unknown }).filterModels)
  )
}
