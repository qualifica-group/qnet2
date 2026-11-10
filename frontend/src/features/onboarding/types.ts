/** Welcome tour state of the authenticated user (spec 0368 D-6), computed by the backend. */
export type OnboardingStatus = 'required' | 'outdated' | 'completed'

/** What the user did with the tour: body of PUT /auth/me/onboarding. */
export type OnboardingOutcome = 'completed' | 'skipped'

/** `mandatory`: no skip, Esc and outside clicks ignored (D-9). `skippable`: Skip available. */
export type TourMode = 'mandatory' | 'skippable'

/**
 * Where a step lives, which drives the sidebar visibility (D-11):
 * `center` intro/outro card with no target, `sidebar` a part of the side menu
 * (the tour shows the menu first), `page` an element outside the menu.
 */
export type StepSurface = 'center' | 'sidebar' | 'page'

/** Side of the target the card prefers; the placement falls back when it does not fit. */
export type CardPlacement = 'top' | 'bottom' | 'left' | 'right'

export interface TourStep {
  /** Step id: also the `data-onboarding` anchor value and the i18n key under `steps`. */
  id: string
  surface: StepSurface
  placement: CardPlacement
}
