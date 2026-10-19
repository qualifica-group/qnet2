import { describe, expect, it } from 'vitest'
import { dayOf, formatDayHeader, monthOf, monthRange, shiftMonth } from '@/features/absences/calendar/calendar-month'

describe('calendar-month', () => {
  it('reads the local month and day of a date', () => {
    expect(monthOf(new Date(2026, 11, 31, 23, 30))).toBe('2026-12')
    expect(dayOf(new Date(2026, 0, 5))).toBe('2026-01-05')
  })

  it('shifts across the year boundary', () => {
    expect(shiftMonth('2026-12', 1)).toBe('2027-01')
    expect(shiftMonth('2026-01', -1)).toBe('2025-12')
  })

  it('spans the whole month, leap years included', () => {
    expect(monthRange('2026-12')).toEqual({ from: '2026-12-01', to: '2026-12-31' })
    expect(monthRange('2028-02')).toEqual({ from: '2028-02-01', to: '2028-02-29' })
    expect(monthRange('2026-02')).toEqual({ from: '2026-02-01', to: '2026-02-28' })
  })

  it('labels a day column with its number and weekday', () => {
    expect(formatDayHeader('2026-12-07', 'it')).toEqual({ day: 7, weekday: 'L' })
  })
})
