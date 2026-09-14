/**
 * Client-side filter state of the team view (spec 0122 D-10): free-text
 * search (debounced) plus the Ruolo/Funzione aziendale/Sede operativa/
 * Attivita'/Copertura filters. Pure state — `time-entries-team-pulse.tsx`
 * applies it to the fetched members via `team-tree.ts`.
 */

import { useCallback, useState } from 'react'
import { useDebouncedValue } from '@/hooks/use-debounced-value'
import {
  createDefaultTeamFilters,
  type TeamActivityFilter,
  type TeamFiltersValue,
} from '@/features/time-entries/team/team-tree'
import { TIME_ENTRY_FILTER_SEARCH_DEBOUNCE_MS } from '@/features/time-entries/time-entry-constants'

export interface UseTeamFiltersResult {
  searchInput: string
  setSearchInput: (value: string) => void
  debouncedSearch: string
  filters: TeamFiltersValue
  setRoles: (values: string[]) => void
  setBusinessFunctions: (values: string[]) => void
  setOperationalSites: (values: string[]) => void
  setActivity: (value: TeamActivityFilter) => void
  setCoverageRange: (value: [number, number]) => void
  resetFilters: () => void
}

export function useTeamFilters(): UseTeamFiltersResult {
  const [searchInput, setSearchInput] = useState('')
  const [filters, setFilters] = useState<TeamFiltersValue>(createDefaultTeamFilters)
  const debouncedSearch = useDebouncedValue(searchInput, TIME_ENTRY_FILTER_SEARCH_DEBOUNCE_MS)

  const resetFilters = useCallback(() => setFilters(createDefaultTeamFilters()), [])

  return {
    searchInput,
    setSearchInput,
    debouncedSearch,
    filters,
    setRoles: useCallback((values: string[]) => setFilters((current) => ({ ...current, roles: values })), []),
    setBusinessFunctions: useCallback(
      (values: string[]) => setFilters((current) => ({ ...current, businessFunctions: values })),
      [],
    ),
    setOperationalSites: useCallback(
      (values: string[]) => setFilters((current) => ({ ...current, operationalSites: values })),
      [],
    ),
    setActivity: useCallback(
      (value: TeamActivityFilter) => setFilters((current) => ({ ...current, activity: value })),
      [],
    ),
    setCoverageRange: useCallback(
      (value: [number, number]) => setFilters((current) => ({ ...current, coverageRange: value })),
      [],
    ),
    resetFilters,
  }
}
