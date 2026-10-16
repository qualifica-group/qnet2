import { describe, expect, it } from 'vitest'
import { formatVariation, sumMoney, variationPercent } from '@/features/management-control/money-math'
import { monthLabelFormatter } from '@/features/management-control/month-label'

describe('money-math', () => {
  it('AC-015 sums to the cent without float noise', () => {
    expect(sumMoney([0.1, 0.2])).toBe(0.3)
    expect(sumMoney([])).toBe(0)
  })

  it('AC-015 variation is null on a zero base, signed on the absolute base otherwise', () => {
    expect(variationPercent(100, 0)).toBeNull()
    expect(variationPercent(150, 100)).toBe(50)
    expect(variationPercent(50, 100)).toBe(-50)
    expect(variationPercent(-50, -100)).toBe(50)
  })

  it('AC-015 formats the variation with sign and one decimal in the active language', () => {
    expect(formatVariation(12.34, 'en')).toBe('+12.3%')
    expect(formatVariation(-5, 'en')).toBe('-5.0%')
    expect(formatVariation(0, 'en')).toBe('0.0%')
    expect(formatVariation(12.34, 'it')).toBe('+12,3%')
  })
})

describe('monthLabelFormatter', () => {
  it('AC-015 labels a YYYY-MM month in the language given, short month and two-digit year', () => {
    expect(monthLabelFormatter('en')('2026-10')).toBe('Oct 26')
    expect(monthLabelFormatter('it')('2026-10')).toBe('ott 26')
    expect(monthLabelFormatter('en')('2027-01')).toBe('Jan 27')
  })
})
