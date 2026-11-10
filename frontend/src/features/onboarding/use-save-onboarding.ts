import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { authKeys } from '@/features/auth/query-keys'
import type { User } from '@/features/auth/types'
import { saveOnboarding } from '@/features/onboarding/api'
import type { OnboardingOutcome } from '@/features/onboarding/types'

/**
 * Records the tour outcome and primes the authenticated user, so the new
 * `onboarding_status` applies without a refetch. On failure the status stays
 * as it was: the tour is offered again at the next app load.
 */
export function useSaveOnboarding() {
  const { t } = useTranslation('onboarding')
  const queryClient = useQueryClient()

  return useMutation<User, Error, OnboardingOutcome>({
    mutationFn: saveOnboarding,
    onSuccess: (user) => {
      queryClient.setQueryData(authKeys.me, user)
    },
    onError: () => {
      toast.error(t('tour.saveError'))
    },
  })
}
