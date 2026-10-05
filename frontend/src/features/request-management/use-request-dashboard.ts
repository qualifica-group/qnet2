import { useCallback } from 'react'
import { keepPreviousData, useQuery, useQueryClient } from '@tanstack/react-query'
import {
  fetchRequestManagementDashboard,
  type RequestDashboardQuery,
} from '@/features/request-management/dashboard-api'
import { previousPeriod } from '@/features/request-management/dashboard-period-presets'
import { requestManagementKeys } from '@/features/request-management/query-keys'
import { useRequestModule } from '@/features/request-management/request-module'

/**
 * Server state of the dashboard's aggregates (spec 0107 D-5): synchronous,
 * no polling — unlike the CSV report's `ExportRun` cycle. `enabled` mirrors
 * the filter form's own validity (e.g. every branch deselected fails the
 * shared Zod schema, AC-044): an invalid form never issues a request. The
 * dashboard lives on its own page since spec 0185, so nothing fetches until
 * that page mounts `RequestDashboard`.
 *
 * `query` doubles as the query key (`requestManagementKeys.dashboard`), so a
 * changed filter is a DIFFERENT cache entry — a late response for a filter
 * combination the user has since left behind can never land on the one
 * currently rendered. That is what `applied` (the contract's echo of the
 * request's own filters) is FOR; TanStack's key-scoped cache already
 * delivers it structurally, so no separate client-side comparison is added.
 */
export function useRequestDashboard(query: RequestDashboardQuery, enabled: boolean) {
  const module = useRequestModule()

  return useQuery({
    queryKey: requestManagementKeys.dashboard(module.key, query),
    queryFn: () => fetchRequestManagementDashboard(module.apiBasePath, query),
    enabled,
    // A new filter combination keeps the last numbers on screen (flagged
    // `isPlaceholderData`) instead of flashing back to the skeleton (spec 0192).
    placeholderData: keepPreviousData,
  })
}

/**
 * The SAME dashboard on the period of equal length right before the applied
 * one (spec 0192 D-4): only the two dates change, so the comparison covers
 * exactly the same categories, Sedi and operators. Undefined — never the
 * current period's data, which shares the query key when disabled — with an
 * open bound, until it loads, or when it fails: the tiles then simply show
 * no change, the page never waits on this second call.
 */
export function useRequestDashboardPrevious(query: RequestDashboardQuery, enabled: boolean) {
  const module = useRequestModule()
  const previous = previousPeriod({ date_from: query.date_from ?? '', date_to: query.date_to ?? '' })
  const previousQuery = previous ? { ...query, ...previous } : query

  // No placeholder here, unlike the main query: a change computed against
  // another selection's previous period would be a wrong number, not a stale one.
  const { data } = useQuery({
    queryKey: requestManagementKeys.dashboard(module.key, previousQuery),
    queryFn: () => fetchRequestManagementDashboard(module.apiBasePath, previousQuery),
    enabled: enabled && previous !== null,
  })

  return previous ? data : undefined
}

/**
 * Stable callback that marks every dashboard entry of the module stale after
 * a write that moves an indicator. The panel stays mounted while a note is
 * written in the modal work panel or the row's notes dialog, and
 * `refetchOnWindowFocus` is off: without this the tiles keep the value read
 * when the panel opened. Same semantics as `useInvalidateModuleStats` — an
 * open panel refetches now, a closed one is served fresh on the next open.
 */
export function useInvalidateRequestDashboard(): () => void {
  const queryClient = useQueryClient()
  const module = useRequestModule()

  return useCallback(() => {
    void queryClient.invalidateQueries({ queryKey: requestManagementKeys.dashboardAll(module.key) })
  }, [queryClient, module.key])
}
