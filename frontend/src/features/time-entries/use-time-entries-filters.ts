/**
 * Filters + period navigation state of the `/time-entries` dashboard (spec
 * 0122 AC-032/AC-033), same semantics as q-net's `work-activities-list.tsx`:
 * a client-only "period mode" survives in `localStorage` (`PERIOD_MODE_KEY`)
 * so re-opening the page remembers the navigation granularity, while the
 * backend-facing `period_preset` filter is cleared the moment the user
 * navigates (prev/next/today send explicit `date_from`/`date_to` instead —
 * D-13's "Periodo: date_from/date_to se presenti, altrimenti period_preset").
 *
 * Hydration reads `localStorage` synchronously inside the `useState`
 * lazy initializer (not an effect): this is a client-only SPA, the same
 * pattern already used by `useRequestReportFilters`.
 */

import { useCallback, useState } from 'react'
import {
  createDefaultTimeEntriesFilters,
  parseStoredTimeEntriesFilters,
  removeTimeEntriesFilterValue,
  TIME_ENTRIES_FILTERS_STORAGE_KEY,
  type TimeEntriesFiltersState,
} from '@/features/time-entries/time-entries-filters'
import {
  getPeriodRange,
  isTimeEntriesPeriodUnit,
  parseDateString,
  shiftAnchor,
  toDateString,
  type TimeEntriesPeriodUnit,
} from '@/features/time-entries/time-entry-period'

/** Client-only filter key recording the active preset mode while navigating; excluded from the backend query. */
const PERIOD_MODE_KEY = 'period_mode'

function readStoredFilters(): TimeEntriesFiltersState {
  if (typeof window === 'undefined') {
    return createDefaultTimeEntriesFilters()
  }
  return parseStoredTimeEntriesFilters(window.localStorage.getItem(TIME_ENTRIES_FILTERS_STORAGE_KEY))
}

function persistFilters(filters: TimeEntriesFiltersState): void {
  if (typeof window === 'undefined') {
    return
  }
  try {
    window.localStorage.setItem(TIME_ENTRIES_FILTERS_STORAGE_KEY, JSON.stringify(filters))
  } catch {
    // Storage can be unavailable (private mode, quota): filters still apply this session.
  }
}

function firstValue(value: string | string[] | undefined): string {
  const raw = Array.isArray(value) ? value[0] : value
  return typeof raw === 'string' ? raw : ''
}

type FiltersUpdater = TimeEntriesFiltersState | ((current: TimeEntriesFiltersState) => TimeEntriesFiltersState)

export interface UseTimeEntriesFiltersResult {
  filters: TimeEntriesFiltersState
  setFilters: (updater: FiltersUpdater) => void
  resetFilters: () => void
  removeFilterChip: (key: string) => void
  setSort: (sortBy: string, sortDirection: 'asc' | 'desc') => void
  /** The navigation granularity currently driving prev/next/today, or `null` on a custom range. */
  navigationUnit: TimeEntriesPeriodUnit | null
  /** `Y-m-d` the current/previous/next period is computed from. */
  anchorDate: string
  selectPeriodPreset: (unit: TimeEntriesPeriodUnit) => void
  goToPreviousPeriod: () => void
  goToNextPeriod: () => void
  goToToday: () => void
  setCustomDateRange: (from: string, to: string) => void
}

