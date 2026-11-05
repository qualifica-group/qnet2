import { useQuery } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { fetchTreasuryOverview, treasuryOverviewKeys } from '@/features/treasury-overview/api'
import type { TreasuryOverview } from '@/features/treasury-overview/types'

export function useTreasuryOverview(companyId: number | null) {
  return useQuery<TreasuryOverview, AxiosError>({
    queryKey: treasuryOverviewKeys.detail(companyId),
    queryFn: () => fetchTreasuryOverview(companyId),
  })
}
