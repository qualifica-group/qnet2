import { useQuery } from '@tanstack/react-query'
import { fetchRequestManagementReportSites } from '@/features/request-management/report-api'
import { requestManagementKeys } from '@/features/request-management/query-keys'

/**
 * Loads the operational sites the report may be filtered by (spec 0112,
 * D-10). Twin of `useRequestReportOperators`, including the `enabled` gate:
 * the toolbar mounting the slot must not trigger the call on its own — it
 * fires when the panel opens or the filter sheet is opened.
 */
export function useRequestReportSites(enabled: boolean) {
  return useQuery({
    queryKey: requestManagementKeys.reportSites(),
    queryFn: fetchRequestManagementReportSites,
    enabled,
  })
}
