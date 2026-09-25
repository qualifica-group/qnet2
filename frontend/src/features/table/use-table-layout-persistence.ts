import { useCallback, useEffect, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import type { GridApi } from 'ag-grid-community'
import { saveTableFilters, saveTablePreferences } from '@/features/table/api'
import type { ColumnPreferenceInput } from '@/features/table/types'
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

function clearTimer(timerRef: { current: ReturnType<typeof setTimeout> | null }): void {
  if (timerRef.current) {
    clearTimeout(timerRef.current)
    timerRef.current = null
  }
}

function ignoreUnloadFailure(): void {}

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

  // The grid's live column filterModel (spec 0158 D-5): tracked as state, not
  // just read lazily off the grid API, so the active-filter chip row re-renders
  // whenever a column filter changes. Reset when `initialFilterModel` changes
  // identity (a fresh config load) via the "adjust state during render"
  // pattern (react.dev), not an effect — an effect's `setState` would cause an
  // extra cascading render (react-hooks/set-state-in-effect).
  const [filterModel, setFilterModel] = useState<Record<string, unknown>>(initialFilterModel)
  const [seenInitialFilterModel, setSeenInitialFilterModel] = useState(initialFilterModel)
  if (initialFilterModel !== seenInitialFilterModel) {
    setSeenInitialFilterModel(initialFilterModel)
    setFilterModel(initialFilterModel)
  }

  // Payloads waiting out their debounce. They are captured when the change
  // happens, not when the timer fires: a save flushed on unmount runs after AG
  // Grid has already been destroyed, so the grid can no longer be read then.
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null)
  const pendingLayoutRef = useRef<ColumnPreferenceInput[] | null>(null)
  const filterDebounceRef = useRef<ReturnType<typeof setTimeout> | null>(null)
  const pendingFilterRef = useRef<Record<string, unknown> | null>(null)

  const { mutate: mutateLayout } = savePreferences
  const flushLayout = useCallback(() => {
    clearTimer(debounceRef)
    const columns = pendingLayoutRef.current
    pendingLayoutRef.current = null
    if (columns) {
      mutateLayout(columns)
    }
  }, [mutateLayout])

  const { mutate: mutateFilters } = saveFilters
  const flushFilters = useCallback(() => {
    clearTimer(filterDebounceRef)
    const filterModel = pendingFilterRef.current
    pendingFilterRef.current = null
    if (filterModel) {
      mutateFilters({ filterModel })
    }
  }, [mutateFilters])

  // Persist the user's column layout, debounced so a drag/resize burst yields a
  // single save. The full current state is read from the grid and sent to the
  // backend, which computes the sparse delta (the frontend never diffs).
  const handleColumnStateChanged = useCallback(() => {
    if (!gridApi) {
      return
    }
    // The user just changed columns → offer reset immediately, before the
    // debounced save round-trips.
    setCustomizedLocally(true)
    pendingLayoutRef.current = toColumnPreferences(gridApi.getColumnState(), knownColumnIds)
    clearTimer(debounceRef)
    debounceRef.current = setTimeout(flushLayout, PERSIST_DEBOUNCE_MS)
  }, [gridApi, knownColumnIds, flushLayout])

  // Debounce filter persistence, and hold the last-persisted model (serialized)
  // so the grid's own echo of the saved filters on mount is not re-saved.
  const lastPersistedFilterRef = useRef<string>(JSON.stringify(EMPTY_FILTER_MODEL))
  useEffect(() => {
    lastPersistedFilterRef.current = JSON.stringify(initialFilterModel)
  }, [initialFilterModel])

  const handleFilterChanged = useCallback(() => {
    if (!gridApi) {
      return
    }
    const model = gridApi.getFilterModel()
    setFilterModel(model)
    const serialized = JSON.stringify(model)
    // Skip echoes and no-op refires: only a real change is persisted.
    if (serialized === lastPersistedFilterRef.current) {
      return
    }
    lastPersistedFilterRef.current = serialized
    setFiltersCustomizedLocally(Object.keys(model).length > 0)
    pendingFilterRef.current = model
    clearTimer(filterDebounceRef)
    filterDebounceRef.current = setTimeout(flushFilters, PERSIST_DEBOUNCE_MS)
  }, [gridApi, flushFilters])

  // Send a pending save immediately on unmount (in-app navigation) instead of
  // dropping it with its timer: the mutation outlives the component and still
  // refreshes the cached config the next mount reads.
  useEffect(
    () => () => {
      flushLayout()
      flushFilters()
    },
    [flushLayout, flushFilters],
  )

  // A reload or tab close never unmounts React, and the browser cancels an
  // ordinary request on unload: a change made within the debounce window was
  // lost. `pagehide` sends what is pending as `keepalive` requests, which the
  // browser completes after the page is gone. Their outcome is ignored because
  // no page is left to report it on.
  const productCategoryId = scope?.productCategoryId
  useEffect(() => {
    const handlePageHide = () => {
      clearTimer(debounceRef)
      clearTimer(filterDebounceRef)
      const columns = pendingLayoutRef.current
      const filterModel = pendingFilterRef.current
      pendingLayoutRef.current = null
      pendingFilterRef.current = null
      if (columns) {
        saveTablePreferences(domain, columns, productCategoryId, { keepalive: true }).catch(ignoreUnloadFailure)
      }
      if (filterModel) {
        saveTableFilters(domain, { filterModel }, productCategoryId, { keepalive: true }).catch(ignoreUnloadFailure)
      }
    }
    window.addEventListener('pagehide', handlePageHide)
    return () => window.removeEventListener('pagehide', handlePageHide)
  }, [domain, productCategoryId])

  const handleResetLayout = useCallback(async () => {
    try {
      // Drop any pending save so it can't re-persist the layout we are resetting.
      clearTimer(debounceRef)
      pendingLayoutRef.current = null
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
      clearTimer(filterDebounceRef)
      pendingFilterRef.current = null
      await resetFilters.mutateAsync()
      // Refetch BEFORE remounting so the grid mounts with an empty filterModel,
      // then bump the key to rebuild it cleanly (SSRM re-queries unfiltered).
      await refetchConfig()
      lastPersistedFilterRef.current = JSON.stringify(EMPTY_FILTER_MODEL)
      setFilterModel(EMPTY_FILTER_MODEL)
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
    /** The grid's live column filterModel (spec 0158 D-5), for the active-filter chip row. */
    filterModel,
    handleColumnStateChanged,
    handleFilterChanged,
    handleResetLayout,
    handleResetFilters,
    resettingLayout: resetPreferences.isPending,
    resettingFilters: resetFilters.isPending,
  }
}
