import { afterEach, describe, expect, it } from 'vitest'
import {
  DATE_FORMAT_DEFAULT,
  TIME_FORMAT_DEFAULT,
  applyDateDisplayPreferences,
  formatDate,
  formatDateTime,
  formatDateTimeOptionalTime,
  toDateFormat,
  toTimeFormat,
} from '@/lib/formatting/date-display'

afterEach(() => {
  applyDateDisplayPreferences(DATE_FORMAT_DEFAULT, TIME_FORMAT_DEFAULT)
})

describe('formatDate', () => {
  it('defaults to the Italian day-first pattern', () => {
    expect(formatDate('2026-08-03')).toBe('03/08/2026')
  })

  it('renders the US month-first pattern', () => {
    applyDateDisplayPreferences('mdy', '24h')
    expect(formatDate('2026-08-03')).toBe('08/03/2026')
  })

  it('renders the ISO pattern', () => {
    applyDateDisplayPreferences('ymd', '24h')
    expect(formatDate('2026-08-03')).toBe('2026-08-03')
  })

  it('zero-pads single-digit days and months', () => {
    expect(formatDate('2026-01-09')).toBe('09/01/2026')
  })

  it('keeps the calendar day of a bare Y-m-d regardless of the UTC offset', () => {
    // Parsed as UTC midnight, this would render as the 2nd west of Greenwich.
    expect(formatDate('2026-08-03')).toBe('03/08/2026')
  })

  it('drops the time part of a full instant', () => {
    expect(formatDate('2026-08-03T14:30:00')).toBe('03/08/2026')
  })

  it('returns a blank for missing or unparsable values', () => {
    expect(formatDate(null)).toBe('')
    expect(formatDate('')).toBe('')
    expect(formatDate('not-a-date')).toBe('')
    expect(formatDate(42)).toBe('')
  })
})

describe('formatDateTime', () => {
  it('appends a zero-padded 24h clock by default', () => {
    expect(formatDateTime('2026-08-03T14:30:00')).toBe('03/08/2026 14:30')
    expect(formatDateTime('2026-08-03T09:05:00')).toBe('03/08/2026 09:05')
  })

  it('renders an AM/PM clock when the 12h preference is active', () => {
    applyDateDisplayPreferences('dmy', '12h')
    expect(formatDateTime('2026-08-03T14:30:00')).toBe('03/08/2026 2:30 PM')
    expect(formatDateTime('2026-08-03T09:05:00')).toBe('03/08/2026 9:05 AM')
  })

  it('renders the 12h edges of midnight and noon as 12', () => {
    applyDateDisplayPreferences('dmy', '12h')
    expect(formatDateTime('2026-08-03T00:15:00')).toBe('03/08/2026 12:15 AM')
    expect(formatDateTime('2026-08-03T12:15:00')).toBe('03/08/2026 12:15 PM')
  })

  it('combines both preferences', () => {
    applyDateDisplayPreferences('ymd', '12h')
    expect(formatDateTime('2026-08-03T14:30:00')).toBe('2026-08-03 2:30 PM')
  })

  it('returns a blank for missing values', () => {
    expect(formatDateTime(undefined)).toBe('')
  })
})

describe('formatDateTimeOptionalTime', () => {
  it('hides a midnight time, which means "no hour was set"', () => {
    expect(formatDateTimeOptionalTime('2026-08-03T00:00')).toBe('03/08/2026')
    expect(formatDateTimeOptionalTime('2026-08-03T00:00:00')).toBe('03/08/2026')
  })

  it('keeps a real time', () => {
    expect(formatDateTimeOptionalTime('2026-08-03T08:45')).toBe('03/08/2026 08:45')
  })
})

describe('preference guards', () => {
  it('falls back to the defaults for unknown values', () => {
    expect(toDateFormat('dd.mm.yyyy')).toBe('dmy')
    expect(toDateFormat(null)).toBe('dmy')
    expect(toTimeFormat('36h')).toBe('24h')
    expect(toTimeFormat(undefined)).toBe('24h')
  })

  it('passes declared values through', () => {
    expect(toDateFormat('ymd')).toBe('ymd')
    expect(toTimeFormat('12h')).toBe('12h')
  })
})
