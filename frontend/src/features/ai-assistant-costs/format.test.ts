import { describe, expect, it } from 'vitest'
import { formatUsd } from '@/features/ai-assistant-costs/format'
import { currentMonthPeriod, isValidPeriod } from '@/features/ai-assistant-costs/period'

describe('formatUsd', () => {
  it('keeps the precision of the ledger, trimming zeros down to two decimals and never rounding', () => {
    expect(formatUsd('1.750000000000')).toBe('$1.75')
    expect(formatUsd('0.000123456789')).toBe('$0.000123456789')
    expect(formatUsd('0.000000000000')).toBe('$0.00')
    expect(formatUsd('12345.500000000000')).toBe('$12,345.50')
  })
})

describe('period', () => {
  it('starts on the first of the month and ends today', () => {
    expect(currentMonthPeriod(new Date(2026, 10, 26))).toEqual({ from: '2026-11-01', to: '2026-11-26' })
  })

  it('accepts only a complete, ordered period', () => {
    expect(isValidPeriod({ from: '2026-11-01', to: '2026-11-01' })).toBe(true)
    expect(isValidPeriod({ from: '2026-11-02', to: '2026-11-01' })).toBe(false)
    expect(isValidPeriod({ from: '', to: '2026-11-01' })).toBe(false)
  })
})
