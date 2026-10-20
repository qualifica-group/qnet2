import { useCallback } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { userSkillKeys } from '@/features/user-skills/query-keys'

/** After any write on a skill: refresh its detail, the coverage and the grid, which the page owns. */
export function useUserSkillRefresh(refreshGrid?: () => void) {
  const queryClient = useQueryClient()

  return useCallback(
    (id: number) => {
      void queryClient.invalidateQueries({ queryKey: userSkillKeys.detail(id) })
      void queryClient.invalidateQueries({ queryKey: userSkillKeys.coverageAll })
      refreshGrid?.()
    },
    [queryClient, refreshGrid],
  )
}
