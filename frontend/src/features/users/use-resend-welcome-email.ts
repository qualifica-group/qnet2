import axios from 'axios'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { resendWelcomeEmail } from '@/features/users/api'

/** Backend `errors` bag of a rejected resend (e.g. `user`: already activated). */
function backendMessage(error: unknown): string | null {
  if (!axios.isAxiosError(error)) {
    return null
  }
  const data = error.response?.data as
    | { message?: string; errors?: Record<string, string[]> }
    | undefined
  return data?.errors?.user?.[0] ?? data?.message ?? null
}

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
      toast.error(backendMessage(error) ?? t('users.detail.resendWelcomeError'))
    },
  })
}
