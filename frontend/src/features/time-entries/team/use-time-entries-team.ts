/**
 * Server state of the team view (`GET /api/time-entries/stats/team`, D-10).
 * `enabled` is driven by the caller (`meta.team.can_view` of the list query,
 * AC-037): this hook never decides on its own whether the request is allowed.
 */

import { useQuery, type UseQueryResult } from '@tanstack/react-query'
import { fetchTimeEntriesTeam } from '@/features/time-entries/api'
import { timeEntryKeys } from '@/features/time-entries/query-keys'
import type { TeamPulse, TeamStatsParams } from '@/features/time-entries/types'

export function useTimeEntriesTeam(
  params: TeamStatsParams,
  enabled: boolean,
): UseQueryResult<TeamPulse> {
  return useQuery({
    queryKey: timeEntryKeys.team(params),
    queryFn: () => fetchTimeEntriesTeam(params),
    enabled,
  })
}
