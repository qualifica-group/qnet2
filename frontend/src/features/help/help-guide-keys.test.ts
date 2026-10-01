import { describe, expect, it } from 'vitest'
import { GENERAL_HELP_KEY, HELP_GUIDE_KEYS } from '@/features/help/help-guide-keys'

describe('HELP_GUIDE_KEYS (spec 0143 context: 49 navigation keys + general; spec 0150 adds "notifications"; spec 0175 adds "email-templates"/"document-bundles"; spec 0185 adds "request-statistics")', () => {
  it('has exactly 54 unique keys, "general" first', () => {
    expect(HELP_GUIDE_KEYS).toHaveLength(54)
    expect(new Set(HELP_GUIDE_KEYS).size).toBe(54)
    expect(HELP_GUIDE_KEYS[0]).toBe(GENERAL_HELP_KEY)
  })
})
