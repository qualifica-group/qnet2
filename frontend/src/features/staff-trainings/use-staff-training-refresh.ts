import { useCallback } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { staffTrainingKeys } from '@/features/staff-trainings/query-keys'

/** After any write on a training: refresh its detail, the compliance situation and the grid, which the page owns. */
export function useStaffTrainingRefresh(refreshGrid?: () => void) {
  const queryClient = useQueryClient()

  return useCallback(
    (id: number) => {
      void queryClient.invalidateQueries({ queryKey: staffTrainingKeys.detail(id) })
      void queryClient.invalidateQueries({ queryKey: staffTrainingKeys.complianceAll })
      refreshGrid?.()
    },
    [queryClient, refreshGrid],
  )
}
