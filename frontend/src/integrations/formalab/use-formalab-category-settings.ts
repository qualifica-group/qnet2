import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { fetchFormalabCategorySettings, updateFormalabCategorySettings } from '@/integrations/formalab/api'
import { serverMessage } from '@/integrations/formalab/error-message'
import { formalabKeys } from '@/integrations/formalab/query-keys'
import type { FormalabCategorySettings, FormalabCategorySettingsPayload } from '@/integrations/formalab/types'

export function useFormalabCategorySettings(categoryId: number) {
  return useQuery({
    queryKey: formalabKeys.categorySettings(categoryId),
    queryFn: () => fetchFormalabCategorySettings(categoryId),
    retry: false,
  })
}

/**
 * Saves the category's settings. Another category of the branch and the
 * work order tabs may resolve differently afterwards, so every cached
 * Formalab category and work order state is refreshed.
 */
export function useUpdateFormalabCategorySettings(categoryId: number, onSaved: () => void) {
  const { t } = useTranslation('formalab')
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (payload: FormalabCategorySettingsPayload) => updateFormalabCategorySettings(categoryId, payload),
    onSuccess: (result: FormalabCategorySettings) => {
      queryClient.setQueryData(formalabKeys.categorySettings(categoryId), result)
      void queryClient.invalidateQueries({ queryKey: formalabKeys.allCategorySettings })
      void queryClient.invalidateQueries({ queryKey: formalabKeys.workOrderLinks })
      toast.success(t('category.saved'))
      onSaved()
    },
    onError: (error) => toast.error(serverMessage(error, t('category.saveFailed'))),
  })
}
