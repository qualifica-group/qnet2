import { useQuery } from '@tanstack/react-query'
import { fetchRequestManagementReportOperators } from '@/features/request-management/report-api'
import { requestManagementKeys } from '@/features/request-management/query-keys'
import { useRequestModule } from '@/features/request-management/request-module'

/**
 * Loads the GA2 the report may be filtered by (spec 0109, D-6). Twin of
 * `useRequestReportCategories`, including the `enabled` gate: the toolbar
 * mounting the slot must not trigger the call on its own — it fires when the
 * panel opens or the filter sheet is opened.
 */
export function useRequestReportOperators(enabled: boolean) {
  const module = useRequestModule()

  return useQuery({
    queryKey: requestManagementKeys.reportOperators(module.key),
    queryFn: () => fetchRequestManagementReportOperators(module.apiBasePath),
    enabled,
  })
}
