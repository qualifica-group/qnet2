import { createContext, useContext } from 'react'

export interface OnboardingContextValue {
  /** Opens the welcome tour on demand (Settings). No-op while `canStart` is false. */
  start: () => void
  /** False while impersonating or while the impersonation state is loading (spec 0368 D-8). */
  canStart: boolean
  /** True while the impersonation state forbids the tour, to explain why it is unavailable. */
  isImpersonating: boolean
}

export const OnboardingContext = createContext<OnboardingContextValue | null>(null)

export function useOnboardingTour(): OnboardingContextValue {
  const context = useContext(OnboardingContext)
  if (!context) {
    throw new Error('useOnboardingTour must be used within an OnboardingProvider.')
  }
  return context
}
