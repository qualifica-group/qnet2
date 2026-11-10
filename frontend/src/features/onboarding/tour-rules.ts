import type { OnboardingOutcome, OnboardingStatus, TourMode, TourStep } from '@/features/onboarding/types'

/**
 * Impersonation state as the tour sees it: `pending` until GET /auth/impersonation
 * answers. The tour waits for it, so an admin entering another user's session
 * never sees that user's tour, not even for a frame (spec 0368 D-8).
 */
export type ImpersonationPhase = 'pending' | 'none' | 'active'

/** Mode of the automatic start at app load, or null when the tour must not start. */
export function autostartMode(status: OnboardingStatus, impersonation: ImpersonationPhase): TourMode | null {
  if (impersonation !== 'none') {
    return null
  }
  if (status === 'required') {
    return 'mandatory'
  }
  return status === 'outdated' ? 'skippable' : null
}

/** Mode of a start from Settings: mandatory only if the tour was never seen (D-4). */
export function manualStartMode(status: OnboardingStatus): TourMode {
  return status === 'required' ? 'mandatory' : 'skippable'
}

/**
 * Outcome to record when the user skips. Only an `outdated` tour is recorded:
 * a replay of an already seen tour changes nothing, a mandatory one has no skip.
 */
export function skipOutcome(status: OnboardingStatus): OnboardingOutcome | null {
  return status === 'outdated' ? 'skipped' : null
}

/**
 * Steps shown right now: intro/outro and side-menu steps always (the tour opens
 * the menu itself, D-11), the other steps only while their element is on screen
 * (D-10: a header button hidden by a permission or a disabled module is skipped).
 */
export function availableSteps(steps: readonly TourStep[], presentIds: readonly string[]): TourStep[] {
  return steps.filter((step) => step.surface !== 'page' || presentIds.includes(step.id))
}
