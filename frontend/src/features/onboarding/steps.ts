import type { TourStep } from '@/features/onboarding/types'

/** DOM attribute every tour target carries; its value is the step id. */
export const ONBOARDING_ANCHOR_ATTRIBUTE = 'data-onboarding'

export const WELCOME_STEP_ID = 'welcome'
export const FINISH_STEP_ID = 'finish'

/**
 * Tour content, version 1 (spec 0368 D-2): the structure of the side menu, then
 * the header icons left to right, then the floating assistant. Changing this list
 * in a substantial way requires raising `auth.onboarding_version` on the backend
 * (D-4/D-5), so the users who already saw it are offered the new one.
 */
export const ONBOARDING_STEPS: readonly TourStep[] = [
  { id: WELCOME_STEP_ID, surface: 'center', placement: 'bottom' },
  { id: 'sidebar-brand', surface: 'sidebar', placement: 'right' },
  { id: 'sidebar-nav', surface: 'sidebar', placement: 'right' },
  { id: 'sidebar-footer', surface: 'sidebar', placement: 'right' },
  { id: 'sidebar-toggle', surface: 'page', placement: 'bottom' },
  { id: 'workspace-launcher', surface: 'page', placement: 'bottom' },
  { id: 'support-request', surface: 'page', placement: 'bottom' },
  { id: 'help', surface: 'page', placement: 'bottom' },
  { id: 'notifications', surface: 'page', placement: 'bottom' },
  { id: 'theme', surface: 'page', placement: 'bottom' },
  { id: 'user-menu', surface: 'page', placement: 'bottom' },
  { id: 'ai-assistant', surface: 'page', placement: 'top' },
  { id: FINISH_STEP_ID, surface: 'center', placement: 'bottom' },
]

/** Spread on a target element: `<div {...onboardingAnchor('help')}>`. */
export function onboardingAnchor(stepId: string): Record<string, string> {
  return { [ONBOARDING_ANCHOR_ATTRIBUTE]: stepId }
}

export function anchorSelector(stepId: string): string {
  return `[${ONBOARDING_ANCHOR_ATTRIBUTE}="${stepId}"]`
}
