import { keepPreviousData, useQuery } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { fetchAbsenceBalanceOverview } from '@/features/absences/api'
import { absenceKeys } from '@/features/absences/query-keys'
import type { AbsenceBalanceOverviewPage, AbsenceBalanceOverviewParams } from '@/features/absences/types'

/** One page of the people x types balances; the previous page stays on screen while the next one loads. */
export function useBalanceOverview(params: AbsenceBalanceOverviewParams) {
  return useQuery<AbsenceBalanceOverviewPage, AxiosError>({
    queryKey: absenceKeys.balanceOverview(params),
    queryFn: () => fetchAbsenceBalanceOverview(params),
    placeholderData: keepPreviousData,
    retry: false,
  })
}
