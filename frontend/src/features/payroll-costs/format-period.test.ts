import { describe, expect, it } from 'vitest'
import { formatPeriod } from '@/features/payroll-costs/format-period'

describe('formatPeriod', () => {
  it('formats YYYY-MM as month and year in the given language', () => {
    expect(formatPeriod('2026-10', 'en')).toBe('October 2026')
    expect(formatPeriod('2026-10', 'it')).toBe('ottobre 2026')
  })

  it('returns an unparsable value unchanged', () => {
    expect(formatPeriod('n/a', 'en')).toBe('n/a')
  })
})
