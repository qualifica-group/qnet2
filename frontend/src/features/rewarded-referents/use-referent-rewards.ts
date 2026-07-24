import { useQuery, type UseQueryResult } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { fetchReferentRewards } from '@/features/rewarded-referents/api'
import { rewardedReferentsKeys } from '@/features/rewarded-referents/query-keys'
import type { RewardDetailItem } from '@/features/rewards/types'

/**
 * Lazy-loads one Referent's reward list for the master/detail expanded row
 * (spec 0059 D-4). The detail renderer only mounts once AG Grid expands the
 * row, so the fetch is naturally deferred to that moment — no extra `enabled`
 * gate is needed on top of the mount itself.
 *
 * `staleTime: Infinity` is the deliberate mechanism behind AC-026 ("collassando
 * e riespandendo non rifà la richiesta"): AG Grid unmounts the detail renderer
 * on collapse (no `keepDetailRows`), so a naive re-mount with the default
 * `staleTime: 0` would trigger a background refetch on every re-expand even
 * though the cached data is still authoritative for the session. The query key
 * is stable per referent, so React Query serves the cached result instead.
 */
export function useReferentRewards(
  referentId: number,
  options: { enabled?: boolean } = {},
): UseQueryResult<RewardDetailItem[], AxiosError> {
  return useQuery({
    queryKey: rewardedReferentsKeys.rewards(referentId),
    queryFn: () => fetchReferentRewards(referentId),
    staleTime: Infinity,
    enabled: options.enabled ?? true,
  })
}
