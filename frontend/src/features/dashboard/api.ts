/**
 * Raw HTTP adapter of the dashboard module (spec 0151 data_contract).
 */

import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type { DashboardTaskCounters } from '@/features/dashboard/types'

/** Fetches the "Attività da completare" Task counters (`GET /api/dashboard/tasks`). */
export async function fetchDashboardTaskCounters(): Promise<DashboardTaskCounters> {
  const { data } = await apiClient.get<ApiResponse<DashboardTaskCounters>>('/dashboard/tasks')
  return data.data
}
