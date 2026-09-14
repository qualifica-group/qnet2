/**
 * View-mode / selected-member orchestration for the `/time-entries` dashboard
 * (spec 0122 D-10, AC-037): personal vs team perspective, the member drilled
 * into from the team tree, and the resulting `user_id` override fed to the
 * days/stats queries. Kept out of `time-entries-dashboard.tsx` so the JSX
 * there stays presentation-only (engineering.md §2 — logic in the hook).
 *
 * `viewMode`/`selectedMember` are intentionally session-only (no
 * `localStorage`): only the drawer filters + sort persist (D-13), the
 * perspective always starts on "Vista personale" on a fresh load.
 */

import { useCallback, useMemo, useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { useAuth } from '@/features/auth/use-auth'
import {
  useTimeEntriesFilters,
  type UseTimeEntriesFiltersResult,
} from '@/features/time-entries/use-time-entries-filters'
import {
  useTimeEntriesDays,
  type UseTimeEntriesDaysResult,
} from '@/features/time-entries/days/use-time-entries-days'
import {
  buildTimeEntriesFilterParams,
  TIME_ENTRY_SORT_OPTIONS,
  type TimeEntriesFiltersState,
} from '@/features/time-entries/time-entries-filters'
import { timeEntryKeys } from '@/features/time-entries/query-keys'
import type {
  TeamPulseMember,
  TeamStatsParams,
  TimeEntriesFilterParams,
  TimeEntriesSortBy,
} from '@/features/time-entries/types'

type TimeEntriesViewMode = 'personal' | 'team'

export interface TimeEntriesCurrentUser {
  id: number
  name: string
  avatarUrl: string | null
}

export interface UseTimeEntriesDashboardViewResult {
  filters: UseTimeEntriesFiltersResult
  viewMode: TimeEntriesViewMode
  selectedMember: TeamPulseMember | null
  selectPersonalView: () => void
  selectTeamView: () => void
  selectMember: (member: TeamPulseMember) => void
  backToTeamList: () => void
  currentUser: TimeEntriesCurrentUser | null
  /** Selected user driving every query below: drawer's "Utente" filter, the drilled-into member, or self. */
  effectiveUserId: number | undefined
  queryFilterParams: TimeEntriesFilterParams
  sortBy: TimeEntriesSortBy
  days: UseTimeEntriesDaysResult
  /** True while the team tree (no member picked yet) should be mounted instead of the day list. */
  showTeamPulse: boolean
  /** True while the day-grouped dashboard (personal, or a drilled-into member) should be mounted. */
  showEntries: boolean
  teamPeriodParams: TeamStatsParams
  /** Re-fetches every time-entries query after the top alert's "Riprova". */
  retry: () => void
}

/** Falls back to the default sort column when the persisted value is stale/invalid. */
function resolveSortBy(value: string): TimeEntriesSortBy {
  const match = TIME_ENTRY_SORT_OPTIONS.find((option) => option.value === value)
  return match?.value ?? 'date'
}

export function useTimeEntriesDashboardView(): UseTimeEntriesDashboardViewResult {
  const { user } = useAuth()
  const queryClient = useQueryClient()
  const filters = useTimeEntriesFilters()
  const [viewMode, setViewMode] = useState<TimeEntriesViewMode>('personal')
  const [selectedMember, setSelectedMember] = useState<TeamPulseMember | null>(null)

  // Step 1: resolve the user the dashboard is currently scoped to.
  const baseFilterParams = useMemo(() => buildTimeEntriesFilterParams(filters.filters), [filters.filters])
  const effectiveUserId =
    viewMode === 'team' && selectedMember ? selectedMember.user.id : (baseFilterParams.user_id ?? user?.id)

  const showEntries = viewMode === 'personal' || (viewMode === 'team' && selectedMember !== null)
  const showTeamPulse = viewMode === 'team' && selectedMember === null

  // Step 2: rebuild the filters state with `user_id` pinned to the resolved user.
  const effectiveFiltersState = useMemo<TimeEntriesFiltersState>(
    () => ({
      ...filters.filters,
      values: { ...filters.filters.values, user_id: effectiveUserId ? String(effectiveUserId) : '' },
    }),
    [filters.filters, effectiveUserId],
  )

  // Step 3: wire the day list + KPI/pulse params off that same resolved state.
  const days = useTimeEntriesDays({ filters: effectiveFiltersState, enabled: showEntries })
  const queryFilterParams = useMemo(
    () => buildTimeEntriesFilterParams(effectiveFiltersState),
    [effectiveFiltersState],
  )
  const teamPeriodParams = useMemo<TeamStatsParams>(
    () => ({
      period_preset: baseFilterParams.period_preset,
      date_from: baseFilterParams.date_from,
      date_to: baseFilterParams.date_to,
    }),
    [baseFilterParams.date_from, baseFilterParams.date_to, baseFilterParams.period_preset],
  )

  const selectPersonalView = useCallback(() => {
    setViewMode('personal')
    setSelectedMember(null)
  }, [])
  const selectTeamView = useCallback(() => setViewMode('team'), [])
  const selectMember = useCallback((member: TeamPulseMember) => setSelectedMember(member), [])
  const backToTeamList = useCallback(() => setSelectedMember(null), [])
  const retry = useCallback(() => {
    void queryClient.invalidateQueries({ queryKey: timeEntryKeys.all })
  }, [queryClient])

  return {
    filters,
    viewMode,
    selectedMember,
    selectPersonalView,
    selectTeamView,
    selectMember,
    backToTeamList,
    currentUser: user ? { id: user.id, name: user.name, avatarUrl: user.avatar_url } : null,
    effectiveUserId,
    queryFilterParams,
    sortBy: resolveSortBy(filters.filters.sortBy),
    days,
    showTeamPulse,
    showEntries,
    teamPeriodParams,
    retry,
  }
}
