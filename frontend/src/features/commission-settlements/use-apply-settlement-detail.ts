import { useQueryClient } from '@tanstack/react-query'
import { commissionSettlementKeys } from '@/features/commission-settlements/api'
import type { SettlementDetailWithPermissions } from '@/features/commission-settlements/types'

/** Replaces the cached detail with a fresh server response, then tells the caller to refresh its grid. */
export function useApplySettlementDetail(settlementId: number, onChanged: () => void) {
  const queryClient = useQueryClient()
  return (data: SettlementDetailWithPermissions) => {
    queryClient.setQueryData(commissionSettlementKeys.detail(settlementId), data)
    onChanged()
  }
}
