import { describe, expect, it } from 'vitest'
import { GENERAL_HELP_KEY, HELP_GUIDE_KEYS } from '@/features/help/help-guide-keys'

describe('HELP_GUIDE_KEYS (spec 0143 context: 49 navigation keys + general)', () => {
  it('has exactly 50 unique keys, "general" first', () => {
    expect(HELP_GUIDE_KEYS).toHaveLength(50)
    expect(new Set(HELP_GUIDE_KEYS).size).toBe(50)
    expect(HELP_GUIDE_KEYS[0]).toBe(GENERAL_HELP_KEY)
  })
})
