import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type { User } from '@/features/auth/types'
import type { OnboardingOutcome } from '@/features/onboarding/types'

/** Records the welcome tour as seen (spec 0368); returns the updated authenticated user. */
export async function saveOnboarding(outcome: OnboardingOutcome): Promise<User> {
  const { data } = await apiClient.put<ApiResponse<User>>('/auth/me/onboarding', { outcome })
  return data.data
}
