import { useCallback, useMemo, useState, type RefObject } from 'react'
import type { TableViewHandle } from '@/features/table/table-view'
import type { InstallmentQuickFilter } from '@/features/invoice-installments/types'

export const STATUS_FILTER_KEY = 'status'
export const OVERDUE_FILTER_KEY = 'overdue'
const UNPAID = ['unpaid'] as const
const PAID = ['paid'] as const
const OVERDUE_YES = 'yes'
const OVERDUE_NO = 'no'

type FilterModel = Record<string, unknown>

function setFilter(values: readonly string[]): { filterType: 'set'; values: string[] } {
  return { filterType: 'set', values: [...values] }
}

function readValues(model: FilterModel, key: string): string[] {
  const entry = model[key] as { values?: unknown } | undefined
  return Array.isArray(entry?.values) ? entry.values.map(String) : []
}

/** Filter-model patch of one quick filter; `null` removes a key (see `TableViewHandle.setFilterModel`). */
export function buildQuickFilterPatch(filter: InstallmentQuickFilter): FilterModel {
  switch (filter) {
    case 'unpaid':
      return { [STATUS_FILTER_KEY]: setFilter(UNPAID), [OVERDUE_FILTER_KEY]: null }
    case 'due':
      return { [STATUS_FILTER_KEY]: setFilter(UNPAID), [OVERDUE_FILTER_KEY]: setFilter([OVERDUE_NO]) }
    case 'overdue':
      return { [STATUS_FILTER_KEY]: setFilter(UNPAID), [OVERDUE_FILTER_KEY]: setFilter([OVERDUE_YES]) }
    case 'paid':
      return { [STATUS_FILTER_KEY]: setFilter(PAID), [OVERDUE_FILTER_KEY]: null }
    case 'all':
      return { [STATUS_FILTER_KEY]: null, [OVERDUE_FILTER_KEY]: null }
  }
}

/** The quick filter a live grid filter model corresponds to, or `null` when the user customized it. */
export function readQuickFilter(model: FilterModel): InstallmentQuickFilter | null {
  const statuses = readValues(model, STATUS_FILTER_KEY)
  const overdue = readValues(model, OVERDUE_FILTER_KEY)
  const isUnpaid = statuses.length === 1 && statuses[0] === UNPAID[0]

  if (isUnpaid && overdue.length === 0) {
    return 'unpaid'
  }
  if (isUnpaid && overdue.length === 1) {
    return overdue[0] === OVERDUE_NO ? 'due' : overdue[0] === OVERDUE_YES ? 'overdue' : null
  }
  if (statuses.length === 1 && statuses[0] === PAID[0] && overdue.length === 0) {
    return 'paid'
  }
  return overdue.length === 0 && statuses.length === 0 ? 'all' : null
}

export interface InstallmentQuickFilterState {
  quickFilter: InstallmentQuickFilter | null
  /** Default (Da incassare), merged over the saved grid filters at mount so grid and toolbar agree. */
  forcedFilterModel: FilterModel
  /** Mirror of the grid's live filter model (pass to `TableView.onFilterModelChange`). */
  onFilterModelChange: (model: FilterModel) => void
  setQuickFilter: (filter: InstallmentQuickFilter) => void
}

/**
 * Da incassare / In scadenza / Scadute / Incassate / Tutte state. The grid filter model is the single source of
 * truth (saved filters and "clear filters" chips keep the toolbar honest): the
 * toolbar reads from it and writes through the table handle.
 */
export function useInstallmentQuickFilter(
  tableRef: RefObject<TableViewHandle | null>,
): InstallmentQuickFilterState {
  const forcedFilterModel = useMemo<FilterModel>(
    () => ({ [STATUS_FILTER_KEY]: setFilter(UNPAID) }),
    [],
  )
  const [model, setModel] = useState<FilterModel>(forcedFilterModel)
  const quickFilter = useMemo(() => readQuickFilter(model), [model])

  const setQuickFilter = useCallback(
    (filter: InstallmentQuickFilter) => tableRef.current?.setFilterModel(buildQuickFilterPatch(filter)),
    [tableRef],
  )

  return { quickFilter, forcedFilterModel, onFilterModelChange: setModel, setQuickFilter }
}
