import { useCallback } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { absenceKeys } from '@/features/absences/query-keys'

/**
 * After any write on a request: refresh its detail, every balance (used and
 * pending minutes move), the calendar (spec 0279) and the grid, which the page owns.
 */
export function useAbsenceRefresh(refreshGrid?: () => void) {
  const queryClient = useQueryClient()

  return useCallback(
    (id: number) => {
      void queryClient.invalidateQueries({ queryKey: absenceKeys.detail(id) })
      void queryClient.invalidateQueries({ queryKey: absenceKeys.balancesAll })
      void queryClient.invalidateQueries({ queryKey: absenceKeys.calendarAll })
      refreshGrid?.()
    },
    [queryClient, refreshGrid],
  )
}
