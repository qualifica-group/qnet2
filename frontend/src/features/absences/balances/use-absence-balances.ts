import { useQuery } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { fetchAbsenceBalances } from '@/features/absences/api'
import { absenceKeys } from '@/features/absences/query-keys'
import type { AbsenceBalances, AbsenceBalancesParams } from '@/features/absences/types'

/** Balances of one person and year (`GET /absence-balances`); 403 is surfaced to the caller, not retried. */
export function useAbsenceBalances(params: AbsenceBalancesParams) {
  return useQuery<AbsenceBalances, AxiosError>({
    queryKey: absenceKeys.balances(params),
    queryFn: () => fetchAbsenceBalances(params),
    retry: false,
  })
}
