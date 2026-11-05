import { useQuery } from '@tanstack/react-query'
import { fetchFinancialAccount } from '@/features/financial-accounts/api'
import { treasuryMovementKeys } from '@/features/treasury-movements/query-keys'

const ORIGIN_STALE_MS = 60 * 1000

/** Company of the origin account, the only one a transfer destination may belong to (spec 0360 D-6). */
export function useOriginCompany(accountId: number | null) {
  const { data, isLoading } = useQuery({
    queryKey: treasuryMovementKeys.originAccount(accountId ?? 0),
    queryFn: () => fetchFinancialAccount(accountId as number),
    enabled: accountId !== null,
    staleTime: ORIGIN_STALE_MS,
  })
  return { companyId: data?.company?.id ?? null, isLoading: accountId !== null && isLoading }
}
