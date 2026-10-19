import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { serverMessage } from '@/features/absences/requests/absence-server-errors'
import { deleteUserSkill } from '@/features/user-skills/api'
import { userSkillKeys } from '@/features/user-skills/query-keys'

/** Deletes one person skill, then drops its detail (it would 404), refreshes the coverage and the grid. */
export function useUserSkillRemove(id: number, refreshGrid?: () => void, onDeleted?: () => void) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: () => deleteUserSkill(id),
    onSuccess: () => {
      queryClient.removeQueries({ queryKey: userSkillKeys.detail(id) })
      void queryClient.invalidateQueries({ queryKey: userSkillKeys.coverageAll })
      refreshGrid?.()
      toast.success(t('userSkills.actions.deleted'))
      onDeleted?.()
    },
    onError: (error: unknown) => toast.error(serverMessage(error) ?? t('userSkills.actions.error')),
  })
}
