import { describe, expect, it } from 'vitest'
import { EMPTY_STAT_VALUE, formatStatValue } from '@/features/stats/format-stat-value'
import { resolveStatsIcon } from '@/features/stats/stats-icons'

describe('formatStatValue — duration (spec 0147)', () => {
  it('renders whole minutes with the app-wide duration format', () => {
    expect(formatStatValue(450, 'duration', 'it')).toBe('7h 30m')
    expect(formatStatValue(45, 'duration', 'it')).toBe('45m')
    expect(formatStatValue(0, 'duration', 'it')).toBe('0m')
  })

  it('keeps the placeholder for a missing value', () => {
    expect(formatStatValue(null, 'duration', 'it')).toBe(EMPTY_STAT_VALUE)
  })
})

describe('resolveStatsIcon — Task panel icons (spec 0147)', () => {
  it.each(['alert-triangle', 'calendar-clock', 'timer', 'clock'])('knows %s', (name) => {
    expect(resolveStatsIcon(name)).toBeDefined()
  })
})
