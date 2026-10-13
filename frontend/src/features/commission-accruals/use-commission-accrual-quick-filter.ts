import { useCallback, useMemo, useState, type RefObject } from 'react'
import type { TableViewHandle } from '@/features/table/table-view'
import type { AccrualQuickFilter } from '@/features/commission-accruals/types'

export const STATUS_FILTER_KEY = 'status'
const OPEN = 'open'
const SETTLED_EXTERNALLY = 'settled_externally'

type FilterModel = Record<string, unknown>

function setFilter(value: string): { filterType: 'set'; values: string[] } {
  return { filterType: 'set', values: [value] }
}

function readValues(model: FilterModel): string[] {
  const entry = model[STATUS_FILTER_KEY] as { values?: unknown } | undefined
  return Array.isArray(entry?.values) ? entry.values.map(String) : []
}

/** Filter-model patch of one quick filter; `null` removes a key (see `TableViewHandle.setFilterModel`). */
export function buildAccrualQuickFilterPatch(filter: AccrualQuickFilter): FilterModel {
  switch (filter) {
    case 'open':
      return { [STATUS_FILTER_KEY]: setFilter(OPEN) }
    case 'settled_externally':
      return { [STATUS_FILTER_KEY]: setFilter(SETTLED_EXTERNALLY) }
    case 'all':
      return { [STATUS_FILTER_KEY]: null }
  }
}

/** The quick filter a live grid filter model corresponds to, or `null` when the user customized it. */
export function readAccrualQuickFilter(model: FilterModel): AccrualQuickFilter | null {
  const statuses = readValues(model)
  if (statuses.length === 0) {
    return 'all'
  }
  if (statuses.length === 1 && statuses[0] === OPEN) {
    return 'open'
  }
  return statuses.length === 1 && statuses[0] === SETTLED_EXTERNALLY ? 'settled_externally' : null
}

export interface AccrualQuickFilterState {
  quickFilter: AccrualQuickFilter | null
  /** Default (Aperto), merged over the saved grid filters at mount so grid and toolbar agree. */
  forcedFilterModel: FilterModel
  /** Mirror of the grid's live filter model (pass to `TableView.onFilterModelChange`). */
  onFilterModelChange: (model: FilterModel) => void
  setQuickFilter: (filter: AccrualQuickFilter) => void
}

/**
 * Aperto / Liquidato esternamente / Tutti state. The grid filter model is the
 * single source of truth: the toolbar reads from it and writes through the
 * table handle.
 */
export function useAccrualQuickFilter(tableRef: RefObject<TableViewHandle | null>): AccrualQuickFilterState {
  const forcedFilterModel = useMemo<FilterModel>(() => buildAccrualQuickFilterPatch('open'), [])
  const [model, setModel] = useState<FilterModel>(forcedFilterModel)
  const quickFilter = useMemo(() => readAccrualQuickFilter(model), [model])

  const setQuickFilter = useCallback(
    (filter: AccrualQuickFilter) => tableRef.current?.setFilterModel(buildAccrualQuickFilterPatch(filter)),
    [tableRef],
  )

  return { quickFilter, forcedFilterModel, onFilterModelChange: setModel, setQuickFilter }
}
