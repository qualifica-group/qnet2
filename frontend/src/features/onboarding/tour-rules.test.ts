import { describe, expect, it } from 'vitest'
import { ONBOARDING_STEPS } from '@/features/onboarding/steps'
import { autostartMode, availableSteps, manualStartMode, skipOutcome } from '@/features/onboarding/tour-rules'

describe('tour rules (spec 0368)', () => {
  it('starts mandatory when never seen, skippable when outdated, never when completed', () => {
    expect(autostartMode('required', 'none')).toBe('mandatory')
    expect(autostartMode('outdated', 'none')).toBe('skippable')
    expect(autostartMode('completed', 'none')).toBeNull()
  })

  it('never starts while impersonating or before the impersonation state is known (D-8)', () => {
    expect(autostartMode('required', 'active')).toBeNull()
    expect(autostartMode('required', 'pending')).toBeNull()
  })

  it('keeps a replay mandatory only for a tour never seen', () => {
    expect(manualStartMode('required')).toBe('mandatory')
    expect(manualStartMode('outdated')).toBe('skippable')
    expect(manualStartMode('completed')).toBe('skippable')
  })

  it('records a skip only for an outdated tour', () => {
    expect(skipOutcome('outdated')).toBe('skipped')
    expect(skipOutcome('completed')).toBeNull()
    expect(skipOutcome('required')).toBeNull()
  })

  it('keeps intro, outro and side-menu steps, and only the page steps on screen (D-10)', () => {
    const ids = availableSteps(ONBOARDING_STEPS, ['help']).map((step) => step.id)

    expect(ids).toEqual(['welcome', 'sidebar-brand', 'sidebar-nav', 'sidebar-footer', 'help', 'finish'])
  })
})
