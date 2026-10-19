import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { resendWelcomeEmail } from '@/features/users/api'
import { backendErrorMessage } from '@/features/users/user-action-errors'

/** Resend-welcome action of the user detail (spec 0177): toast + detail refresh. */
export function useResendWelcomeEmail(userId: number) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: () => resendWelcomeEmail(userId),
    onSuccess: (message) => {
      toast.success(message ?? t('users.detail.resendWelcomeSuccess'))
      void queryClient.invalidateQueries({ queryKey: ['users', 'detail', userId] })
    },
    onError: (error) => {
      toast.error(backendErrorMessage(error) ?? t('users.detail.resendWelcomeError'))
    },
  })
}
