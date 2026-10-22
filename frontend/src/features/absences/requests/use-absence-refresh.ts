import { useCallback } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { absenceKeys } from '@/features/absences/query-keys'

/**
 * After any write on one or many requests: refresh their detail, every balance
 * (used and pending minutes move), the decision insight, the page summary (and
 * with it the menu badge), the calendar (spec 0279) and the grid, which the page owns.
 */
export function useAbsenceRefresh(refreshGrid?: () => void) {
  const queryClient = useQueryClient()

  return useCallback(
    (ids: number | readonly number[]) => {
      for (const id of typeof ids === 'number' ? [ids] : ids) {
        void queryClient.invalidateQueries({ queryKey: absenceKeys.detail(id) })
      }
      void queryClient.invalidateQueries({ queryKey: absenceKeys.balancesAll })
      void queryClient.invalidateQueries({ queryKey: absenceKeys.insightAll })
      void queryClient.invalidateQueries({ queryKey: absenceKeys.summary })
      void queryClient.invalidateQueries({ queryKey: absenceKeys.calendarAll })
      refreshGrid?.()
    },
    [queryClient, refreshGrid],
  )
}
