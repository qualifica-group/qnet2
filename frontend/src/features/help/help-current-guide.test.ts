import { describe, expect, it } from 'vitest'
import { GENERAL_HELP_KEY } from '@/features/help/help-guide-keys'
import { resolveCurrentHelpGuideKey } from '@/features/help/help-current-guide'
import type { HelpVisibleGuide } from '@/features/help/help-visible-guides'

const GUIDES: HelpVisibleGuide[] = [
  { key: GENERAL_HELP_KEY, label: 'General', route: null, groupLabel: null, groupIcon: null, icon: null },
  {
    key: 'request-management',
    label: 'Request management',
    route: '/request-management',
    groupLabel: null,
    groupIcon: null,
    icon: null,
  },
  {
    key: 'field-change-requests',
    label: 'Field change requests',
    route: '/field-change-requests',
    groupLabel: null,
    groupIcon: null,
    icon: null,
  },
]

describe('resolveCurrentHelpGuideKey (AC-002/AC-003)', () => {
  it('matches the exact route', () => {
    expect(resolveCurrentHelpGuideKey(GUIDES, '/request-management')).toBe('request-management')
  })

  it('matches a nested path as a route prefix', () => {
    expect(resolveCurrentHelpGuideKey(GUIDES, '/request-management/12')).toBe('request-management')
  })

  it('falls back to "general" when no route matches', () => {
    expect(resolveCurrentHelpGuideKey(GUIDES, '/settings')).toBe(GENERAL_HELP_KEY)
  })

  it('does not treat a route as a prefix of an unrelated, differently-named route', () => {
    expect(resolveCurrentHelpGuideKey(GUIDES, '/request-management-archive')).toBe(GENERAL_HELP_KEY)
  })
})
