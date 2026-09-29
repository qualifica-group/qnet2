import { useCallback } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { fetchRequestManagementCategories } from '@/features/request-management/api'
import { requestManagementKeys } from '@/features/request-management/query-keys'
import { useRequestModule } from '@/features/request-management/request-module'

/**
 * Loads the Product Category tab strip (spec 0064): only categories with at
 * least one request in the actor's own scope, each with its request count.
 * Both move with every write on a request (its product lines, a create, a
 * delete, a transfer), so the list is never held fresh: it is re-read each
 * time the table mounts — e.g. back from the work page — while the cached
 * copy paints the strip meanwhile. Switching tabs does not remount this
 * query's owner, so it never re-fetches.
 */
export function useRequestManagementCategories() {
  const module = useRequestModule()

  return useQuery({
    queryKey: requestManagementKeys.categories(module.key),
    queryFn: () => fetchRequestManagementCategories(module.apiBasePath),
  })
}

/**
 * Stable callback that re-reads the tab strip after a write made while the
 * table stays mounted (modal work panel, delete, bulk flows): the remount
 * refetch above does not cover those.
 */
export function useInvalidateRequestManagementCategories(): () => void {
  const queryClient = useQueryClient()
  const module = useRequestModule()

  return useCallback(() => {
    void queryClient.invalidateQueries({ queryKey: requestManagementKeys.categories(module.key) })
  }, [queryClient, module.key])
}
