import { describe, expect, it } from 'vitest'
import { BookOpen, Circle, ShieldCheck } from 'lucide-react'
import { resolveHelpGuideIcon } from '@/features/help/help-icons'
import { GENERAL_HELP_KEY } from '@/features/help/help-guide-keys'

/** Spec 0143 rev 2, AC-014: icon resolution for the help index/view. */
describe('resolveHelpGuideIcon', () => {
  it('gives the "general" guide its own fixed icon, ignoring any icon name', () => {
    expect(resolveHelpGuideIcon(GENERAL_HELP_KEY, null)).toBe(BookOpen)
    expect(resolveHelpGuideIcon(GENERAL_HELP_KEY, 'shield-check')).toBe(BookOpen)
  })

  it('resolves a module guide through the same map the sidebar uses', () => {
    expect(resolveHelpGuideIcon('roles', 'shield-check')).toBe(ShieldCheck)
  })

  it('falls back to the neutral Circle icon when the module has no icon', () => {
    expect(resolveHelpGuideIcon('roles', null)).toBe(Circle)
  })
})
