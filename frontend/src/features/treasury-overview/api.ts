import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type { TreasuryOverview } from '@/features/treasury-overview/types'

/** Centralized query keys; the company is part of the key, so a change refetches. */
export const treasuryOverviewKeys = {
  all: ['treasury-overview'] as const,
  detail: (companyId: number | null) => ['treasury-overview', companyId] as const,
}

/** `GET /api/treasury-overview`; "All" companies omits the parameter. */
export async function fetchTreasuryOverview(companyId: number | null): Promise<TreasuryOverview> {
  const { data } = await apiClient.get<ApiResponse<TreasuryOverview>>('/treasury-overview', {
    params: companyId !== null ? { company_id: companyId } : {},
  })
  return data.data
}
