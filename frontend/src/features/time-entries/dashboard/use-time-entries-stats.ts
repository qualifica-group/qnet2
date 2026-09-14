/**
 * Overview + pulse tiles data (spec 0122 D-11, AC-034). Two plain `useQuery`
 * calls keyed by `timeEntryKeys`, both gated by the same `enabled` — the
 * dashboard passes `false` while the driving filters/period are not resolved
 * yet (e.g. before `meta` from the list request is known).
 */

import { useQuery } from '@tanstack/react-query'
import { fetchTimeEntriesOverview, fetchTimeEntriesPulse } from '@/features/time-entries/api'
import { timeEntryKeys } from '@/features/time-entries/query-keys'
import type { OverviewStats, PulseStats, TimeEntriesFilterParams } from '@/features/time-entries/types'

export interface UseTimeEntriesStatsOptions {
  params: TimeEntriesFilterParams
  enabled?: boolean
}

export interface UseTimeEntriesStatsResult {
  overview: OverviewStats | undefined
  isOverviewLoading: boolean
  isOverviewError: boolean
  pulse: PulseStats | undefined
  isPulseLoading: boolean
  isPulseError: boolean
}

export function useTimeEntriesStats({
  params,
  enabled = true,
}: UseTimeEntriesStatsOptions): UseTimeEntriesStatsResult {
  const overviewQuery = useQuery({
    queryKey: timeEntryKeys.overview(params),
    queryFn: () => fetchTimeEntriesOverview(params),
    enabled,
  })

  const pulseQuery = useQuery({
    queryKey: timeEntryKeys.pulse(params),
    queryFn: () => fetchTimeEntriesPulse(params),
    enabled,
  })

  return {
    overview: overviewQuery.data,
    isOverviewLoading: enabled && overviewQuery.isPending,
    isOverviewError: overviewQuery.isError,
    pulse: pulseQuery.data,
    isPulseLoading: enabled && pulseQuery.isPending,
    isPulseError: pulseQuery.isError,
  }
}
