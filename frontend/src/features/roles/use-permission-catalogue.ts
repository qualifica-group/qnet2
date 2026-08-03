import { useQuery } from '@tanstack/react-query'
import { fetchPermissionCatalogue } from '@/features/roles/permission-catalogue-api'

/** The catalogue rarely changes within a session (mirrors the retired field catalogue's 5 min). */
const PERMISSION_CATALOGUE_STALE_TIME_MS = 5 * 60 * 1000

const PERMISSION_CATALOGUE_QUERY_KEY = ['authorization', 'permission-catalogue'] as const

/**
 * Loads the Area > Module permission catalogue (spec 0076) backing the Role
 * form's two-panel explorer and the read-only role detail view. Always
 * enabled: unlike the retired field catalogue, this is the single source for
 * the `permissions` field's own tree, not a section gated behind a separate
 * ability check.
 */
export function usePermissionCatalogue() {
  return useQuery({
    queryKey: PERMISSION_CATALOGUE_QUERY_KEY,
    queryFn: fetchPermissionCatalogue,
    staleTime: PERMISSION_CATALOGUE_STALE_TIME_MS,
  })
}
