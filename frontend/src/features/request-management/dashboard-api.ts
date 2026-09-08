import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type { RequestReportFilterPayload } from '@/features/request-management/report-api'

/**
 * Dashboard contract (spec 0107): synchronous JSON, the SAME filter shape as
 * the CSV report (spec 0106/0109) but no `ExportRun`/polling — D-5. The type
 * is literally the report's own payload, so the two can never drift.
 */
export type RequestDashboardQuery = RequestReportFilterPayload

/** One KPI tile: `label` already translated server-side (D-9), `value` the real total (D-8). */
export interface RequestDashboardSummaryItem {
  key: string
  label: string
  value: number
}

/** `category` -> one point per selected branch; `operator` -> one point per GA2 of one branch. */
export type RequestDashboardChartScope = 'category' | 'operator'

export interface RequestDashboardChartPoint {
  label: string
  value: number
}

export interface RequestDashboardChart {
  id: string
  scope: RequestDashboardChartScope
  /** Set only for `scope: 'operator'` — the branch the operator breakdown belongs to. */
  category_key: string | null
  category_label: string | null
  indicator_key: string
  indicator_label: string
  /** Already ordered by value desc, then label asc (rev-2 AC-007) — never re-sort. */
  points: RequestDashboardChartPoint[]
}

/** Response of `GET /request-management/report/dashboard` (envelope `data`). */
export interface RequestDashboardData {
  /**
   * Echo of the applied filters — compare against the current query to
   * discard an out-of-order response. `operator_keys` is `null` (not absent)
   * when none was sent, i.e. "every operator" (spec 0109 D-2/AC-009).
   */
  applied: Omit<RequestDashboardQuery, 'operator_keys'> & { operator_keys: string[] | null }
  summary: RequestDashboardSummaryItem[]
  charts: RequestDashboardChart[]
}

/**
 * Fetches the dashboard aggregates for one filter combination
 * (`GET /request-management/report/dashboard`). Array query params use the
 * same indexed serialization as `fetchForSelect` (Laravel's array
 * convention), not the default axios repeated-key form.
 */
export async function fetchRequestManagementDashboard(
  query: RequestDashboardQuery,
): Promise<RequestDashboardData> {
  const { data } = await apiClient.get<ApiResponse<RequestDashboardData>>(
    '/request-management/report/dashboard',
    {
      params: query,
      paramsSerializer: { indexes: true },
    },
  )
  return data.data
}
