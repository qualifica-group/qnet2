import { useCallback, useEffect, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import type { GridApi } from 'ag-grid-community'
import {
  toColumnPreferences,
  useResetTablePreferences,
  useSaveTablePreferences,
} from '@/features/table/use-table-preferences'
import { useResetTableFilters, useSaveTableFilters } from '@/features/table/use-table-filters'
import type { TableConfigScope } from '@/features/table/use-table-config'

/** Debounce window for persisting layout changes after the user stops editing. */
const PERSIST_DEBOUNCE_MS = 500

/** Stable empty filter model (module-level so its identity never changes). */
export const EMPTY_FILTER_MODEL: Record<string, unknown> = {}

interface UseTableLayoutPersistenceInput {
  domain: string
  /** The scope the config query is keyed by (spec 0064), so a save refreshes the entry the grid reads. */
  scope?: TableConfigScope
  gridApi: GridApi | null
  /** The domain's real column ids (server allow-list), so synthetic grid columns are never persisted. */
  knownColumnIds: Set<string>
  /** The saved filterModel replayed into the grid on mount, so the initial echo is never re-persisted. */
  initialFilterModel: Record<string, unknown>
  /** `config.customized` from the backend (a saved layout exists). */
  configCustomized: boolean
  /** `config.filtersCustomized` from the backend (saved filters exist). */
  configFiltersCustomized: boolean
  /** Refetches the table config; awaited before a reset remounts the grid on the fresh defaults. */
  refetchConfig: () => Promise<unknown>
}

/**
 * Owns the debounced persistence of the user's column layout and filter state
 * (spec 0003/0009) and the "reset to default" flow for both. Split out of
 * `TableView` to keep that orchestrator within the engineering size limits
 * (`.claude/rules/engineering.md` §6) — pure extraction, no behavior change.
 *
 * `layoutVersion` is bumped after a reset to force a clean `<DataTable>`
 * remount on the refetched default config, which is how the caller drops AG
 * Grid's in-memory column/filter state deterministically.
 */
export function useTableLayoutPersistence({
  domain,
  scope,
  gridApi,
  knownColumnIds,
  initialFilterModel,
  configCustomized,
  configFiltersCustomized,
  refetchConfig,
}: UseTableLayoutPersistenceInput) {
  const { t } = useTranslation()

  const savePreferences = useSaveTablePreferences(domain, scope)
  const resetPreferences = useResetTablePreferences(domain)
  const saveFilters = useSaveTableFilters(domain, scope)
  const resetFilters = useResetTableFilters(domain)

  const [layoutVersion, setLayoutVersion] = useState(0)

  // Reflects whether the user has changed columns THIS session, for immediate
  // feedback; combined with the persisted `config.customized` (true after a
  // reload when a saved layout exists). The "Reset layout" action shows only
  // when the layout is customized.
  const [customizedLocally, setCustomizedLocally] = useState(false)
  const isCustomized = customizedLocally || configCustomized

  // Same immediate-feedback pattern for the saved filter state: a "Reset
  // filters" action shows whenever filters are active (this session or persisted).
  const [filtersCustomizedLocally, setFiltersCustomizedLocally] = useState(false)
  const isFilterCustomized = filtersCustomizedLocally || configFiltersCustomized

  // Persist the user's column layout, debounced so a drag/resize burst yields a
  // single save. The full current state is read from the grid and sent to the
  // backend, which computes the sparse delta (the frontend never diffs).
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null)
  const handleColumnStateChanged = useCallback(() => {
    if (!gridApi) {
      return
    }
    if (debounceRef.current) {
      clearTimeout(debounceRef.current)
    }
    // The user just changed columns → offer reset immediately, before the
    // debounced save round-trips.
    setCustomizedLocally(true)
    debounceRef.current = setTimeout(() => {
      const preferences = toColumnPreferences(gridApi.getColumnState(), knownColumnIds)
      savePreferences.mutate(preferences)
    }, PERSIST_DEBOUNCE_MS)
  }, [gridApi, knownColumnIds, savePreferences])

  // Debounce filter persistence, and hold the last-persisted model (serialized)
  // so the grid's own echo of the saved filters on mount is not re-saved.
  const filterDebounceRef = useRef<ReturnType<typeof setTimeout> | null>(null)
  const lastPersistedFilterRef = useRef<string>(JSON.stringify(EMPTY_FILTER_MODEL))
  useEffect(() => {
    lastPersistedFilterRef.current = JSON.stringify(initialFilterModel)
  }, [initialFilterModel])

  const handleFilterChanged = useCallback(() => {
    if (!gridApi) {
      return
    }
    const model = gridApi.getFilterModel()
    const serialized = JSON.stringify(model)
    // Skip echoes and no-op refires: only a real change is persisted.
    if (serialized === lastPersistedFilterRef.current) {
      return
    }
    lastPersistedFilterRef.current = serialized
    setFiltersCustomizedLocally(Object.keys(model).length > 0)
    if (filterDebounceRef.current) {
      clearTimeout(filterDebounceRef.current)
    }
    filterDebounceRef.current = setTimeout(() => {
      saveFilters.mutate({ filterModel: model })
    }, PERSIST_DEBOUNCE_MS)
  }, [gridApi, saveFilters])

  // Flush any pending debounce on unmount so the last change is not lost.
  useEffect(
    () => () => {
      if (debounceRef.current) {
        clearTimeout(debounceRef.current)
      }
      if (filterDebounceRef.current) {
        clearTimeout(filterDebounceRef.current)
      }
    },
    [],
  )

  const handleResetLayout = useCallback(async () => {
    try {
      await resetPreferences.mutateAsync()
      // Refetch defaults BEFORE remounting so the new grid mounts on the pure
      // PHP default layout, then bump the key to rebuild it cleanly.
      await refetchConfig()
      setCustomizedLocally(false)
      setLayoutVersion((version) => version + 1)
      toast.success(t('table.layoutReset'))
    } catch {
      toast.error(t('table.layoutError'))
    }
  }, [resetPreferences, refetchConfig, t])

  const handleResetFilters = useCallback(async () => {
    try {
      // Drop any pending save so it can't re-persist the filters we are clearing.
      if (filterDebounceRef.current) {
        clearTimeout(filterDebounceRef.current)
      }
      await resetFilters.mutateAsync()
      // Refetch BEFORE remounting so the grid mounts with an empty filterModel,
      // then bump the key to rebuild it cleanly (SSRM re-queries unfiltered).
      await refetchConfig()
      lastPersistedFilterRef.current = JSON.stringify(EMPTY_FILTER_MODEL)
      setFiltersCustomizedLocally(false)
      setLayoutVersion((version) => version + 1)
      toast.success(t('table.filtersReset'))
    } catch {
      toast.error(t('table.filtersError'))
    }
  }, [resetFilters, refetchConfig, t])

  return {
    layoutVersion,
    isCustomized,
    isFilterCustomized,
    setFiltersCustomizedLocally,
    handleColumnStateChanged,
    handleFilterChanged,
    handleResetLayout,
    handleResetFilters,
    resettingLayout: resetPreferences.isPending,
    resettingFilters: resetFilters.isPending,
  }
}
