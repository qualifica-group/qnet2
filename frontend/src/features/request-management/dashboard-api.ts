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

/** `indicator` -> one point per indicator column of the category; `operator` -> one point per GA2 on ONE indicator. */
export type RequestDashboardChartScope = 'indicator' | 'operator'

export interface RequestDashboardChartPoint {
  label: string
  value: number
}

export interface RequestDashboardChart {
  id: string
  scope: RequestDashboardChartScope
  /** Null for `scope: 'indicator'` — there the indicator IS the series, one bar each. */
  indicator_key: string | null
  indicator_label: string | null
  /**
   * `indicator` charts follow the report's own column order, `operator` ones
   * value desc then label asc (rev-2 AC-007) — never re-sort either.
   */
  points: RequestDashboardChartPoint[]
}

/** One category section (rev-3 D-10): its own tiles and its own charts. */
export interface RequestDashboardCategory {
  key: string
  label: string
  summary: RequestDashboardSummaryItem[]
  charts: RequestDashboardChart[]
}

/** Response of `GET /request-management/report/dashboard` (envelope `data`). */
export interface RequestDashboardData {
  /**
   * Echo of the applied filters — compare against the current query to
   * discard an out-of-order response. `operator_keys` and `site_keys` are
   * `null` (not absent) when none was sent, i.e. "every operator" / "every
   * site" (spec 0109 D-2/AC-009, spec 0112 D-4).
   */
  applied: Omit<RequestDashboardQuery, 'operator_keys' | 'site_keys'> & {
    operator_keys: string[] | null
    site_keys: string[] | null
  }
  /** Overall tiles over the union of the selected branches (D-8), every indicator column included (rev-3 D-11). */
  summary: RequestDashboardSummaryItem[]
  /** One section per selected category, in the report's own branch order (rev-3 D-10). */
  categories: RequestDashboardCategory[]
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