/** Filters + period navigation state, persisted to `localStorage` (D-13). */
export function useTimeEntriesFilters(): UseTimeEntriesFiltersResult {
  const [filters, setFiltersState] = useState<TimeEntriesFiltersState>(readStoredFilters)
  const [navigationUnit, setNavigationUnit] = useState<TimeEntriesPeriodUnit | null>(() => {
    const stored = readStoredFilters()
    const mode = firstValue(stored.values[PERIOD_MODE_KEY]) || firstValue(stored.values.period_preset)
    return isTimeEntriesPeriodUnit(mode) ? mode : null
  })
  const [anchorDate, setAnchorDate] = useState<string>(() => {
    const explicitFrom = firstValue(readStoredFilters().values.date_from)
    return explicitFrom || toDateString(new Date())
  })

  const setFilters = useCallback((updater: FiltersUpdater) => {
    setFiltersState((current) => {
      const resolved = typeof updater === 'function' ? updater(current) : updater
      persistFilters(resolved)
      return resolved
    })
  }, [])

  const resetFilters = useCallback(() => {
    const defaults = createDefaultTimeEntriesFilters()
    const defaultUnit = firstValue(defaults.values.period_preset)
    setNavigationUnit(isTimeEntriesPeriodUnit(defaultUnit) ? defaultUnit : null)
    setAnchorDate(toDateString(new Date()))
    setFilters(defaults)
  }, [setFilters])

  const removeFilterChip = useCallback(
    (key: string) => {
      setFilters((current) => ({ ...current, values: removeTimeEntriesFilterValue(current.values, key) }))
    },
    [setFilters],
  )

  const setSort = useCallback(
    (sortBy: string, sortDirection: 'asc' | 'desc') => {
      setFilters((current) => ({ ...current, sortBy, sortDirection }))
    },
    [setFilters],
  )

  const applyNavigationRange = useCallback(
    (unit: TimeEntriesPeriodUnit, anchor: Date) => {
      const range = getPeriodRange(unit, anchor)
      setAnchorDate(range.from)
      setFilters((current) => ({
        ...current,
        values: {
          ...current.values,
          period_preset: [],
          [PERIOD_MODE_KEY]: [unit],
          date_from: range.from,
          date_to: range.to,
        },
      }))
    },
    [setFilters],
  )

  const selectPeriodPreset = useCallback(
    (unit: TimeEntriesPeriodUnit) => {
      setNavigationUnit(unit)
      setAnchorDate(toDateString(new Date()))
      setFilters((current) => ({
        ...current,
        values: {
          ...current.values,
          period_preset: [unit],
          [PERIOD_MODE_KEY]: [unit],
          date_from: '',
          date_to: '',
        },
      }))
    },
    [setFilters],
  )

  const goToPreviousPeriod = useCallback(() => {
    if (!navigationUnit) {
      return
    }
    const current = parseDateString(anchorDate) ?? new Date()
    applyNavigationRange(navigationUnit, shiftAnchor(navigationUnit, current, -1))
  }, [anchorDate, applyNavigationRange, navigationUnit])

  const goToNextPeriod = useCallback(() => {
    if (!navigationUnit) {
      return
    }
    const current = parseDateString(anchorDate) ?? new Date()
    applyNavigationRange(navigationUnit, shiftAnchor(navigationUnit, current, 1))
  }, [anchorDate, applyNavigationRange, navigationUnit])

  const goToToday = useCallback(() => {
    if (!navigationUnit) {
      return
    }
    setAnchorDate(toDateString(new Date()))
    setFilters((current) => ({
      ...current,
      values: {
        ...current.values,
        period_preset: [navigationUnit],
        [PERIOD_MODE_KEY]: [navigationUnit],
        date_from: '',
        date_to: '',
      },
    }))
  }, [navigationUnit, setFilters])

  const setCustomDateRange = useCallback(
    (from: string, to: string) => {
      setNavigationUnit(null)
      if (from) {
        setAnchorDate(from)
      }
      setFilters((current) => ({
        ...current,
        values: {
          ...current.values,
          period_preset: [],
          [PERIOD_MODE_KEY]: [],
          date_from: from,
          date_to: to,
        },
      }))
    },
    [setFilters],
  )

  return {
    filters,
    setFilters,
    resetFilters,
    removeFilterChip,
    setSort,
    navigationUnit,
    anchorDate,
    selectPeriodPreset,
    goToPreviousPeriod,
    goToNextPeriod,
    goToToday,
    setCustomDateRange,
  }
}
