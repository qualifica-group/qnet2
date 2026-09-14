import { describe, expect, it } from 'vitest'
import {
  computeTrackedMinutes,
  formatMinutesLabel,
  minutesToTimeValue,
  timeValueToMinutes,
} from '@/features/time-entries/time-entry-format'

/** Spec 0122 AC-029: the "Tempo" field derives minutes from the time pair. */

describe('formatMinutesLabel', () => {
  it('renders a sub-hour duration without hours', () => {
    expect(formatMinutesLabel(45)).toBe('45m')
  })

  it('renders hours and zero-padded minutes', () => {
    expect(formatMinutesLabel(450)).toBe('7h 30m')
  })

  it('zero-pads minutes even when the remainder is a round hour', () => {
    expect(formatMinutesLabel(480)).toBe('8h 00m')
  })

  it('clamps a negative/NaN input to 0', () => {
    expect(formatMinutesLabel(-10)).toBe('0m')
    expect(formatMinutesLabel(Number.NaN)).toBe('0m')
  })
})

describe('minutesToTimeValue / timeValueToMinutes', () => {
  it('round-trips a duration through HH:MM', () => {
    expect(minutesToTimeValue(90)).toBe('01:30')
    expect(timeValueToMinutes('01:30')).toBe(90)
  })

  it('clamps above 23:59', () => {
    expect(minutesToTimeValue(1500)).toBe('23:59')
  })

  it('rejects an invalid time-of-day string', () => {
    expect(timeValueToMinutes('24:00')).toBeNull()
    expect(timeValueToMinutes('not-a-time')).toBeNull()
  })
})

describe('computeTrackedMinutes', () => {
  it('computes minutes from 09:00 to 10:30', () => {
    expect(computeTrackedMinutes('09:00', '10:30')).toBe(90)
  })

  it('returns null when either side is missing', () => {
    expect(computeTrackedMinutes(null, '10:30')).toBeNull()
    expect(computeTrackedMinutes('09:00', undefined)).toBeNull()
  })

  it('returns null when end is not after start (no recompute, D-6)', () => {
    expect(computeTrackedMinutes('10:30', '10:30')).toBeNull()
    expect(computeTrackedMinutes('10:30', '09:00')).toBeNull()
  })
})
