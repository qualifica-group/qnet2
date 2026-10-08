import { useCallback, useMemo, useState, type RefObject } from 'react'
import type { TableViewHandle } from '@/features/table/table-view'
import { REGISTRY_TYPE_TABS, type RegistryTypeTab } from '@/features/registries/types'

export const REGISTRY_TYPE_FILTER_KEY = 'registry_type'
/** The tabs that map to a single card type (every tab but "all"). */
const TYPE_VALUES = REGISTRY_TYPE_TABS.filter((tab) => tab !== 'all')

type FilterModel = Record<string, unknown>

/** Filter-model patch of one tab; `null` removes the key (see `TableViewHandle.setFilterModel`). */
export function buildRegistryTypePatch(tab: RegistryTypeTab): FilterModel {
  return {
    [REGISTRY_TYPE_FILTER_KEY]: tab === 'all' ? null : { filterType: 'set', values: [tab] },
  }
}

/** The tab a live grid filter model corresponds to, or `null` when the user customized the type filter. */
export function readRegistryTypeTab(model: FilterModel): RegistryTypeTab | null {
  const entry = model[REGISTRY_TYPE_FILTER_KEY] as { values?: unknown } | undefined
  const values = Array.isArray(entry?.values) ? entry.values : []

  if (values.length === 0) {
    return 'all'
  }
  const only: unknown = values.length === 1 ? values[0] : null
  return TYPE_VALUES.find((tab) => tab === only) ?? null
}

export interface RegistryTypeTabState {
  tab: RegistryTypeTab | null
  /** Mirror of the grid's live filter model (pass to `TableView.onFilterModelChange`). */
  onFilterModelChange: (model: FilterModel) => void
  setTab: (tab: RegistryTypeTab) => void
}

/**
 * Tutte / Persone fisiche / Aziende state. The grid filter model is the single
 * source of truth (saved filters and "clear filters" chips keep the tabs
 * honest): the tabs read from it and write through the table handle.
 */
export function useRegistryTypeTab(tableRef: RefObject<TableViewHandle | null>): RegistryTypeTabState {
  const [model, setModel] = useState<FilterModel>({})
  const tab = useMemo(() => readRegistryTypeTab(model), [model])

  const setTab = useCallback(
    (next: RegistryTypeTab) => tableRef.current?.setFilterModel(buildRegistryTypePatch(next)),
    [tableRef],
  )

  return { tab, onFilterModelChange: setModel, setTab }
}
