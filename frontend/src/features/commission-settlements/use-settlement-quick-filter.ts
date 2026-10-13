import { useCallback, useMemo, useState, type RefObject } from 'react'
import type { TableViewHandle } from '@/features/table/table-view'
import type { SettlementQuickFilter, SettlementStatus } from '@/features/commission-settlements/types'

export const SETTLEMENT_STATUS_FILTER_KEY = 'status'

type FilterModel = Record<string, unknown>

const STATUS_BY_FILTER: Record<Exclude<SettlementQuickFilter, 'all'>, SettlementStatus> = {
  draft: 'DRAFT',
  issued: 'ISSUED',
  cancelled: 'CANCELLED',
}

/** Filter-model patch of one quick filter; `null` removes a key (see `TableViewHandle.setFilterModel`). */
export function buildSettlementQuickFilterPatch(filter: SettlementQuickFilter): FilterModel {
  if (filter === 'all') {
    return { [SETTLEMENT_STATUS_FILTER_KEY]: null }
  }
  return { [SETTLEMENT_STATUS_FILTER_KEY]: { filterType: 'set', values: [STATUS_BY_FILTER[filter]] } }
}

/** The quick filter a live grid filter model corresponds to, or `null` when the user customized it. */
export function readSettlementQuickFilter(model: FilterModel): SettlementQuickFilter | null {
  const entry = model[SETTLEMENT_STATUS_FILTER_KEY] as { values?: unknown } | undefined
  const values = Array.isArray(entry?.values) ? entry.values.map(String) : []
  if (values.length === 0) {
    return 'all'
  }
  if (values.length !== 1) {
    return null
  }
  const match = (Object.entries(STATUS_BY_FILTER) as [SettlementQuickFilter, SettlementStatus][]).find(
    ([, status]) => status === values[0],
  )
  return match ? match[0] : null
}

export interface SettlementQuickFilterState {
  quickFilter: SettlementQuickFilter | null
  /** Default (Bozze), merged over the saved grid filters at mount so grid and toolbar agree. */
  forcedFilterModel: FilterModel
  /** Mirror of the grid's live filter model (pass to `TableView.onFilterModelChange`). */
  onFilterModelChange: (model: FilterModel) => void
  setQuickFilter: (filter: SettlementQuickFilter) => void
}

/** Bozze / Emessi / Annullati / Tutti state: the grid filter model is the single source of truth. */
export function useSettlementQuickFilter(tableRef: RefObject<TableViewHandle | null>): SettlementQuickFilterState {
  const forcedFilterModel = useMemo<FilterModel>(() => buildSettlementQuickFilterPatch('draft'), [])
  const [model, setModel] = useState<FilterModel>(forcedFilterModel)
  const quickFilter = useMemo(() => readSettlementQuickFilter(model), [model])

  const setQuickFilter = useCallback(
    (filter: SettlementQuickFilter) => tableRef.current?.setFilterModel(buildSettlementQuickFilterPatch(filter)),
    [tableRef],
  )

  return { quickFilter, forcedFilterModel, onFilterModelChange: setModel, setQuickFilter }
}
