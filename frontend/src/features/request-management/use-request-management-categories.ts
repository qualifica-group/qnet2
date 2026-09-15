import { useQuery } from '@tanstack/react-query'
import { fetchRequestManagementCategories } from '@/features/request-management/api'
import { requestManagementKeys } from '@/features/request-management/query-keys'
import { useRequestModule } from '@/features/request-management/request-module'

/**
 * Loads the Product Category tab strip (spec 0064): only categories with at
 * least one request in the actor's own scope. Cached like the rest of the
 * generic table's config (semi-static, changes only with the actor's own
 * requests), so switching tabs back and forth never re-fetches it.
 */
export function useRequestManagementCategories() {
  const module = useRequestModule()

  return useQuery({
    queryKey: requestManagementKeys.categories(module.key),
    queryFn: () => fetchRequestManagementCategories(module.apiBasePath),
    staleTime: 5 * 60 * 1000,
  })
}
