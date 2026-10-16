import { useMemo } from 'react'
import { useQuery } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { fetchBilling, managementControlKeys, toBillingParams } from '@/features/management-control/api'
import type { Billing, BillingFilters } from '@/features/management-control/billing-types'

/** Backlog snapshot and proforma cycle for the given filters. */
export function useBilling(filters: BillingFilters) {
  const params = useMemo(() => toBillingParams(filters), [filters])

  return useQuery<Billing, AxiosError>({
    queryKey: managementControlKeys.billing(params),
    queryFn: () => fetchBilling(params),
  })
}
