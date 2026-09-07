import { useQuery } from '@tanstack/react-query'
import { env } from '@/config/env'
import { fetchDeployedVersions } from '@/features/app-version/api'
import {
  backendBaselineVersion,
  rememberBackendVersion,
} from '@/features/app-version/backend-baseline'
import { appVersionKeys } from '@/features/app-version/query-keys'

/**
 * Detects that a newer build has been deployed while this client kept running,
 * so the UI can ask the user to reload instead of letting them work on a stale
 * bundle against a redeployed API.
 *
 * Two independent signals, either of which marks the session outdated:
 * - FRONTEND: the build id baked into this bundle vs the one in the deployed
 *   `version.json`.
 * - BACKEND: the deployed API build id vs the first one this session saw.
 *
 * Polling pauses while the tab is in the background (TanStack Query's default)
 * and catches up on focus, so a machine left open overnight notices the deploy
 * the moment its user comes back rather than one interval later.
 *
 * `enabled` is a parameter rather than an internal env read so the caller owns
 * the decision (production bundles only) and the hook stays testable.
 */
export function useAppVersion(enabled: boolean): { isOutdated: boolean } {
  const { data } = useQuery({
    queryKey: appVersionKeys.deployed,
    queryFn: async () => {
      const versions = await fetchDeployedVersions()
      // The poll is where the session's backend reference point is established:
      // an effectful boundary, never a render.
      rememberBackendVersion(versions.backend)
      return versions
    },
    enabled,
    refetchInterval: env.versionPollInterval,
    refetchOnWindowFocus: true,
    staleTime: 0,
    // The probes never reject (see api.ts), so a retry policy would only add
    // latency; the next interval is the retry.
    retry: false,
  })

  return {
    isOutdated:
      hasChanged(env.buildVersion, data?.frontend ?? null) ||
      hasChanged(backendBaselineVersion(), data?.backend ?? null),
  }
}

/**
 * A version counts as changed only when BOTH sides are known. An unknown side —
 * a probe that failed, a backend with no APP_VERSION configured — must never
 * prompt: a false "please reload" is worse than a missed one.
 */
function hasChanged(running: string | null, deployed: string | null): boolean {
  return running !== null && deployed !== null && running !== deployed
}
