import { useMutation, useQueryClient } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { bulkSaveAbsenceAllowances } from '@/features/absences/api'
import { absenceKeys } from '@/features/absences/query-keys'
import type { BulkAllowancePayload } from '@/features/absences/types'

/** Sets the allowance of many people and refreshes every cached balance (overview and personal tiles). */
export function useBulkAllowance() {
  const queryClient = useQueryClient()

  return useMutation<{ updated: number }, AxiosError, BulkAllowancePayload>({
    mutationFn: bulkSaveAbsenceAllowances,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: absenceKeys.balancesAll }),
  })
}
