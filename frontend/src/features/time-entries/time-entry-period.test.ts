import { describe, expect, it } from 'vitest'
import {
  formatPeriodLabel,
  getPeriodRange,
  isTimeEntriesPeriodUnit,
  parseDateString,
  shiftAnchor,
  toDateString,
} from '@/features/time-entries/time-entry-period'

/** Spec 0122 AC-032: period navigation (preset, prev/next, today). */

describe('getPeriodRange', () => {
  it('resolves an ISO week (Monday to Sunday)', () => {
    // 2026-09-16 is a Wednesday.
    expect(getPeriodRange('week', new Date(2026, 8, 16))).toEqual({
      from: '2026-09-14',
      to: '2026-09-20',
    })
  })

  it('resolves an ISO week anchored on a Sunday', () => {
    expect(getPeriodRange('week', new Date(2026, 8, 20))).toEqual({
      from: '2026-09-14',
      to: '2026-09-20',
    })
  })

  it('resolves a calendar month', () => {
    expect(getPeriodRange('month', new Date(2026, 1, 10))).toEqual({
      from: '2026-02-01',
      to: '2026-02-28',
    })
  })

  it('resolves a calendar year', () => {
    expect(getPeriodRange('year', new Date(2026, 5, 1))).toEqual({
      from: '2026-01-01',
      to: '2026-12-31',
    })
  })

  it('resolves a single day', () => {
    expect(getPeriodRange('day', new Date(2026, 8, 16))).toEqual({
      from: '2026-09-16',
      to: '2026-09-16',
    })
  })
})

describe('shiftAnchor', () => {
  it('moves the anchor to the next week', () => {
    const next = shiftAnchor('week', new Date(2026, 8, 16), 1)
    expect(toDateString(next)).toBe('2026-09-23')
  })

  it('moves the anchor to the previous week', () => {
    const previous = shiftAnchor('week', new Date(2026, 8, 16), -1)
    expect(toDateString(previous)).toBe('2026-09-09')
  })

  it('moves the anchor by month/year units', () => {
    expect(toDateString(shiftAnchor('month', new Date(2026, 0, 31), 1))).toBe('2026-03-03')
    expect(toDateString(shiftAnchor('year', new Date(2026, 0, 1), 1))).toBe('2027-01-01')
  })
})

describe('parseDateString / toDateString', () => {
  it('round-trips a Y-m-d value', () => {
    expect(toDateString(parseDateString('2026-09-16')!)).toBe('2026-09-16')
  })

  it('returns null for an empty/unparsable value', () => {
    expect(parseDateString(null)).toBeNull()
    expect(parseDateString('not-a-date')).toBeNull()
  })
})

describe('formatPeriodLabel', () => {
  it('formats a same-month week range', () => {
    expect(formatPeriodLabel('week', new Date(2026, 8, 16), 'it')).toBe('14 – 20 set 2026')
  })

  it('formats a month label with a capitalized first letter', () => {
    expect(formatPeriodLabel('month', new Date(2026, 1, 10), 'it')).toBe('Febbraio 2026')
  })

  it('formats a year label as the bare year', () => {
    expect(formatPeriodLabel('year', new Date(2026, 5, 1), 'it')).toBe('2026')
  })
})

describe('isTimeEntriesPeriodUnit', () => {
  it('accepts the four valid units and rejects anything else', () => {
    expect(isTimeEntriesPeriodUnit('week')).toBe(true)
    expect(isTimeEntriesPeriodUnit('quarter')).toBe(false)
    expect(isTimeEntriesPeriodUnit(undefined)).toBe(false)
  })
})
