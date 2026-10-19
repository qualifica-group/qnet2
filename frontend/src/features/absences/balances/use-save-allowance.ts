import { useMutation, useQueryClient } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { saveAbsenceAllowance } from '@/features/absences/api'
import { absenceKeys } from '@/features/absences/query-keys'
import type { AbsenceBalanceRow, AllowancePayload } from '@/features/absences/types'

/** Sets a yearly allowance and refreshes every cached balance (the residual of other years may change via carry-over). */
export function useSaveAllowance() {
  const queryClient = useQueryClient()

  return useMutation<AbsenceBalanceRow, AxiosError, AllowancePayload>({
    mutationFn: saveAbsenceAllowance,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: absenceKeys.balancesAll }),
  })
}
