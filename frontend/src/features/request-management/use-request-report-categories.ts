import { useQuery } from '@tanstack/react-query'
import { fetchRequestManagementReportCategories } from '@/features/request-management/report-api'
import { requestManagementKeys } from '@/features/request-management/query-keys'
import { useRequestModule } from '@/features/request-management/request-module'

/**
 * Loads the report's selectable branches (spec 0106 rev-2, AC-049): fetched
 * only while the dialog is `open`, so the toolbar mounting the slot never
 * triggers the call on its own.
 */
export function useRequestReportCategories(open: boolean) {
  const module = useRequestModule()

  return useQuery({
    queryKey: requestManagementKeys.reportCategories(module.key),
    queryFn: () => fetchRequestManagementReportCategories(module.apiBasePath),
    enabled: open,
  })
}
