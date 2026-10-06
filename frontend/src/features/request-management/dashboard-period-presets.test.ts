import { describe, expect, it } from 'vitest'
import {
  activePreset,
  presetRange,
  previousPeriod,
} from '@/features/request-management/dashboard-period-presets'

/** Spec 0192 D-2/D-4: one-click periods and the previous period of equal length, on the LOCAL calendar. */

const NOW = new Date(2026, 9, 5, 23, 30) // 5 October 2026, late evening local time

describe('presetRange', () => {
  it.each([
    ['today', { date_from: '2026-10-05', date_to: '2026-10-05' }],
    ['yesterday', { date_from: '2026-10-04', date_to: '2026-10-04' }],
    ['last7Days', { date_from: '2026-09-29', date_to: '2026-10-05' }],
    ['thisMonth', { date_from: '2026-10-01', date_to: '2026-10-05' }],
    ['lastMonth', { date_from: '2026-09-01', date_to: '2026-09-30' }],
    ['all', { date_from: '', date_to: '' }],
  ] as const)('%s', (preset, expected) => {
    expect(presetRange(preset, NOW)).toEqual(expected)
  })

  it('crosses the year boundary for last month in January', () => {
    expect(presetRange('lastMonth', new Date(2027, 0, 10))).toEqual({ date_from: '2026-12-01', date_to: '2026-12-31' })
  })
})

describe('activePreset', () => {
  it('derives the preset from the applied dates', () => {
    expect(activePreset({ date_from: '2026-09-29', date_to: '2026-10-05' }, NOW)).toBe('last7Days')
    expect(activePreset({ date_from: '', date_to: '' }, NOW)).toBe('all')
  })

  it('prefers the first matching preset on the 1st of a month', () => {
    // "Today" and "This month" are the same range on the 1st: the narrower name wins.
    expect(activePreset({ date_from: '2026-10-01', date_to: '2026-10-01' }, new Date(2026, 9, 1))).toBe('today')
  })

  it('is null for a range no preset produces', () => {
    expect(activePreset({ date_from: '2026-03-02', date_to: '2026-03-06' }, NOW)).toBeNull()
    expect(activePreset({ date_from: '2026-10-01', date_to: '' }, NOW)).toBeNull()
  })
})

describe('previousPeriod', () => {
  it('is the same number of days, ending the day before the start', () => {
    expect(previousPeriod({ date_from: '2026-03-02', date_to: '2026-03-06' })).toEqual({
      date_from: '2026-02-25',
      date_to: '2026-03-01',
    })
  })

  it('is the day before for a single day', () => {
    expect(previousPeriod({ date_from: '2026-10-05', date_to: '2026-10-05' })).toEqual({
      date_from: '2026-10-04',
      date_to: '2026-10-04',
    })
  })

  it('keeps the length across a DST change', () => {
    // 29 March 2026 is the spring-forward Sunday in Europe.
    expect(previousPeriod({ date_from: '2026-03-30', date_to: '2026-04-05' })).toEqual({
      date_from: '2026-03-23',
      date_to: '2026-03-29',
    })
  })

  it('is null with an open bound', () => {
    expect(previousPeriod({ date_from: '2026-03-02', date_to: '' })).toBeNull()
    expect(previousPeriod({ date_from: '', date_to: '2026-03-06' })).toBeNull()
  })
})
